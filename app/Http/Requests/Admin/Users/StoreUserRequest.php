<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->user()?->can('create', User::class) ?? false;
    }

    public function rules(): array
    {
        $user = auth()->user();
        $availableRoles = $user->manageableUserRoleOptions();
        $requestedRole = User::normalizeRole($this->string('role')->toString());
        $canManageClientNotes = $user->isShadow() && $requestedRole === User::ROLE_CLIENT;

        return [
            'username' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9._-]+$/', 'unique:users,username'],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'unique:users,email',
                'max:255',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (User::emailUsesReservedSystemDomain((string) $value)) {
                        $fail('Email addresses ending in @system.local are reserved for system archive accounts.');
                    }
                },
            ],
            'phone' => ['required', 'string', 'max:20'],
            'department' => ['required', Rule::in(User::allowedDepartments())],
            'role' => ['required', Rule::in($availableRoles)],
            'client_notes' => $canManageClientNotes
                ? ['nullable', 'string', 'max:2000']
                : ['prohibited'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $rawName = trim((string) $this->input('name'));
        $username = trim(mb_strtolower((string) $this->input('username')));

        if ($username === '' && $rawName !== '') {
            $username = User::generateAvailableUsername($rawName);
        }

        $this->merge([
            'username' => $username !== '' ? $username : null,
            'name' => $rawName,
            'email' => trim((string) $this->input('email')),
            'phone' => trim((string) $this->input('phone')),
        ]);
    }
}
