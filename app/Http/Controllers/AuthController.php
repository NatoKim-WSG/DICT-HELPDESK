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
            'login' => 'required|string',
            'password' => 'required',
        ]);

        $loginValue = $request->login;
        $fieldName = str_contains($loginValue, '@') ? 'email' : 'name';

        if (Auth::attempt(
            [$fieldName => $loginValue, 'password' => $request->password, 'is_active' => true],
            $request->filled('remember')
        )) {
            $request->session()->regenerate();

            return redirect()->intended($this->dashboardPath(Auth::user()));
        }

        $inactiveAccount = User::where($fieldName, $loginValue)
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

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'department' => $request->department,
        ];

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
        if ($user->canManageTickets()) {
            return '/admin/dashboard';
        }

        if ($user->canAccessAdminTickets()) {
            return '/admin/tickets';
        }

        return '/client/dashboard';
    }
}
