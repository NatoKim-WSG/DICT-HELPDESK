<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TicketIndexService
{
    public function resolveActiveTab(string $requestedTab): string
    {
        return in_array($requestedTab, ['all', 'tickets', 'attention', 'history'], true)
            ? $requestedTab
            : 'tickets';
    }

    public function resolveSelectedStatus(string $requestedStatus, string $activeTab): string
    {
        $selectedStatus = trim($requestedStatus);
        if ($selectedStatus === '') {
            $selectedStatus = 'all';
        }

        $allowedStatuses = $this->allowedStatusesForTab($activeTab);

        return in_array($selectedStatus, $allowedStatuses, true)
            ? $selectedStatus
            : 'all';
    }

    public function resolveCreatedDateRange(Request $request): ?array
    {
        $selectedMonth = $this->parseMonthKey($request->query('month'));
        if ($selectedMonth !== null) {
            $monthStart = $selectedMonth->copy()->startOfMonth();
            $monthEnd = $selectedMonth->copy()->endOfMonth();

            return [
                'start' => $monthStart,
                'end' => $monthEnd,
                'from' => $monthStart->toDateString(),
                'to' => $monthEnd->toDateString(),
                'label' => $monthStart->format('F Y'),
                'month' => $monthStart->format('Y-m'),
            ];
        }

        $fromDate = $this->parseCreatedDate($request->query('created_from'));
        $toDate = $this->parseCreatedDate($request->query('created_to'));
        if (! $fromDate && ! $toDate) {
            return null;
        }

        $startDate = $fromDate ?? $toDate;
        $endDate = $toDate ?? $fromDate;
        if (! $startDate || ! $endDate) {
            return null;
        }

        if ($startDate->gt($endDate)) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return [
            'start' => $startDate->copy()->startOfDay(),
            'end' => $endDate->copy()->endOfDay(),
            'from' => $startDate->toDateString(),
            'to' => $endDate->toDateString(),
            'label' => trim((string) $request->query('report_scope')),
            'month' => null,
        ];
    }

    public function scopedTicketQueryFor(?User $user): Builder
    {
        $query = Ticket::query();

        if ($user && $user->isTechnician()) {
            Ticket::applyAssignedToConstraint($query, (int) $user->id);
        }

        return $query;
    }

    public function applyTabScope(Builder $query, string $activeTab): void
    {
        if ($activeTab === 'all') {
            return;
        }

        if ($activeTab === 'history') {
            $query->whereIn('status', Ticket::CLOSED_STATUSES);

            return;
        }

        if ($activeTab === 'attention') {
            $query->whereNotIn('status', Ticket::CLOSED_STATUSES)
                ->where('created_at', '<=', now()->subHours(16));

            return;
        }

        $query->whereIn('status', Ticket::OPEN_STATUSES);
    }

    public function applyFilters(
        Builder $query,
        Request $request,
        string $selectedStatus,
        ?array $createdDateRange = null,
    ): void {
        $this->applyStatusFilter($query, $selectedStatus);
        $this->applyPriorityFilter($query, $request);
        $this->applyCategoryFilters($query, $request);
        $this->applyLocationFilters($query, $request);
        $this->applyAccountFilters($query, $request);
        $this->applyAssignmentFilter($query, $request);
        $this->applySearchFilter($query, $request);
        $this->applyCreatedDateRangeFilter($query, $createdDateRange);
    }

    public function buildTicketListSnapshotToken(Builder $query): string
    {
        $qualifiedUpdatedAtColumn = $query->getModel()->qualifyColumn('updated_at');
        $snapshot = (clone $query)
            ->toBase()
            ->selectRaw("COUNT(*) as total_tickets, MAX({$qualifiedUpdatedAtColumn}) as latest_updated_at")
            ->first();
        $latestUpdatedTimestamp = $snapshot?->latest_updated_at
            ? strtotime((string) $snapshot->latest_updated_at)
            : 0;
        $totalTickets = (int) ($snapshot->total_tickets ?? 0);

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'total_tickets' => $totalTickets,
        ]));
    }

    public function buildTicketListPageSnapshotToken(Builder $orderedQuery, int $page, int $perPage): string
    {
        $idColumn = $orderedQuery->getModel()->qualifyColumn('id');
        $updatedAtColumn = $orderedQuery->getModel()->qualifyColumn('updated_at');
        $pageRows = (clone $orderedQuery)
            ->forPage($page, $perPage)
            ->get([$idColumn, $updatedAtColumn]);

        return $this->buildTicketListPageSnapshotTokenForTickets($pageRows);
    }

    public function buildTicketListPageSnapshotTokenForTickets(LengthAwarePaginator|Collection $tickets): string
    {
        $ticketItems = $tickets instanceof LengthAwarePaginator
            ? $tickets->getCollection()
            : $tickets;

        return sha1(json_encode(
            $ticketItems
                ->map(fn (Ticket $ticket): array => [
                    'id' => (int) $ticket->id,
                    'updated_at' => optional($ticket->updated_at)->getTimestamp() ?? 0,
                ])
                ->values()
                ->all()
        ));
    }

    public function distinctTicketColumnOptions(string $column, ?Builder $scopedBaseQuery = null): Collection
    {
        $this->assertSupportedLocationColumn($column);

        $query = $scopedBaseQuery ? clone $scopedBaseQuery : Ticket::query();

        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values();
    }

    public function accountOptionsFor(?User $currentUser, Builder $scopedTickets): Collection
    {
        if ($currentUser && $currentUser->isTechnician()) {
            $visibleClientIds = (clone $scopedTickets)
                ->whereNotNull('user_id')
                ->select('user_id')
                ->distinct()
                ->pluck('user_id');

            return User::where('role', User::ROLE_CLIENT)
                ->where('is_active', true)
                ->whereIn('id', $visibleClientIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values();
        }

        return Cache::remember('admin_ticket_account_options_active_clients_v1', now()->addSeconds(60), function () {
            return User::where('role', User::ROLE_CLIENT)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values();
        });
    }

    public function monthOptionsFor(Builder $scopedTickets): Collection
    {
        $monthKeyExpression = $this->monthKeyExpression('created_at', $scopedTickets);

        return (clone $scopedTickets)
            ->whereNotNull('created_at')
            ->selectRaw("{$monthKeyExpression} as month_key")
            ->distinct()
            ->orderByDesc('month_key')
            ->pluck('month_key')
            ->map(function (mixed $monthKey): array {
                $month = Carbon::createFromFormat('Y-m', (string) $monthKey)->startOfMonth();

                return [
                    'value' => $month->format('Y-m'),
                    'label' => $month->format('F Y'),
                ];
            })
            ->unique('value')
            ->values();
    }

    public function activeAssignableAgents(): Collection
    {
        return Cache::remember('admin_ticket_active_agents_v2', now()->addSeconds(45), function () {
            return User::whereIn('role', User::TICKET_CONSOLE_ROLES)
                ->visibleDirectory()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role']);
        });
    }

    private function allowedStatusesForTab(string $activeTab): array
    {
        return $activeTab === 'history'
            ? array_merge(['all'], Ticket::CLOSED_STATUSES)
            : ($activeTab === 'all'
                ? array_merge(['all'], Ticket::OPEN_STATUSES, Ticket::CLOSED_STATUSES)
                : array_merge(['all'], Ticket::OPEN_STATUSES));
    }

    private function applyStatusFilter(Builder $query, string $selectedStatus): void
    {
        if ($selectedStatus === 'all') {
            return;
        }

        $query->where('status', $selectedStatus);
    }

    private function applyPriorityFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('priority') || $request->priority === 'all') {
            return;
        }

        $priority = $request->string('priority')->toString();

        if ($priority === 'unassigned') {
            $query->whereNull('priority');

            return;
        }

        $normalizedPriority = Ticket::normalizePriorityValue($priority);
        if ($normalizedPriority === null) {
            return;
        }

        $query->where('priority', $normalizedPriority);
    }

    private function applyCategoryFilters(Builder $query, Request $request): void
    {
        if ($request->filled('category') && $request->category !== 'all') {
            $query->where('category_id', $request->integer('category'));
        }

        if (! $request->filled('category_bucket') || $request->category_bucket === 'all') {
            return;
        }

        $bucket = $this->normalizeCategoryBucketFilter($request->string('category_bucket')->toString());
        if ($bucket === null) {
            return;
        }

        $this->applyCategoryBucketFilter($query, $bucket);
    }

    private function applyLocationFilters(Builder $query, Request $request): void
    {
        if ($request->filled('province') && $request->province !== 'all') {
            $this->applyCaseInsensitiveExactMatch($query, 'province', (string) $request->province);
        }

        if ($request->filled('municipality') && $request->municipality !== 'all') {
            $this->applyCaseInsensitiveExactMatch($query, 'municipality', (string) $request->municipality);
        }
    }

    private function applyAccountFilters(Builder $query, Request $request): void
    {
        if ($request->filled('account_id') && $request->account_id !== 'all') {
            $query->where('user_id', $request->integer('account_id'));
        }

        if (! $request->filled('related_user_id') || $request->related_user_id === 'all') {
            return;
        }

        $relatedUserId = $request->integer('related_user_id');

        $query->where(function (Builder $builder) use ($relatedUserId) {
            $builder->where('user_id', $relatedUserId)
                ->orWhere(function (Builder $assignmentQuery) use ($relatedUserId) {
                    Ticket::applyAssignedToConstraint($assignmentQuery, $relatedUserId);
                });
        });
    }

    private function applyAssignmentFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('assigned_to') || $request->assigned_to === 'all') {
            return;
        }

        if ((string) $request->assigned_to === '0') {
            $query->whereDoesntHave('assignedUsers');

            return;
        }

        Ticket::applyAssignedToConstraint($query, $request->integer('assigned_to'));
    }

    private function applySearchFilter(Builder $query, Request $request): void
    {
        if (! $request->filled('search')) {
            return;
        }

        $search = mb_strtolower($request->string('search')->toString());
        $pattern = '%'.$search.'%';

        $query->where(function (Builder $searchQuery) use ($pattern) {
            $searchQuery->whereRaw('LOWER(subject) LIKE ?', [$pattern])
                ->orWhereRaw('LOWER(ticket_number) LIKE ?', [$pattern])
                ->orWhereHas('user', function (Builder $userQuery) use ($pattern) {
                    $userQuery->whereRaw('LOWER(name) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
                });
        });
    }

    private function applyCreatedDateRangeFilter(Builder $query, ?array $createdDateRange): void
    {
        if ($createdDateRange === null) {
            return;
        }

        $query->whereBetween('created_at', [
            $createdDateRange['start'],
            $createdDateRange['end'],
        ]);
    }

    private function parseCreatedDate(mixed $rawDate): ?Carbon
    {
        if (! is_string($rawDate)) {
            return null;
        }

        $normalized = trim($rawDate);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $normalized)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseMonthKey(mixed $rawMonth): ?Carbon
    {
        if (! is_string($rawMonth)) {
            return null;
        }

        $normalized = trim($rawMonth);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m', $normalized)->startOfMonth();
        } catch (\Throwable) {
            return null;
        }
    }

    private function monthKeyExpression(string $column, Builder $query): string
    {
        /** @var Connection $connection */
        $connection = $query->getQuery()->getConnection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private function applyCaseInsensitiveExactMatch(Builder $query, string $column, string $value): void
    {
        $this->assertSupportedLocationColumn($column);

        $normalizedValue = strtolower(trim($value));
        $query->whereRaw("LOWER(COALESCE({$column}, '')) = ?", [$normalizedValue]);
    }

    private function assertSupportedLocationColumn(string $column): void
    {
        if (! in_array($column, ['province', 'municipality'], true)) {
            throw new \InvalidArgumentException('Unsupported ticket location column.');
        }
    }

    private function normalizeCategoryBucketFilter(string $raw): ?string
    {
        $normalized = strtolower(trim($raw));
        if ($normalized === '') {
            return null;
        }

        return match ($normalized) {
            'hardware' => 'hardware',
            'software' => 'software',
            'network' => 'network',
            'access_permissions', 'access/permissions', 'access / permissions' => 'access_permissions',
            'security' => 'security',
            'other' => 'other',
            default => null,
        };
    }

    private function applyCategoryBucketFilter(Builder $query, string $bucket): void
    {
        $patterns = $this->categoryBucketLikePatterns();

        $query->where(function (Builder $builder) use (
            $bucket,
            $patterns
        ) {
            if ($bucket === 'other') {
                $builder->whereNull('category_id')
                    ->orWhereHas('category', function (Builder $categoryQuery) use (
                        $patterns
                    ) {
                        $categoryQuery
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$patterns['hardware']])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$patterns['software']])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$patterns['application']])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$patterns['network']])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$patterns['connect']])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$patterns['access']])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$patterns['permission']])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$patterns['account']])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$patterns['security']]);
                    });

                return;
            }

            $builder->whereHas('category', function (Builder $categoryQuery) use (
                $bucket,
                $patterns
            ) {
                match ($bucket) {
                    'hardware' => $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$patterns['hardware']]),
                    'software' => $categoryQuery->where(function (Builder $query) use ($patterns) {
                        $query->whereRaw('LOWER(name) LIKE ?', [$patterns['software']])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$patterns['application']]);
                    }),
                    'network' => $categoryQuery->where(function (Builder $query) use ($patterns) {
                        $query->whereRaw('LOWER(name) LIKE ?', [$patterns['network']])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$patterns['connect']]);
                    }),
                    'access_permissions' => $categoryQuery->where(function (Builder $query) use ($patterns) {
                        $query->whereRaw('LOWER(name) LIKE ?', [$patterns['access']])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$patterns['permission']])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$patterns['account']]);
                    }),
                    'security' => $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$patterns['security']]),
                    default => null,
                };
            });
        });
    }

    private function categoryBucketLikePatterns(): array
    {
        return [
            'hardware' => '%hardware%',
            'software' => '%software%',
            'application' => '%application%',
            'network' => '%network%',
            'connect' => '%connect%',
            'access' => '%access%',
            'permission' => '%permission%',
            'account' => '%account%',
            'security' => '%security%',
        ];
    }
}
