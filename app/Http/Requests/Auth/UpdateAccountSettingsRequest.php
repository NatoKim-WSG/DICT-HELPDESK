<?php

namespace App\Http\Requests\Auth;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->check() && ! auth()->user()->isClient();
    }

    public function rules(): array
    {
        $user = auth()->user();
        $normalizedRole = $user->normalizedRole();
        $isSuperAdmin = in_array($normalizedRole, [User::ROLE_SHADOW, User::ROLE_ADMIN], true);
        $email = $isSuperAdmin
            ? $this->string('email')->toString()
            : (string) $user->email;
        $requiresCurrentPassword = $user->mustChangePassword()
            || ($isSuperAdmin && $email !== $user->email)
            || $this->filled('password');

        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => $isSuperAdmin
                ? ['required', 'email', Rule::unique('users', 'email')->ignore($user->id)]
                : ['nullable'],
            'phone' => ['nullable', 'string', 'max:20'],
            'department' => $isSuperAdmin
                ? ['required', Rule::in(User::allowedDepartments())]
                : ['nullable'],
            'password' => $user->mustChangePassword()
                ? ['required', 'string', 'min:8', 'confirmed']
                : ['nullable', 'string', 'min:8', 'confirmed'],
            'current_password' => $requiresCurrentPassword
                ? ['required', 'current_password']
                : ['nullable'],
        ];

        return $rules;
    }
}
