<?php

namespace App\Services\Admin;

use App\Models\User;

class ManagedUserAccessService
{
    public function __construct(
        private UserDirectoryService $userDirectory,
    ) {}

    public function showError(User $currentUser, User $targetUser): ?string
    {
        if ($this->isSystemReplacementUser($targetUser)) {
            return 'System archive users cannot be accessed from user management.';
        }

        if ($this->userDirectory->cannotManageTarget($currentUser, $targetUser)) {
            return 'You do not have permission to view this user.';
        }

        return null;
    }

    public function editError(User $currentUser, User $targetUser): ?string
    {
        if ($this->isSystemReplacementUser($targetUser)) {
            return 'System archive users cannot be accessed from user management.';
        }

        if ($targetUser->id === $currentUser->id) {
            return 'Use Account Settings to edit your own account.';
        }

        if ($this->userDirectory->cannotManageTarget($currentUser, $targetUser)) {
            return 'You do not have permission to edit this user.';
        }

        return null;
    }

    public function updateError(User $currentUser, User $targetUser): ?string
    {
        if ($this->isSystemReplacementUser($targetUser)) {
            return 'System archive users cannot be modified.';
        }

        if ($targetUser->id === $currentUser->id) {
            return 'Use Account Settings to edit your own account.';
        }

        return $this->userDirectory->cannotManageTarget($currentUser, $targetUser)
            ? 'You do not have permission to edit this user.'
            : null;
    }

    public function destroyError(User $currentUser, User $targetUser): ?string
    {
        if ($this->isSystemReplacementUser($targetUser)) {
            return 'System archive users cannot be deleted.';
        }

        if ($targetUser->id === $currentUser->id) {
            return 'You cannot delete your own account.';
        }

        if ($targetUser->is_profile_locked) {
            return 'Locked users cannot be deleted. Unlock the profile first.';
        }

        if ($targetUser->isShadow()) {
            if (! $currentUser->isShadow()) {
                return 'You do not have permission to delete this user.';
            }

            return 'This account cannot be deleted.';
        }

        if ($targetUser->normalizedRole() === User::ROLE_ADMIN && ! $currentUser->isShadow()) {
            return 'Admin users cannot be deleted.';
        }

        if ($this->userDirectory->cannotManageTarget($currentUser, $targetUser)) {
            return 'You do not have permission to delete this user.';
        }

        return null;
    }

    public function toggleStatusError(User $currentUser, User $targetUser): ?string
    {
        if ($this->isSystemReplacementUser($targetUser)) {
            return 'System archive users cannot be modified.';
        }

        if ($targetUser->id === $currentUser->id) {
            return 'You cannot deactivate your own account.';
        }

        if ($targetUser->isShadow()) {
            if (! $currentUser->isShadow()) {
                return 'You do not have permission to change this user status.';
            }

            return 'This account cannot be deactivated.';
        }

        if ($targetUser->normalizedRole() === User::ROLE_ADMIN && ! $currentUser->isShadow()) {
            return 'Admin users cannot be deactivated.';
        }

        if ($this->userDirectory->cannotManageTarget($currentUser, $targetUser)) {
            return 'You do not have permission to change this user status.';
        }

        return null;
    }

    public function credentialAccessBoundaryError(User $currentUser, User $targetUser): ?string
    {
        if ($this->isSystemReplacementUser($targetUser)) {
            return 'System archive users cannot be modified.';
        }

        if ($this->userDirectory->cannotManageTarget($currentUser, $targetUser)) {
            return 'You do not have permission to modify this user.';
        }

        return null;
    }

    public function resolveReturnUrl(mixed $candidate, User $targetUser): string
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

    private function isSystemReplacementUser(User $user): bool
    {
        return $user->isSystemReplacementAccount();
    }
}
