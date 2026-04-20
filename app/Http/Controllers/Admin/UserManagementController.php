<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Users\StoreUserRequest;
use App\Models\User;
use App\Services\Admin\UserDirectoryService;
use App\Services\SystemLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserManagementController extends Controller
{
    public function __construct(
        private SystemLogService $systemLogs,
        private UserDirectoryService $userDirectory,
    ) {}

    public function index(Request $request)
    {
        $this->authorize('viewAny', User::class);

        return $this->renderUserIndex($request, 'staff');
    }

    public function clients(Request $request)
    {
        $this->authorize('viewAny', User::class);

        return $this->renderUserIndex($request, 'clients');
    }

    public function create()
    {
        $this->authorize('create', User::class);

        $availableRoles = auth()->user()->manageableUserRoleOptions();

        return view('admin.users.create', compact('availableRoles'));
    }

    public function store(StoreUserRequest $request)
    {
        $this->authorize('create', User::class);

        $user = auth()->user();

        $role = $request->string('role')->toString();
        $department = $this->userDirectory->departmentForRole($role, $request->string('department')->toString());
        $persistedRole = $this->userDirectory->normalizeRoleForPersistence($role);

        $createdUser = User::create([
            'username' => $request->string('username')->toString() ?: null,
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'department' => $department,
            'client_notes' => ($persistedRole === User::ROLE_CLIENT && $user->isShadow())
                ? (trim($request->string('client_notes')->toString()) ?: null)
                : null,
            'role' => $persistedRole,
            'password' => Hash::make((string) $request->password),
            'is_active' => true,
            'is_profile_locked' => false,
            'must_change_password' => false,
        ]);
        $this->systemLogs->record(
            'user.created',
            'Created a user account.',
            [
                'category' => 'user',
                'target_type' => User::class,
                'target_id' => $createdUser->id,
                'metadata' => [
                    'role' => User::normalizeRole($createdUser->role),
                    'department' => $createdUser->department,
                    'is_active' => (bool) $createdUser->is_active,
                ],
                'request' => $request,
            ]
        );

        $redirectRoute = $persistedRole === User::ROLE_CLIENT
            ? 'admin.users.clients'
            : 'admin.users.index';
        $searchTerm = $createdUser->username ?: $createdUser->name;

        return redirect()->route($redirectRoute, [
            'search' => $searchTerm,
        ])
            ->with('success', 'User created successfully.');
    }

    private function renderUserIndex(Request $request, string $segment)
    {
        $currentUser = auth()->user();

        return view('admin.users.index', $this->userDirectory->buildIndexViewData($request, $currentUser, $segment));
    }
}
