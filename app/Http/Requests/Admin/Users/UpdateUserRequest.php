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
        $canManageTarget = $currentUser->can('update', $targetUser);
        $availableRoles = $canManageTarget
            ? $currentUser->manageableUserRoleOptions($targetUser)
            : [User::ROLE_CLIENT, User::ROLE_SHADOW, User::ROLE_ADMIN, User::ROLE_SUPER_USER, User::ROLE_TECHNICAL];
        $canEditPassword = $currentUser->canEditManagedUserPassword($targetUser);
        $requestedRole = User::normalizeRole($this->string('role')->toString());
        $willBeClientRole = $requestedRole === User::ROLE_CLIENT;
        $canManageClientNotes = $currentUser->isShadow() && $willBeClientRole;

        $rules = [
            'username' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')->ignore($targetUser->id)],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('users', 'email')->ignore($targetUser->id),
                function (string $attribute, mixed $value, \Closure $fail) use ($canManageTarget): void {
                    if ($canManageTarget && User::emailUsesReservedSystemDomain((string) $value)) {
                        $fail('Email addresses ending in @system.local are reserved for system archive accounts.');
                    }
                },
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'department' => ['required', Rule::in(User::allowedDepartments())],
            'role' => ['required', Rule::in($availableRoles)],
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
}
