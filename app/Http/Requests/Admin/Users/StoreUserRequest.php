<?php

namespace App\Http\Requests\Admin\Users;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check();
    }

    public function rules(): array
    {
        $user = auth()->user();
        $availableRoles = $this->availableRolesFor($user);
        $requestedRole = User::normalizeRole($this->string('role')->toString());
        $canManageClientNotes = $user->isShadow() && $requestedRole === User::ROLE_CLIENT;

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'department' => ['required', Rule::in(User::allowedDepartments())],
            'role' => ['required', Rule::in($availableRoles)],
            'client_notes' => $canManageClientNotes
                ? ['nullable', 'string', 'max:2000']
                : ['prohibited'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ];
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
}
