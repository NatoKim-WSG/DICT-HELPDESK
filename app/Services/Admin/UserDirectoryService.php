<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

class UserDirectoryService
{
    public function buildIndexViewData(Request $request, User $currentUser, string $segment): array
    {
        if (! $this->canManageStaffAccounts($currentUser)) {
            $segment = 'clients';
        }

        $query = User::query()->where('email', 'not like', '%'.User::SYSTEM_RESERVED_EMAIL_DOMAIN);
        $departmentsQuery = User::query()->where('email', 'not like', '%'.User::SYSTEM_RESERVED_EMAIL_DOMAIN);

        $this->applyVisibilityScope($query, $currentUser);
        $this->applyVisibilityScope($departmentsQuery, $currentUser);
        $this->applySegmentScope($query, $segment, $currentUser);
        $this->applySegmentScope($departmentsQuery, $segment, $currentUser);

        if ($request->filled('role') && $request->role !== 'all') {
            $requestedRole = User::normalizeRole($request->string('role')->toString());

            if ($requestedRole === User::ROLE_ADMIN) {
                $query->whereIn('role', [
                    User::ROLE_SHADOW,
                    User::ROLE_ADMIN,
                ]);
            } elseif ($requestedRole === User::ROLE_TECHNICAL) {
                $query->where('role', User::ROLE_TECHNICAL);
            } else {
                $query->where('role', $requestedRole);
            }
        }

        if ($request->filled('search')) {
            $search = trim((string) $request->search);
            $normalizedSearch = mb_strtolower($search);

            $query->where(function (Builder $builder) use ($normalizedSearch) {
                $builder->whereRaw('LOWER(COALESCE(username, \'\')) LIKE ?', ["%{$normalizedSearch}%"])
                    ->orWhereRaw('LOWER(COALESCE(name, \'\')) LIKE ?', ["%{$normalizedSearch}%"])
                    ->orWhereRaw('LOWER(COALESCE(email, \'\')) LIKE ?', ["%{$normalizedSearch}%"])
                    ->orWhereRaw('LOWER(COALESCE(phone, \'\')) LIKE ?', ["%{$normalizedSearch}%"])
                    ->orWhereRaw('LOWER(COALESCE(department, \'\')) LIKE ?', ["%{$normalizedSearch}%"]);
            });
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
                    WHEN 'shadow' THEN 1
                    WHEN 'admin' THEN 2
                    WHEN 'super_user' THEN 3
                    WHEN 'technical' THEN 4
                    WHEN 'client' THEN 5
                    ELSE 6
                END
            ")
            ->orderByRaw("LOWER(COALESCE(name, ''))")
            ->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString();

        $departments = $departmentsQuery
            ->whereNotNull('department')
            ->where('department', '!=', '')
            ->distinct()
            ->pluck('department')
            ->sort(fn (string $left, string $right) => strnatcasecmp($left, $right))
            ->values();

        return [
            'users' => $users,
            'departments' => $departments,
            'availableRolesFilter' => $this->availableRolesFilterForSegment($segment, $currentUser),
            'segment' => $segment,
            'segmentTitle' => $segment === 'clients' ? 'Client Accounts' : 'Staff Accounts',
        ];
    }

    public function departmentForRole(string $role, string $department): string
    {
        return User::managedDepartmentForRole($role, $department);
    }

    public function normalizeRoleForPersistence(string $role): string
    {
        return User::normalizeRole($role);
    }

    public function buildUserTicketStatistics(User $user): array
    {
        $isClient = $user->normalizedRole() === User::ROLE_CLIENT;
        $relatedTickets = $isClient
            ? Ticket::query()->where('user_id', $user->id)
            : Ticket::query()->where(function (Builder $query) use ($user) {
                $query->where('user_id', $user->id)
                    ->orWhere(function (Builder $assignmentQuery) use ($user) {
                        Ticket::applyAssignedToConstraint($assignmentQuery, (int) $user->id);
                    });
            });
        $assignedTickets = $isClient
            ? null
            : Ticket::applyAssignedToConstraint(Ticket::query(), (int) $user->id);

        return [
            'total_tickets' => (clone $relatedTickets)->count(),
            'open_tickets' => (clone $relatedTickets)->whereIn('status', Ticket::OPEN_STATUSES)->count(),
            'closed_tickets' => (clone $relatedTickets)->whereIn('status', Ticket::CLOSED_STATUSES)->count(),
            'assigned_tickets' => $assignedTickets ? (clone $assignedTickets)->count() : null,
            'show_assigned' => ! $isClient,
        ];
    }

    public function buildUserStatisticsLinks(User $user, bool $showAssigned): array
    {
        $ticketFilter = $user->normalizedRole() === User::ROLE_CLIENT
            ? ['account_id' => $user->id]
            : ['related_user_id' => $user->id];

        return [
            'total_tickets' => route('admin.tickets.index', array_merge($ticketFilter, ['tab' => 'all'])),
            'open_tickets' => route('admin.tickets.index', array_merge($ticketFilter, ['tab' => 'tickets'])),
            'closed_tickets' => route('admin.tickets.index', array_merge($ticketFilter, ['tab' => 'history'])),
            'assigned_tickets' => $showAssigned
                ? route('admin.tickets.index', ['tab' => 'tickets', 'assigned_to' => $user->id])
                : null,
        ];
    }

    public function recentTicketsForUser(User $user): Collection
    {
        $normalizedRole = $user->normalizedRole();

        return Ticket::query()
            ->where(function (Builder $query) use ($user, $normalizedRole) {
                $query->where('user_id', $user->id);

                if ($normalizedRole !== User::ROLE_CLIENT) {
                    $query->orWhere(function (Builder $assignmentQuery) use ($user) {
                        Ticket::applyAssignedToConstraint($assignmentQuery, (int) $user->id);
                    });
                }
            })
            ->latest()
            ->take(5)
            ->get();
    }

    public function canManageStaffAccounts(User $currentUser): bool
    {
        return $currentUser->canManageStaffAccounts();
    }

    public function cannotManageTarget(User $currentUser, User $targetUser): bool
    {
        return Gate::forUser($currentUser)->denies('view', $targetUser);
    }

    private function manageableRolesForAdmin(): array
    {
        return User::USER_MANAGEMENT_CLIENT_ONLY_ROLES;
    }

    private function applyVisibilityScope(Builder $query, User $currentUser): void
    {
        if ($currentUser->isShadow()) {
            return;
        }

        if ($currentUser->normalizedRole() === User::ROLE_ADMIN) {
            $query->where('role', '!=', User::ROLE_SHADOW);

            return;
        }

        if (! $currentUser->isSuperAdmin()) {
            $query->where('id', '!=', $currentUser->id)
                ->whereIn('role', $this->manageableRolesForAdmin());
        }
    }

    private function applySegmentScope(Builder $query, string $segment, User $currentUser): void
    {
        if ($segment === 'clients' || ! $this->canManageStaffAccounts($currentUser)) {
            $query->where('role', User::ROLE_CLIENT);

            return;
        }

        $query->whereIn('role', [
            User::ROLE_SHADOW,
            User::ROLE_ADMIN,
            User::ROLE_SUPER_USER,
            User::ROLE_TECHNICAL,
        ]);

        if ($currentUser->normalizedRole() === User::ROLE_ADMIN) {
            $query->where('role', '!=', User::ROLE_SHADOW);
        }
    }

    private function availableRolesFilterForSegment(string $segment, User $currentUser): array
    {
        if ($segment === 'clients') {
            return [User::ROLE_CLIENT];
        }

        if ($this->canManageStaffAccounts($currentUser)) {
            return [
                User::ROLE_ADMIN,
                User::ROLE_SUPER_USER,
                User::ROLE_TECHNICAL,
            ];
        }

        return [User::ROLE_CLIENT];
    }
}
