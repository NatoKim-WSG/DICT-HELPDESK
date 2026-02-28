<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CredentialHandoff;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\SystemLogService;
use App\Support\DefaultPasswordResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

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

        if (! $this->canManageStaffAccounts($currentUser)) {
            $segment = 'clients';
        }

        $query = User::query()->where('email', 'not like', '%@system.local');
        $departmentsQuery = User::query()->where('email', 'not like', '%@system.local');

        $this->applyVisibilityScope($query, $currentUser);
        $this->applyVisibilityScope($departmentsQuery, $currentUser);
        $this->applySegmentScope($query, $segment, $currentUser);
        $this->applySegmentScope($departmentsQuery, $segment, $currentUser);

        if ($request->filled('role') && $request->role !== 'all') {
            $requestedRole = User::normalizeRole($request->string('role')->toString());

            if ($requestedRole === User::ROLE_ADMIN) {
                $query->whereIn('role', [
                    User::ROLE_SHADOW,
                    User::ROLE_ADMIN,
                ]);
            } elseif ($requestedRole === User::ROLE_TECHNICAL) {
                $query->where('role', User::ROLE_TECHNICAL);
            } else {
                $query->where('role', $requestedRole);
            }
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
                    WHEN 'shadow' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'super_user' THEN 3
                    WHEN 'technical' THEN 4
                    WHEN 'client' THEN 5
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
            : ($currentUser->normalizedRole() === User::ROLE_ADMIN
                ? 'Manage internal admin, super user, and technical accounts.'
                : 'Manage internal admin, super user, and technical accounts.');

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
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $role = $request->string('role')->toString();
        $department = $this->departmentForRole($role, $request->string('department')->toString());
        $persistedRole = $this->normalizeRoleForPersistence($role);
        if ($request->filled('password')) {
            $resolvedPassword = (string) $request->password;
        } else {
            $resolvedPassword = DefaultPasswordResolver::user();
        }

        $createdUser = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $department,
            'role' => $persistedRole,
            'password' => Hash::make($resolvedPassword),
            'is_active' => true,
            'is_profile_locked' => false,
        ]);
        $this->systemLogs->record(
            'user.created',
            'Created a user account.',
            [
                'category' => 'user',
                'target_type' => User::class,
                'target_id' => $createdUser->id,
                'metadata' => [
                    'role' => User::normalizeRole($createdUser->role),
                    'department' => $createdUser->department,
                    'is_active' => (bool) $createdUser->is_active,
                ],
                'request' => $request,
            ]
        );

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

        if ($this->cannotManageTarget($currentUser, $user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to view this user.');
        }

        $statistics = $this->buildUserTicketStatistics($user);
        $statisticsLinks = $this->buildUserStatisticsLinks($user, $statistics['show_assigned']);
        $recentTickets = $this->recentTicketsForUser($user);
        $canRevealManagedPassword = $currentUser->isShadow() && ! $user->isShadow() && $user->id !== $currentUser->id;
        $activeCredentialHandoff = null;
        if ($canRevealManagedPassword) {
            $activeCredentialHandoff = CredentialHandoff::query()
                ->where('target_user_id', $user->id)
                ->whereNull('consumed_at')
                ->where('expires_at', '>', now())
                ->first();
        }
        $revealedManagedPassword = session('managed_password_reveal');

        return view('admin.users.show', compact(
            'user',
            'statistics',
            'statisticsLinks',
            'recentTickets',
            'canRevealManagedPassword',
            'activeCredentialHandoff',
            'revealedManagedPassword'
        ));
    }

    public function resetManagedUserPassword(User $user)
    {
        $currentUser = auth()->user();

        if (! $currentUser->isShadow()) {
            abort(403, 'Access denied. Insufficient permissions.');
        }

        if ($this->isSystemReplacementUser($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'System archive users cannot be modified.');
        }

        if ($user->isShadow()) {
            return redirect()->route('admin.users.show', $user)
                ->with('error', 'Shadow account passwords cannot be revealed from user management.');
        }

        if ($user->id === $currentUser->id) {
            return redirect()->route('admin.users.show', $user)
                ->with('error', 'Use Account Settings to update your own password.');
        }

        $temporaryPassword = $this->generateTemporaryManagedPassword();
        $expiresAt = now()->addMinutes(10);

        $user->forceFill([
            'password' => Hash::make($temporaryPassword),
        ])->save();

        CredentialHandoff::query()->updateOrCreate(
            ['target_user_id' => $user->id],
            [
                'issued_by_user_id' => $currentUser->id,
                'temporary_password' => $temporaryPassword,
                'expires_at' => $expiresAt,
                'revealed_at' => null,
                'consumed_at' => null,
            ]
        );

        $this->systemLogs->record(
            'user.password.handoff_issued',
            'Issued a one-time managed password handoff.',
            [
                'category' => 'security',
                'target_type' => User::class,
                'target_id' => $user->id,
                'metadata' => [
                    'expires_at' => $expiresAt->toIso8601String(),
                ],
            ]
        );

        return redirect()->route('admin.users.show', $user)
            ->with('success', 'Temporary password issued. Reveal it once before it expires.');
    }

    public function revealManagedUserPassword(User $user)
    {
        $currentUser = auth()->user();

        if (! $currentUser->isShadow()) {
            abort(403, 'Access denied. Insufficient permissions.');
        }

        if ($this->isSystemReplacementUser($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'System archive users cannot be modified.');
        }

        if ($user->isShadow()) {
            return redirect()->route('admin.users.show', $user)
                ->with('error', 'Shadow account passwords cannot be revealed from user management.');
        }

        if ($user->id === $currentUser->id) {
            return redirect()->route('admin.users.show', $user)
                ->with('error', 'Use Account Settings to update your own password.');
        }

        $handoff = CredentialHandoff::query()
            ->where('target_user_id', $user->id)
            ->whereNull('consumed_at')
            ->where('expires_at', '>', now())
            ->first();

        if (! $handoff) {
            return redirect()->route('admin.users.show', $user)
                ->with('error', 'No active temporary password is available for this account.');
        }

        $temporaryPassword = (string) $handoff->temporary_password;
        $handoff->forceFill([
            'revealed_at' => $handoff->revealed_at ?? now(),
            'consumed_at' => now(),
        ])->save();

        $this->systemLogs->record(
            'user.password.handoff_revealed',
            'Revealed a one-time managed password handoff.',
            [
                'category' => 'security',
                'target_type' => User::class,
                'target_id' => $user->id,
            ]
        );

        return redirect()->route('admin.users.show', $user)
            ->with('managed_password_reveal', $temporaryPassword)
            ->with('success', 'Temporary password revealed once. Copy it now; it cannot be viewed again.');
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

        if ($this->cannotManageTarget($currentUser, $user)) {
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

        if ($this->cannotManageTarget($currentUser, $user)) {
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
            'is_profile_locked' => 'boolean',
        ];

        if (! $canEditPassword) {
            $validationRules['password_confirmation'] = 'prohibited';
        }

        $request->validate($validationRules);

        $role = $request->string('role')->toString();
        $department = $this->departmentForRole($role, $request->string('department')->toString());
        $persistedRole = $this->normalizeRoleForPersistence($role);
        $requestedIsProfileLocked = $request->boolean('is_profile_locked');

        if ($user->is_profile_locked) {
            $isChangingProfileFieldsWhileLocked = (
                (string) $request->string('name')->toString() !== (string) $user->name
                || (string) $request->string('email')->toString() !== (string) $user->email
                || (string) $request->string('phone')->toString() !== (string) ($user->phone ?? '')
                || (string) $department !== (string) $user->department
                || (string) $persistedRole !== (string) $user->normalizedRole()
                || $request->boolean('is_active') !== (bool) $user->is_active
                || $request->filled('password')
            );

            if ($isChangingProfileFieldsWhileLocked) {
                return back()
                    ->withInput()
                    ->with('error', 'Profile editing is locked. Unlock it first before changing other fields.');
            }
        }

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $department,
            'role' => $persistedRole,
            'is_active' => $request->boolean('is_active'),
            'is_profile_locked' => $requestedIsProfileLocked,
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
            CredentialHandoff::query()
                ->where('target_user_id', $user->id)
                ->delete();
        }

        $user->fill($updateData);

        if (! $user->isDirty()) {
            return redirect()->route('admin.users.index')
                ->with('success', 'No changes were detected.');
        }

        $changedFields = array_keys($user->getDirty());
        $nonSensitiveChangedFields = array_values(array_filter(
            $changedFields,
            static fn (string $field): bool => $field !== 'password'
        ));

        $user->save();
        $this->systemLogs->record(
            'user.updated',
            'Updated a user account.',
            [
                'category' => 'user',
                'target_type' => User::class,
                'target_id' => $user->id,
                'metadata' => [
                    'changed_fields' => $nonSensitiveChangedFields,
                ],
                'request' => $request,
            ]
        );

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

        if ($user->is_profile_locked) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Locked users cannot be deleted. Unlock the profile first.');
        }

        if ($user->isShadow()) {
            if (! $currentUser->isShadow()) {
                return redirect()->route('admin.users.index')
                    ->with('error', 'You do not have permission to delete this user.');
            }

            return redirect()->route('admin.users.index')
                ->with('error', 'This account cannot be deleted.');
        }

        // Admin users can only be deleted by shadows.
        if ($user->normalizedRole() === User::ROLE_ADMIN && ! $currentUser->isShadow()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Admin users cannot be deleted.');
        }

        // Super users can only delete client accounts.
        if ($this->cannotManageTarget($currentUser, $user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to delete this user.');
        }

        $deletedUserId = $user->id;
        $deletedUserRole = User::normalizeRole($user->role);
        $deletedUserDepartment = (string) $user->department;

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
        $this->systemLogs->record(
            'user.deleted',
            'Deleted a user account.',
            [
                'category' => 'user',
                'target_type' => User::class,
                'target_id' => $deletedUserId,
                'metadata' => [
                    'role' => $deletedUserRole,
                    'department' => $deletedUserDepartment,
                ],
            ]
        );

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

        if ($user->isShadow()) {
            if (! $currentUser->isShadow()) {
                return response()->json(['error' => 'You do not have permission to change this user status.'], 403);
            }

            return response()->json(['error' => 'This account cannot be deactivated.'], 403);
        }

        if ($user->normalizedRole() === User::ROLE_ADMIN && ! $currentUser->isShadow()) {
            return response()->json(['error' => 'Admin users cannot be deactivated.'], 403);
        }

        if ($this->cannotManageTarget($currentUser, $user)) {
            return response()->json(['error' => 'You do not have permission to change this user status.'], 403);
        }

        $user->update(['is_active' => ! $user->is_active]);
        $this->systemLogs->record(
            'user.status.toggled',
            'Toggled user account status.',
            [
                'category' => 'user',
                'target_type' => User::class,
                'target_id' => $user->id,
                'metadata' => [
                    'is_active' => (bool) $user->is_active,
                ],
            ]
        );

        return response()->json([
            'success' => true,
            'is_active' => $user->is_active,
            'message' => 'User status updated successfully.',
        ]);
    }

    private function availableRolesFor(User $currentUser): array
    {
        $roles = [User::ROLE_CLIENT];

        if ($currentUser->isShadow()) {
            $roles[] = User::ROLE_ADMIN;
            $roles[] = User::ROLE_TECHNICAL;
            $roles[] = User::ROLE_SUPER_USER;

            return $roles;
        }

        if ($currentUser->normalizedRole() === User::ROLE_ADMIN) {
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
        if ($currentUser->isShadow()) {
            return true;
        }

        if ($currentUser->isSuperAdmin()) {
            return ! $targetUser->isShadow();
        }

        return ! (
            $currentUser->normalizedRole() === User::ROLE_SUPER_USER
            && User::normalizeRole($targetUser->role) === User::ROLE_CLIENT
        );
    }

    private function applyVisibilityScope($query, User $currentUser): void
    {
        if ($currentUser->isShadow()) {
            return;
        }

        if ($currentUser->normalizedRole() === User::ROLE_ADMIN) {
            $query->where('role', '!=', User::ROLE_SHADOW);

            return;
        }

        if (! $currentUser->isSuperAdmin()) {
            $query->where('id', '!=', $currentUser->id)
                ->whereIn('role', $this->manageableRolesForAdmin());
        }
    }

    private function applySegmentScope($query, string $segment, User $currentUser): void
    {
        if ($segment === 'clients' || ! $this->canManageStaffAccounts($currentUser)) {
            $query->where('role', User::ROLE_CLIENT);

            return;
        }

        if ($this->canManageStaffAccounts($currentUser)) {
            $query->whereIn('role', [
                User::ROLE_SHADOW,
                User::ROLE_ADMIN,
                User::ROLE_SUPER_USER,
                User::ROLE_TECHNICAL,
            ]);

            if ($currentUser->normalizedRole() === User::ROLE_ADMIN) {
                $query->where('role', '!=', User::ROLE_SHADOW);
            }

            return;
        }
    }

    private function availableRolesFilterForSegment(string $segment, User $currentUser): array
    {
        if ($segment === 'clients') {
            return [User::ROLE_CLIENT];
        }

        if ($this->canManageStaffAccounts($currentUser)) {
            return [
                User::ROLE_ADMIN,
                User::ROLE_SUPER_USER,
                User::ROLE_TECHNICAL,
            ];
        }

        return [User::ROLE_CLIENT];
    }

    private function departmentForRole(string $role, string $department): string
    {
        $normalizedRole = User::normalizeRole($role);

        if (in_array($normalizedRole, [User::ROLE_SHADOW, User::ROLE_ADMIN, User::ROLE_SUPER_USER, User::ROLE_TECHNICAL], true)) {
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

    private function isSystemReplacementUser(User $user): bool
    {
        return str_ends_with(strtolower((string) $user->email), '@system.local');
    }

    private function replacementUserForDeletedAccount(User $deletedUser): User
    {
        $normalizedRole = $deletedUser->normalizedRole();
        $isSupportAccount = in_array($normalizedRole, [
            User::ROLE_SHADOW,
            User::ROLE_ADMIN,
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
                    'password' => Hash::make(Str::random(64)),
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
                'password' => Hash::make(Str::random(64)),
                'is_active' => false,
            ]
        );
    }

    private function generateTemporaryManagedPassword(): string
    {
        return strtoupper(Str::random(4)).'-'.Str::random(8);
    }

    private function canManageStaffAccounts(User $currentUser): bool
    {
        return in_array($currentUser->normalizedRole(), [User::ROLE_SHADOW, User::ROLE_ADMIN], true);
    }

    private function cannotManageTarget(User $currentUser, User $targetUser): bool
    {
        if ($currentUser->isShadow()) {
            return false;
        }

        if ($currentUser->normalizedRole() === User::ROLE_ADMIN && $targetUser->isShadow()) {
            return true;
        }

        if ($currentUser->isSuperAdmin()) {
            return false;
        }

        return ! $this->isManageableByNonSuperAdmin($targetUser);
    }
}


