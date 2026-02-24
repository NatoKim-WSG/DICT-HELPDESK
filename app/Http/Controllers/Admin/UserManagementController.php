<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        return $this->renderUserIndex($request, 'staff');
    }

    public function clients(Request $request)
    {
        return $this->renderUserIndex($request, 'clients');
    }

    private function renderUserIndex(Request $request, string $segment)
    {
        $currentUser = auth()->user();
        $query = User::query()->where('email', 'not like', '%@system.local');
        $departmentsQuery = User::query()->where('email', 'not like', '%@system.local');

        $this->applyVisibilityScope($query, $currentUser);
        $this->applyVisibilityScope($departmentsQuery, $currentUser);
        $this->applySegmentScope($query, $segment, $currentUser);
        $this->applySegmentScope($departmentsQuery, $segment, $currentUser);

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);

            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%")
                    ->orWhere('department', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department') && $request->department !== 'all') {
            $query->where('department', $request->department);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        $users = $query
            ->orderByRaw("
                CASE role
                    WHEN 'super_admin' THEN 1
                    WHEN 'super_user' THEN 2
                    WHEN 'admin' THEN 2
                    WHEN 'technical' THEN 3
                    WHEN 'technician' THEN 3
                    WHEN 'client' THEN 4
                    ELSE 6
                END
            ")
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $departments = $departmentsQuery
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        $availableRolesFilter = $this->availableRolesFilterForSegment($segment, $currentUser);
        $segmentTitle = $segment === 'clients' ? 'Client Accounts' : 'Staff Accounts';
        $segmentDescription = $segment === 'clients'
            ? 'Manage client accounts separately from internal staff.'
            : 'Manage internal super user and technical accounts.';

        return view('admin.users.index', compact(
            'users',
            'departments',
            'availableRolesFilter',
            'segment',
            'segmentTitle',
            'segmentDescription'
        ));
    }

    public function create()
    {
        $availableRoles = $this->availableRolesFor(auth()->user());

        return view('admin.users.create', compact('availableRoles'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $availableRoles = $this->availableRolesFor($user);
        $allowedDepartments = $this->allowedDepartments();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email|max:255',
            'phone' => 'required|string|max:20',
            'department' => ['required', Rule::in($allowedDepartments)],
            'role' => ['required', Rule::in($availableRoles)],
            'password' => 'required|string|min:8|confirmed',
        ]);

        $role = $request->string('role')->toString();
        $department = $this->departmentForRole($role, $request->string('department')->toString());
        $persistedRole = $this->normalizeRoleForPersistence($role);

        try {
            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'department' => $department,
                'role' => $persistedRole,
                'password' => Hash::make($request->password),
                'is_active' => true,
            ]);
        } catch (QueryException $exception) {
            $fallbackRole = $this->legacyFallbackRole($persistedRole);
            if (!$fallbackRole) {
                throw $exception;
            }

            User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'department' => $department,
                'role' => $fallbackRole,
                'password' => Hash::make($request->password),
                'is_active' => true,
            ]);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to view this user.');
        }

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id && !$currentUser->isSuperAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot edit your own account.');
        }

        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to edit this user.');
        }

        $availableRoles = $this->availableRolesFor($currentUser);

        return view('admin.users.edit', compact('user', 'availableRoles'));
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id && !$currentUser->isSuperAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot edit your own account.');
        }

        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to edit this user.');
        }

        $availableRoles = $this->availableRolesFor($currentUser);
        $allowedDepartments = $this->allowedDepartments();

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'department' => ['required', Rule::in($allowedDepartments)],
            'role' => ['required', Rule::in($availableRoles)],
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'boolean',
        ]);

        $role = $request->string('role')->toString();
        $department = $this->departmentForRole($role, $request->string('department')->toString());
        $persistedRole = $this->normalizeRoleForPersistence($role);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $department,
            'role' => $persistedRole,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        try {
            $user->update($updateData);
        } catch (QueryException $exception) {
            $fallbackRole = $this->legacyFallbackRole($persistedRole);
            if (!$fallbackRole) {
                throw $exception;
            }

            $updateData['role'] = $fallbackRole;
            $user->update($updateData);
        }

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $currentUser = auth()->user();

        if ($this->isSystemReplacementUser($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'System archive users cannot be deleted.');
        }

        // Users cannot delete themselves
        if ($user->id === $currentUser->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // Super admin users cannot be deleted by anyone
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Super admin users cannot be deleted.');
        }

        // Regular admins can only delete clients
        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to delete this user.');
        }

        DB::transaction(function () use ($user) {
            $replacementUser = $this->replacementUserForDeletedAccount($user);

            Ticket::where('user_id', $user->id)->update([
                'user_id' => $replacementUser->id,
            ]);

            TicketReply::where('user_id', $user->id)->update([
                'user_id' => $replacementUser->id,
            ]);

            Ticket::where('assigned_to', $user->id)->update([
                'assigned_to' => null,
            ]);

            $user->delete();
        });

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully. Ticket and chat history were preserved.');
    }

    public function toggleStatus(User $user)
    {
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id) {
            return response()->json(['error' => 'You cannot deactivate your own account.'], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json(['error' => 'Super admin users cannot be deactivated.'], 403);
        }

        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return response()->json(['error' => 'You do not have permission to change this user status.'], 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $user->is_active,
            'message' => 'User status updated successfully.'
        ]);
    }

    private function availableRolesFor(User $currentUser): array
    {
        $roles = [User::ROLE_CLIENT, User::ROLE_TECHNICAL];

        if ($currentUser->isSuperAdmin()) {
            $roles[] = User::ROLE_SUPER_USER;
        }

        return $roles;
    }

    private function manageableRolesForAdmin(): array
    {
        return [User::ROLE_CLIENT, User::ROLE_TECHNICAL, User::ROLE_TECHNICIAN];
    }

    private function applyVisibilityScope($query, User $currentUser): void
    {
        if (!$currentUser->isSuperAdmin()) {
            $query->where('id', '!=', $currentUser->id)
                ->whereIn('role', $this->manageableRolesForAdmin());
        }
    }

    private function applySegmentScope($query, string $segment, User $currentUser): void
    {
        if ($segment === 'clients') {
            $query->where('role', User::ROLE_CLIENT);
            return;
        }

        if ($currentUser->isSuperAdmin()) {
            $query->whereIn('role', [
                User::ROLE_SUPER_ADMIN,
                User::ROLE_SUPER_USER,
                User::ROLE_ADMIN,
                User::ROLE_TECHNICAL,
                User::ROLE_TECHNICIAN,
            ]);
            return;
        }

        $query->whereIn('role', [User::ROLE_TECHNICAL, User::ROLE_TECHNICIAN]);
    }

    private function availableRolesFilterForSegment(string $segment, User $currentUser): array
    {
        if ($segment === 'clients') {
            return [User::ROLE_CLIENT];
        }

        if ($currentUser->isSuperAdmin()) {
            return [
                User::ROLE_SUPER_ADMIN,
                User::ROLE_SUPER_USER,
                User::ROLE_ADMIN,
                User::ROLE_TECHNICAL,
                User::ROLE_TECHNICIAN,
            ];
        }

        return [User::ROLE_TECHNICAL, User::ROLE_TECHNICIAN];
    }

    private function allowedDepartments(): array
    {
        return ['iOne', 'DEPED', 'DICT', 'DAR'];
    }

    private function departmentForRole(string $role, string $department): string
    {
        $normalizedRole = User::normalizeRole($role);

        if (in_array($normalizedRole, [User::ROLE_SUPER_USER, User::ROLE_TECHNICAL, User::ROLE_SUPER_ADMIN], true)) {
            return 'iOne';
        }

        return $department;
    }

    private function normalizeRoleForPersistence(string $role): string
    {
        return User::normalizeRole($role);
    }

    private function legacyFallbackRole(string $role): ?string
    {
        return match (User::normalizeRole($role)) {
            User::ROLE_SUPER_USER => User::ROLE_ADMIN,
            User::ROLE_TECHNICAL => User::ROLE_TECHNICIAN,
            default => null,
        };
    }

    private function isSystemReplacementUser(User $user): bool
    {
        return str_ends_with(strtolower((string) $user->email), '@system.local');
    }

    private function replacementUserForDeletedAccount(User $deletedUser): User
    {
        $normalizedRole = $deletedUser->normalizedRole();
        $isSupportAccount = in_array($normalizedRole, [
            User::ROLE_SUPER_ADMIN,
            User::ROLE_SUPER_USER,
            User::ROLE_TECHNICAL,
        ], true);

        if ($isSupportAccount) {
            return User::firstOrCreate(
                ['email' => 'deleted.support@system.local'],
                [
                    'name' => 'Deleted Support Account',
                    'phone' => null,
                    'department' => 'iOne',
                    'role' => User::ROLE_TECHNICAL,
                    'password' => Hash::make('password'),
                    'is_active' => false,
                ]
            );
        }

        return User::firstOrCreate(
            ['email' => 'deleted.client@system.local'],
            [
                'name' => 'Deleted Client Account',
                'phone' => null,
                'department' => 'iOne',
                'role' => User::ROLE_CLIENT,
                'password' => Hash::make('password'),
                'is_active' => false,
            ]
        );
    }
}
