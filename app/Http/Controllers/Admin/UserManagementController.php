<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Http\Requests\Admin\Users\UpdateUserRequest;
use App\Models\CredentialHandoff;
use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\User;
use App\Services\Admin\UserDirectoryService;
use App\Services\SystemLogService;
use App\Support\DefaultPasswordResolver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserManagementController extends Controller
{
    public function __construct(
        private SystemLogService $systemLogs,
        private UserDirectoryService $userDirectory,
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

        return view('admin.users.index', $this->userDirectory->buildIndexViewData($request, $currentUser, $segment));
    }

    public function create()
    {
        $availableRoles = $this->availableRolesFor(auth()->user());

        return view('admin.users.create', compact('availableRoles'));
    }

    public function store(StoreUserRequest $request)
    {
        $user = auth()->user();

        $requestedRole = User::normalizeRole($request->string('role')->toString());
        $willBeClientRole = $requestedRole === User::ROLE_CLIENT;

        $role = $request->string('role')->toString();
        $department = $this->userDirectory->departmentForRole($role, $request->string('department')->toString());
        $persistedRole = $this->userDirectory->normalizeRoleForPersistence($role);
        $isClientRole = $persistedRole === User::ROLE_CLIENT;
        if ($request->filled('password')) {
            $resolvedPassword = (string) $request->password;
        } else {
            $resolvedPassword = $isClientRole
                ? DefaultPasswordResolver::clientFixed()
                : DefaultPasswordResolver::staff();
        }

        $createdUser = User::create([
            'username' => $request->string('username')->toString() ?: null,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $department,
            'client_notes' => ($persistedRole === User::ROLE_CLIENT && $user->isShadow())
                ? (trim($request->string('client_notes')->toString()) ?: null)
                : null,
            'role' => $persistedRole,
            'password' => Hash::make($resolvedPassword),
            'is_active' => true,
            'is_profile_locked' => false,
            'must_change_password' => ! $request->filled('password') && ! $isClientRole,
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

        if ($this->userDirectory->cannotManageTarget($currentUser, $user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to view this user.');
        }

        $statistics = $this->userDirectory->buildUserTicketStatistics($user);
        $statisticsLinks = $this->userDirectory->buildUserStatisticsLinks($user, $statistics['show_assigned']);
        $recentTickets = $this->userDirectory->recentTicketsForUser($user);
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
            'must_change_password' => ! $user->isClient(),
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

    public function edit(Request $request, User $user)
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

        if ($this->userDirectory->cannotManageTarget($currentUser, $user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to edit this user.');
        }

        $availableRoles = $this->availableRolesFor($currentUser);
        $canEditPassword = $this->canEditManagedUserPassword($currentUser, $user);
        $returnTo = $this->resolveManagedUserReturnUrl($request->query('return_to'), $user);

        return view('admin.users.edit', compact('user', 'availableRoles', 'canEditPassword', 'returnTo'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $currentUser = auth()->user();
        $stayOnEdit = $request->boolean('stay_on_edit');
        $returnToInput = $request->input('return_to');
        $hasReturnTo = is_string($returnToInput) && trim($returnToInput) !== '';
        $returnTo = $hasReturnTo
            ? $this->resolveManagedUserReturnUrl($returnToInput, $user)
            : route('admin.users.index', absolute: false);

        if ($this->isSystemReplacementUser($user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'System archive users cannot be modified.');
        }

        if ($user->id === $currentUser->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Use Account Settings to edit your own account.');
        }

        if ($this->userDirectory->cannotManageTarget($currentUser, $user)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to edit this user.');
        }

        $canEditPassword = $this->canEditManagedUserPassword($currentUser, $user);
        $requestedRole = User::normalizeRole($request->string('role')->toString());
        $willBeClientRole = $requestedRole === User::ROLE_CLIENT;
        $canManageClientNotes = $currentUser->isShadow() && $willBeClientRole;

        $role = $request->string('role')->toString();
        $department = $this->userDirectory->departmentForRole($role, $request->string('department')->toString());
        $persistedRole = $this->userDirectory->normalizeRoleForPersistence($role);
        $requestedIsProfileLocked = $request->boolean('is_profile_locked');
        $requestedUsername = $request->filled('username')
            ? $request->string('username')->toString()
            : (string) $user->username;

        if ($user->is_profile_locked) {
            $isChangingProfileFieldsWhileLocked = (
                $requestedUsername !== (string) ($user->username ?? '')
                || (string) $request->string('name')->toString() !== (string) $user->name
                || (string) $request->string('email')->toString() !== (string) $user->email
                || (string) $request->string('phone')->toString() !== (string) ($user->phone ?? '')
                || (string) $department !== (string) $user->department
                || (string) $persistedRole !== (string) $user->normalizedRole()
                || ($canManageClientNotes && (string) trim($request->string('client_notes')->toString()) !== (string) ($user->client_notes ?? ''))
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
            'username' => $requestedUsername,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $department,
            'role' => $persistedRole,
            'is_active' => $request->boolean('is_active'),
            'is_profile_locked' => $requestedIsProfileLocked,
        ];

        if ($persistedRole !== User::ROLE_CLIENT) {
            $updateData['client_notes'] = null;
        } elseif ($currentUser->isShadow()) {
            $updateData['client_notes'] = trim($request->string('client_notes')->toString()) ?: null;
        } else {
            $updateData['client_notes'] = $user->client_notes;
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
            $updateData['must_change_password'] = false;
            CredentialHandoff::query()
                ->where('target_user_id', $user->id)
                ->delete();
        }

        $user->fill($updateData);

        if (! $user->isDirty()) {
            return $this->redirectAfterManagedUserUpdate($user, $stayOnEdit, 'No changes were detected.', $returnTo, $hasReturnTo);
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

        return $this->redirectAfterManagedUserUpdate($user, $stayOnEdit, 'User updated successfully.', $returnTo, $hasReturnTo);
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
        if ($this->userDirectory->cannotManageTarget($currentUser, $user)) {
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

            Ticket::query()
                ->where(function ($query) use ($user) {
                    Ticket::applyAssignedToConstraint($query, (int) $user->id);
                })
                ->with('assignedUsers')
                ->get()
                ->each(function (Ticket $ticket) use ($user): void {
                    $remainingAssignedIds = $ticket->assignedUsers
                        ->reject(fn (User $assignedUser) => (int) $assignedUser->id === (int) $user->id)
                        ->pluck('id')
                        ->map(fn ($assignedUserId) => (int) $assignedUserId)
                        ->values()
                        ->all();

                    $ticket->assignedUsers()->sync($remainingAssignedIds);
                    $ticket->forceFill([
                        'assigned_to' => $remainingAssignedIds[0] ?? null,
                    ])->saveQuietly();
                });

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

        if ($this->userDirectory->cannotManageTarget($currentUser, $user)) {
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
                    'department' => User::supportDepartment(),
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
                'department' => User::supportDepartment(),
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

    private function redirectAfterManagedUserUpdate(User $user, bool $stayOnEdit, string $message, string $returnTo, bool $hasReturnTo)
    {
        if ($stayOnEdit) {
            $routeParameters = ['user' => $user];
            if ($hasReturnTo) {
                $routeParameters['return_to'] = $returnTo;
            }

            return redirect()->route('admin.users.edit', $routeParameters)
                ->with('success', $message);
        }

        return redirect()->to($returnTo)
            ->with('success', $message);
    }

    private function resolveManagedUserReturnUrl(mixed $candidate, User $targetUser): string
    {
        $default = $targetUser->isClient()
            ? route('admin.users.clients', absolute: false)
            : route('admin.users.index', absolute: false);

        if (! is_string($candidate)) {
            return $default;
        }

        $candidate = trim($candidate);
        if ($candidate === '') {
            return $default;
        }

        $parsed = parse_url($candidate);
        if ($parsed === false) {
            return $default;
        }

        if (
            isset($parsed['scheme'])
            || isset($parsed['host'])
            || isset($parsed['port'])
            || isset($parsed['user'])
            || isset($parsed['pass'])
        ) {
            return $default;
        }

        $path = '/'.ltrim((string) ($parsed['path'] ?? ''), '/');
        if (! in_array($path, ['/admin/users', '/admin/users/clients'], true)) {
            return $default;
        }

        $query = isset($parsed['query']) && $parsed['query'] !== ''
            ? '?'.$parsed['query']
            : '';

        return $path.$query;
    }
}
