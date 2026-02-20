<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class UserManagementController extends Controller
{
    public function index(Request $request)
    {
        $currentUser = auth()->user();
        $query = User::query();

        if (!$currentUser->isSuperAdmin()) {
            $query->where('id', '!=', $currentUser->id)
                ->whereIn('role', $this->manageableRolesForAdmin());
        }

        if ($request->filled('role') && $request->role !== 'all') {
            $query->where('role', $request->role);
        }

        if ($request->filled('department') && $request->department !== 'all') {
            $query->where('department', $request->department);
        }

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('is_active', $request->status === 'active');
        }

        $users = $query
            ->orderByRaw("
                CASE role
                    WHEN 'super_admin' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'technician' THEN 3
                    WHEN 'client' THEN 4
                    ELSE 5
                END
            ")
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $departments = User::query()
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->orderBy('department')
            ->pluck('department');

        $availableRolesFilter = $currentUser->isSuperAdmin()
            ? [User::ROLE_SUPER_ADMIN, User::ROLE_ADMIN, User::ROLE_TECHNICIAN, User::ROLE_CLIENT]
            : [User::ROLE_TECHNICIAN, User::ROLE_CLIENT];

        return view('admin.users.index', compact('users', 'departments', 'availableRolesFilter'));
    }

    public function create()
    {
        $availableRoles = $this->availableRolesFor(auth()->user());

        return view('admin.users.create', compact('availableRoles'));
    }

    public function store(Request $request)
    {
        $user = auth()->user();

        $availableRoles = $this->availableRolesFor($user);

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
        $currentUser = auth()->user();

        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to view this user.');
        }

        return view('admin.users.show', compact('user'));
    }

    public function edit(User $user)
    {
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id && !$currentUser->isSuperAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot edit your own account.');
        }

        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to edit this user.');
        }

        $availableRoles = $this->availableRolesFor($currentUser);

        return view('admin.users.edit', compact('user', 'availableRoles'));
    }

    public function update(Request $request, User $user)
    {
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id && !$currentUser->isSuperAdmin()) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You cannot edit your own account.');
        }

        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to edit this user.');
        }

        $availableRoles = $this->availableRolesFor($currentUser);

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
        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return redirect()->route('admin.users.index')
                ->with('error', 'You do not have permission to delete this user.');
        }

        $user->delete();

        return redirect()->route('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function toggleStatus(User $user)
    {
        $currentUser = auth()->user();

        if ($user->id === $currentUser->id) {
            return response()->json(['error' => 'You cannot deactivate your own account.'], 403);
        }

        if ($user->isSuperAdmin()) {
            return response()->json(['error' => 'Super admin users cannot be deactivated.'], 403);
        }

        if (!$currentUser->isSuperAdmin() && !in_array($user->role, $this->manageableRolesForAdmin(), true)) {
            return response()->json(['error' => 'You do not have permission to change this user status.'], 403);
        }

        $user->update(['is_active' => !$user->is_active]);

        return response()->json([
            'success' => true,
            'is_active' => $user->is_active,
            'message' => 'User status updated successfully.'
        ]);
    }

    private function availableRolesFor(User $currentUser): array
    {
        $roles = [User::ROLE_CLIENT, User::ROLE_TECHNICIAN];

        if ($currentUser->isSuperAdmin()) {
            $roles[] = User::ROLE_ADMIN;
        }

        return $roles;
    }

    private function manageableRolesForAdmin(): array
    {
        return [User::ROLE_CLIENT, User::ROLE_TECHNICIAN];
    }
}
