<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $currentUser = auth()->user();
        /** @var User $targetUser */
        $targetUser = $this->route('user');
        $canEditPassword = $this->canEditManagedUserPassword($currentUser, $targetUser);
        $requestedRole = User::normalizeRole($this->string('role')->toString());
        $willBeClientRole = $requestedRole === User::ROLE_CLIENT;
        $canManageClientNotes = $currentUser->isShadow() && $willBeClientRole;

        $rules = [
            'username' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($targetUser->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', Rule::unique('users', 'email')->ignore($targetUser->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'department' => ['required', Rule::in(User::allowedDepartments())],
            'role' => ['required', Rule::in([User::ROLE_CLIENT, User::ROLE_SHADOW, User::ROLE_ADMIN, User::ROLE_SUPER_USER, User::ROLE_TECHNICAL])],
            'client_notes' => $canManageClientNotes
                ? ['nullable', 'string', 'max:2000']
                : ['prohibited'],
            'password' => $canEditPassword
                ? ['nullable', 'string', 'min:8', 'confirmed']
                : ['prohibited'],
            'is_active' => ['boolean'],
            'is_profile_locked' => ['boolean'],
        ];

        if (! $canEditPassword) {
            $rules['password_confirmation'] = ['prohibited'];
        }

        return $rules;
    }

    protected function prepareForValidation(): void
    {
        /** @var User|null $targetUser */
        $targetUser = $this->route('user');
        $rawName = trim((string) $this->input('name'));
        $username = trim(mb_strtolower((string) $this->input('username')));

        if ($username === '' && $targetUser instanceof User) {
            $username = trim(mb_strtolower((string) $targetUser->username));
        }

        if ($username === '' && $rawName !== '') {
            $username = User::generateAvailableUsername($rawName, $targetUser?->id);
        }

        $this->merge([
            'username' => $username !== '' ? $username : null,
            'name' => $rawName,
            'email' => trim((string) $this->input('email')),
            'phone' => trim((string) $this->input('phone')),
        ]);
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
}
