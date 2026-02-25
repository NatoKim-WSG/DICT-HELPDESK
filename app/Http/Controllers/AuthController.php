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
            return ! $user->is_active && Hash::check($request->password, $user->password);
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
        if ($user->isClient()) {
            return redirect()->route('client.dashboard')
                ->with('error', 'Client accounts cannot access account settings.');
        }

        $departmentOptions = User::allowedDepartments();

        return view('account.settings', compact('user', 'departmentOptions'));
    }

    public function updateAccountSettings(Request $request)
    {
        $user = Auth::user();
        if ($user->isClient()) {
            return redirect()->route('client.dashboard')
                ->with('error', 'Client accounts cannot access account settings.');
        }

        $normalizedRole = $user->normalizedRole();
        $isSuperAdmin = in_array($normalizedRole, [User::ROLE_SHADOW, User::ROLE_ADMIN], true);
        $isUsernameLocked = in_array($normalizedRole, [User::ROLE_SUPER_USER, User::ROLE_TECHNICAL], true);
        $departmentRules = $isSuperAdmin
            ? ['required', Rule::in(User::allowedDepartments())]
            : ['nullable'];
        $email = $isSuperAdmin
            ? $request->string('email')->toString()
            : (string) $user->email;
        $requiresCurrentPassword = ($isSuperAdmin && $email !== $user->email) || $request->filled('password');

        $rules = [
            'name' => 'required|string|max:255',
            'email' => $isSuperAdmin
                ? 'required|email|unique:users,email,'.$user->id
                : 'nullable',
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
            'name' => $isUsernameLocked
                ? (string) $user->name
                : $request->string('name')->toString(),
            'phone' => $request->string('phone')->toString(),
        ];

        if ($isSuperAdmin) {
            $updateData['email'] = $email;
            $updateData['department'] = $request->string('department')->toString();
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->fill($updateData);

        if (! $user->isDirty()) {
            return redirect()->route('account.settings')
                ->with('success', 'No changes were detected.');
        }

        $user->save();

        return redirect()->route('account.settings')
            ->with('success', 'Account settings updated successfully.');
    }

    private function dashboardPath(User $user): string
    {
        if ($user->canAccessAdminTickets()) {
            return '/admin/dashboard';
        }

        return '/client/dashboard';
    }
}

