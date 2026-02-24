<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect($this->dashboardPath(Auth::user()));
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string|max:255',
            'password' => 'required',
            'remember' => 'nullable|boolean',
        ]);

        $loginInput = trim($request->string('login')->toString());
        $remember = $request->boolean('remember');
        $isEmailLogin = filter_var($loginInput, FILTER_VALIDATE_EMAIL) !== false;

        if ($isEmailLogin) {
            // Email login remains case-insensitive.
            $matchedUsers = User::whereRaw('LOWER(email) = ?', [strtolower($loginInput)])
                ->get();
        } else {
            // Username login is case-sensitive.
            $matchedUsers = User::whereRaw('LOWER(name) = ?', [strtolower($loginInput)])
                ->get()
                ->filter(fn (User $user) => $user->name === $loginInput)
                ->values();
        }

        $activeUser = $matchedUsers->first(function (User $user) use ($request) {
            return $user->is_active && Hash::check($request->password, $user->password);
        });

        if ($activeUser) {
            Auth::login($activeUser, $remember);
            $request->session()->regenerate();

            return redirect()->intended($this->dashboardPath($activeUser));
        }

        $inactiveMatch = $matchedUsers->first(function (User $user) use ($request) {
            return !$user->is_active && Hash::check($request->password, $user->password);
        });

        if ($inactiveMatch) {
            return back()->withErrors([
                'login' => 'Your account is inactive. Please contact an administrator.',
            ])->onlyInput('login');
        }

        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function accountSettings()
    {
        $user = Auth::user();
        $departmentOptions = $this->allowedDepartments();

        return view('account.settings', compact('user', 'departmentOptions'));
    }

    public function updateAccountSettings(Request $request)
    {
        $user = Auth::user();
        $normalizedRole = $user->normalizedRole();
        $isClient = $user->isClient();
        $isTechnical = $normalizedRole === User::ROLE_TECHNICAL;
        $isDepartmentLocked = $isClient || $isTechnical;
        $isSuperDepartmentRole = in_array($normalizedRole, [User::ROLE_SUPER_USER, User::ROLE_SUPER_ADMIN], true);

        $departmentRules = ['nullable', 'string', 'max:255'];
        if ($isDepartmentLocked) {
            $departmentRules = ['nullable'];
        } elseif ($isSuperDepartmentRole) {
            $departmentRules = ['required', Rule::in($this->allowedDepartments())];
        }

        $email = $request->string('email')->toString();
        $requiresCurrentPassword = $email !== $user->email || $request->filled('password');

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'department' => $departmentRules,
            'password' => 'nullable|string|min:8|confirmed',
        ];

        if ($requiresCurrentPassword) {
            $rules['current_password'] = ['required', 'current_password'];
        } else {
            $rules['current_password'] = ['nullable'];
        }

        $request->validate($rules);

        $updateData = [
            'name' => $request->name,
            'email' => $email,
        ];

        // Department for clients/technical users is managed from User Management.
        if (!$isDepartmentLocked) {
            $updateData['department'] = $request->department;
        }

        if ($request->has('phone')) {
            $updateData['phone'] = $request->phone;
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return redirect()->route('account.settings')
            ->with('success', 'Account settings updated successfully.');
    }

    private function allowedDepartments(): array
    {
        return ['iOne', 'DEPED', 'DICT', 'DAR'];
    }

    private function dashboardPath(User $user): string
    {
        if ($user->canAccessAdminTickets()) {
            return '/admin/dashboard';
        }

        return '/client/dashboard';
    }
}
