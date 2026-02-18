<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index()
    {
        // Super admins can see all users including themselves
        // Regular admins see all users except themselves
        if (auth()->user()->isSuperAdmin()) {
            $users = User::orderBy('created_at', 'desc')->paginate(15);
        } else {
            $users = User::whereNot('id', auth()->id())
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        }

        return view('admin.users.index', compact('users'));
    }

    public function create()
    {
        $user = auth()->user();
        $availableRoles = [];

        if ($user->isSuperAdmin()) {
            $availableRoles = ['admin'];
        }

        return view('admin.users.create', compact('availableRoles'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $availableRoles = [];
        if ($user->isSuperAdmin()) {
            $availableRoles = ['admin'];
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'role' => ['required', Rule::in($availableRoles)],
            'password' => 'required|string|min:8|confirmed',
        ]);

        User::create([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $request->department,
            'role' => $request->role,
            'password' => Hash::make($request->password),
            'is_active' => true,
        ]);

        return redirect()->route('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(User $user)
    {
        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        // Only prevent regular admins from editing themselves, allow super admins
        if ($user->id === auth()->id() && !auth()->user()->isSuperAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot edit your own account.');
        }

        $currentUser = auth()->user();
        $availableRoles = ['client'];

        if ($currentUser->isSuperAdmin()) {
            $availableRoles[] = 'admin';
        }

        return view('admin.users.edit', compact('user', 'availableRoles'));
    }

    public function update(Request $request, User $user)
    {
        // Only prevent regular admins from editing themselves, allow super admins
        if ($user->id === auth()->id() && !auth()->user()->isSuperAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot edit your own account.');
        }

        $currentUser = auth()->user();
        $availableRoles = ['client'];

        if ($currentUser->isSuperAdmin()) {
            $availableRoles[] = 'admin';
        }

        $request->validate([
            'name' => 'required|string|max:255',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:20',
            'department' => 'nullable|string|max:255',
            'role' => ['required', Rule::in($availableRoles)],
            'password' => 'nullable|string|min:8|confirmed',
            'is_active' => 'boolean',
        ]);

        $updateData = [
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $request->department,
            'role' => $request->role,
            'is_active' => $request->boolean('is_active'),
        ];

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
        }

        $user->update($updateData);

        return redirect()->route('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(User $user)
    {
        $currentUser = auth()->user();

        // Users cannot delete themselves
        if ($user->id === $currentUser->id) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        // Super admin users cannot be deleted by anyone
        if ($user->isSuperAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'Super admin users cannot be deleted.');
        }

        // Regular admins can only delete clients
        if (!$currentUser->isSuperAdmin() && $user->role !== 'client') {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to delete this user.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function toggleStatus(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['error' => 'You cannot deactivate your own account.'], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json(['error' => 'Super admin users cannot be deactivated.'], 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $user->is_active,
            'message' => 'User status updated successfully.'
        ]);
    }
}
