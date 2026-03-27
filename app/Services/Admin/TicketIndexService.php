<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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

        $allowedStatuses = $activeTab === 'history'
            ? array_merge(['all'], Ticket::CLOSED_STATUSES)
            : ($activeTab === 'all'
                ? array_merge(['all'], Ticket::OPEN_STATUSES, Ticket::CLOSED_STATUSES)
                : array_merge(['all'], Ticket::OPEN_STATUSES));

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
        $query
            ->when($selectedStatus !== 'all', function (Builder $builder) use ($selectedStatus) {
                $builder->where('status', $selectedStatus);
            })
            ->when($request->filled('priority') && $request->priority !== 'all', function (Builder $builder) use ($request) {
                $priority = $request->string('priority')->toString();

                if ($priority === 'unassigned') {
                    $builder->whereNull('priority');

                    return;
                }

                $builder->where('priority', $priority);
            })
            ->when($request->filled('category') && $request->category !== 'all', function (Builder $builder) use ($request) {
                $builder->where('category_id', $request->integer('category'));
            })
            ->when($request->filled('category_bucket') && $request->category_bucket !== 'all', function (Builder $builder) use ($request) {
                $bucket = $this->normalizeCategoryBucketFilter($request->string('category_bucket')->toString());
                if ($bucket === null) {
                    return;
                }

                $this->applyCategoryBucketFilter($builder, $bucket);
            });

        if ($request->filled('province') && $request->province !== 'all') {
            $this->applyCaseInsensitiveExactMatch($query, 'province', (string) $request->province);
        }

        if ($request->filled('municipality') && $request->municipality !== 'all') {
            $this->applyCaseInsensitiveExactMatch($query, 'municipality', (string) $request->municipality);
        }

        if ($request->filled('account_id') && $request->account_id !== 'all') {
            $query->where('user_id', $request->integer('account_id'));
        }

        if ($request->filled('related_user_id') && $request->related_user_id !== 'all') {
            $relatedUserId = $request->integer('related_user_id');

            $query->where(function (Builder $builder) use ($relatedUserId) {
                $builder->where('user_id', $relatedUserId)
                    ->orWhere(function (Builder $assignmentQuery) use ($relatedUserId) {
                        Ticket::applyAssignedToConstraint($assignmentQuery, $relatedUserId);
                    });
            });
        }

        if ($request->filled('assigned_to') && $request->assigned_to !== 'all') {
            if ((string) $request->assigned_to === '0') {
                $query->whereDoesntHave('assignedUsers');
            } else {
                Ticket::applyAssignedToConstraint($query, $request->integer('assigned_to'));
            }
        }

        $query->when($request->filled('search'), function (Builder $builder) use ($request) {
            $search = mb_strtolower($request->string('search')->toString());
            $pattern = '%'.$search.'%';

            $builder->where(function (Builder $searchQuery) use ($pattern) {
                $searchQuery->whereRaw('LOWER(subject) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(ticket_number) LIKE ?', [$pattern])
                    ->orWhereHas('user', function (Builder $userQuery) use ($pattern) {
                        $userQuery->whereRaw('LOWER(name) LIKE ?', [$pattern])
                            ->orWhereRaw('LOWER(email) LIKE ?', [$pattern]);
                    });
            });
        });

        if ($createdDateRange !== null) {
            $query->whereBetween('created_at', [
                $createdDateRange['start'],
                $createdDateRange['end'],
            ]);
        }
    }

    public function buildTicketListSnapshotToken(Builder $query): string
    {
        $latestUpdatedAt = (clone $query)->max('updated_at');
        $latestUpdatedTimestamp = $latestUpdatedAt ? strtotime((string) $latestUpdatedAt) : 0;
        $totalTickets = (clone $query)->count();

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'total_tickets' => $totalTickets,
        ]));
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
        $hardwarePattern = '%hardware%';
        $softwarePattern = '%software%';
        $applicationPattern = '%application%';
        $networkPattern = '%network%';
        $connectPattern = '%connect%';
        $accessPattern = '%access%';
        $permissionPattern = '%permission%';
        $accountPattern = '%account%';
        $securityPattern = '%security%';

        $query->where(function (Builder $builder) use (
            $bucket,
            $hardwarePattern,
            $softwarePattern,
            $applicationPattern,
            $networkPattern,
            $connectPattern,
            $accessPattern,
            $permissionPattern,
            $accountPattern,
            $securityPattern
        ) {
            if ($bucket === 'other') {
                $builder->whereNull('category_id')
                    ->orWhereHas('category', function (Builder $categoryQuery) use (
                        $hardwarePattern,
                        $softwarePattern,
                        $applicationPattern,
                        $networkPattern,
                        $connectPattern,
                        $accessPattern,
                        $permissionPattern,
                        $accountPattern,
                        $securityPattern
                    ) {
                        $categoryQuery
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$hardwarePattern])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$softwarePattern])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$applicationPattern])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$networkPattern])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$connectPattern])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$accessPattern])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$permissionPattern])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$accountPattern])
                            ->whereRaw('LOWER(name) NOT LIKE ?', [$securityPattern]);
                    });

                return;
            }

            $builder->whereHas('category', function (Builder $categoryQuery) use (
                $bucket,
                $hardwarePattern,
                $softwarePattern,
                $applicationPattern,
                $networkPattern,
                $connectPattern,
                $accessPattern,
                $permissionPattern,
                $accountPattern,
                $securityPattern
            ) {
                match ($bucket) {
                    'hardware' => $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$hardwarePattern]),
                    'software' => $categoryQuery->where(function (Builder $query) use ($softwarePattern, $applicationPattern) {
                        $query->whereRaw('LOWER(name) LIKE ?', [$softwarePattern])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$applicationPattern]);
                    }),
                    'network' => $categoryQuery->where(function (Builder $query) use ($networkPattern, $connectPattern) {
                        $query->whereRaw('LOWER(name) LIKE ?', [$networkPattern])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$connectPattern]);
                    }),
                    'access_permissions' => $categoryQuery->where(function (Builder $query) use ($accessPattern, $permissionPattern, $accountPattern) {
                        $query->whereRaw('LOWER(name) LIKE ?', [$accessPattern])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$permissionPattern])
                            ->orWhereRaw('LOWER(name) LIKE ?', [$accountPattern]);
                    }),
                    'security' => $categoryQuery->whereRaw('LOWER(name) LIKE ?', [$securityPattern]),
                    default => null,
                };
            });
        });
    }
}
