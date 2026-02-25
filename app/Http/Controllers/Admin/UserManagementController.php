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

        if (! $currentUser->isSuperAdmin()) {
            $segment = 'clients';
        }

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
            ->orderByRaw("LOWER(COALESCE(name, ''))")
            ->orderBy('name')
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $departments = $departmentsQuery
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->sort(fn (string $left, string $right) => strnatcasecmp($left, $right))
            ->values();

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
        $allowedDepartments = User::allowedDepartments();

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
            if (! $fallbackRole) {
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

        if ($this->isSystemReplacementUser($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'System archive users cannot be accessed from user management.');
        }

        if (! $currentUser->isSuperAdmin() && ! $this->isManageableByNonSuperAdmin($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to view this user.');
        }

        $statistics = $this->buildUserTicketStatistics($user);
        $statisticsLinks = $this->buildUserStatisticsLinks($user, $statistics['show_assigned']);
        $recentTickets = $this->recentTicketsForUser($user);

        return view('admin.users.show', compact('user', 'statistics', 'statisticsLinks', 'recentTickets'));
    }

    public function edit(User $user)
    {
        $currentUser = auth()->user();

        if ($this->isSystemReplacementUser($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'System archive users cannot be accessed from user management.');
        }

        if ($user->id === $currentUser->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Use Account Settings to edit your own account.');
        }

        if (! $currentUser->isSuperAdmin() && ! $this->isManageableByNonSuperAdmin($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to edit this user.');
        }

        $availableRoles = $this->availableRolesFor($currentUser);
        $canEditPassword = $this->canEditManagedUserPassword($currentUser, $user);

        return view('admin.users.edit', compact('user', 'availableRoles', 'canEditPassword'));
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();

        if ($this->isSystemReplacementUser($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'System archive users cannot be modified.');
        }

        if ($user->id === $currentUser->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Use Account Settings to edit your own account.');
        }

        if (! $currentUser->isSuperAdmin() && ! $this->isManageableByNonSuperAdmin($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to edit this user.');
        }

        $availableRoles = $this->availableRolesFor($currentUser);
        $allowedDepartments = User::allowedDepartments();
        $canEditPassword = $this->canEditManagedUserPassword($currentUser, $user);

        $validationRules = [
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'department' => ['required', Rule::in($allowedDepartments)],
            'role' => ['required', Rule::in($availableRoles)],
            'password' => $canEditPassword
                ? 'nullable|string|min:8|confirmed'
                : 'prohibited',
            'is_active' => 'boolean',
        ];

        if (! $canEditPassword) {
            $validationRules['password_confirmation'] = 'prohibited';
        }

        $request->validate($validationRules);

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

        $user->fill($updateData);

        if (! $user->isDirty()) {
            return redirect()->route('admin.users.index')
                ->with('success', 'No changes were detected.');
        }

        try {
            $user->save();
        } catch (QueryException $exception) {
            $fallbackRole = $this->legacyFallbackRole($persistedRole);
            if (! $fallbackRole) {
                throw $exception;
            }

            $user->role = $fallbackRole;
            $user->save();
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

        // Super users can only delete client accounts.
        if (! $currentUser->isSuperAdmin() && ! $this->isManageableByNonSuperAdmin($user)) {
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

        if ($this->isSystemReplacementUser($user)) {
            return response()->json(['error' => 'System archive users cannot be modified.'], 403);
        }

        if ($user->id === $currentUser->id) {
            return response()->json(['error' => 'You cannot deactivate your own account.'], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json(['error' => 'Super admin users cannot be deactivated.'], 403);
        }

        if (! $currentUser->isSuperAdmin() && ! $this->isManageableByNonSuperAdmin($user)) {
            return response()->json(['error' => 'You do not have permission to change this user status.'], 403);
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $user->is_active,
            'message' => 'User status updated successfully.',
        ]);
    }

    private function availableRolesFor(User $currentUser): array
    {
        $roles = [User::ROLE_CLIENT];

        if ($currentUser->isSuperAdmin()) {
            $roles[] = User::ROLE_TECHNICAL;
            $roles[] = User::ROLE_SUPER_USER;
        }

        return $roles;
    }

    private function manageableRolesForAdmin(): array
    {
        return [User::ROLE_CLIENT];
    }

    private function isManageableByNonSuperAdmin(User $user): bool
    {
        return in_array(User::normalizeRole($user->role), $this->manageableRolesForAdmin(), true);
    }

    private function canEditManagedUserPassword(User $currentUser, User $targetUser): bool
    {
        if ($currentUser->isSuperAdmin()) {
            return true;
        }

        return ! (
            $currentUser->normalizedRole() === User::ROLE_SUPER_USER
            && User::normalizeRole($targetUser->role) === User::ROLE_CLIENT
        );
    }

    private function applyVisibilityScope($query, User $currentUser): void
    {
        if (! $currentUser->isSuperAdmin()) {
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

        return [User::ROLE_CLIENT];
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

    private function buildUserTicketStatistics(User $user): array
    {
        $isClient = $user->normalizedRole() === User::ROLE_CLIENT;
        $baseTickets = $isClient
            ? Ticket::query()->where('user_id', $user->id)
            : Ticket::query()->where('assigned_to', $user->id);

        return [
            'total_tickets' => (clone $baseTickets)->count(),
            'open_tickets' => (clone $baseTickets)->whereIn('status', Ticket::OPEN_STATUSES)->count(),
            'closed_tickets' => (clone $baseTickets)->whereIn('status', Ticket::CLOSED_STATUSES)->count(),
            'assigned_tickets' => $isClient ? null : (clone $baseTickets)->count(),
            'show_assigned' => ! $isClient,
        ];
    }

    private function buildUserStatisticsLinks(User $user, bool $showAssigned): array
    {
        $isClient = $user->normalizedRole() === User::ROLE_CLIENT;
        $primaryFilter = $isClient
            ? ['account_id' => $user->id]
            : ['assigned_to' => $user->id];

        return [
            'total_tickets' => route('admin.tickets.index', array_merge($primaryFilter, ['tab' => 'tickets', 'include_closed' => 1])),
            'open_tickets' => route('admin.tickets.index', array_merge($primaryFilter, ['tab' => 'tickets'])),
            'closed_tickets' => route('admin.tickets.index', array_merge($primaryFilter, ['tab' => 'history'])),
            'assigned_tickets' => $showAssigned
                ? route('admin.tickets.index', ['tab' => 'tickets', 'assigned_to' => $user->id, 'include_closed' => 1])
                : null,
        ];
    }

    private function recentTicketsForUser(User $user)
    {
        $normalizedRole = $user->normalizedRole();

        return Ticket::query()
            ->where(function ($query) use ($user, $normalizedRole) {
                $query->where('user_id', $user->id);

                if ($normalizedRole !== User::ROLE_CLIENT) {
                    $query->orWhere('assigned_to', $user->id);
                }
            })
            ->latest()
            ->take(5)
            ->get();
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
