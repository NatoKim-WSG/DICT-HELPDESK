<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

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
            'login' => 'required|email',
            'password' => 'required',
            'remember' => 'nullable|boolean',
        ]);

        $loginEmail = $request->string('login')->toString();

        if (Auth::attempt(
            ['email' => $loginEmail, 'password' => $request->password, 'is_active' => true],
            $request->boolean('remember')
        )) {
            $request->session()->regenerate();

            return redirect()->intended($this->dashboardPath(Auth::user()));
        }

        $inactiveAccount = User::where('email', $loginEmail)
            ->where('is_active', false)
            ->exists();

        if ($inactiveAccount) {
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
        return view('account.settings', compact('user'));
    }

    public function updateAccountSettings(Request $request)
    {
        $user = Auth::user();
        $isClient = $user->isClient();

        $email = $request->string('email')->toString();
        $requiresCurrentPassword = $email !== $user->email || $request->filled('password');

        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'department' => $isClient ? 'nullable' : 'nullable|string|max:255',
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

        // Client department is managed only by admins from User Management.
        if (!$isClient) {
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

    private function dashboardPath(User $user): string
    {
        if ($user->canAccessAdminTickets()) {
            return '/admin/dashboard';
        }

        return '/client/dashboard';
    }
}
