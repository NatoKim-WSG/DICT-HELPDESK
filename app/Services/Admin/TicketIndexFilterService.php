<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class TicketIndexFilterService
{
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

    public function applyFilters(
        Builder $query,
        Request $request,
        string $selectedStatus,
        ?array $createdDateRange = null,
    ): void {
        $this->applyFiltersExcept($query, $request, $selectedStatus, $createdDateRange);
    }

    public function applyFiltersExcept(
        Builder $query,
        Request $request,
        string $selectedStatus,
        ?array $createdDateRange = null,
        array $excludedFilters = [],
    ): void {
        $excluded = collect($excludedFilters)
            ->map(fn (mixed $value) => strtolower(trim((string) $value)))
            ->filter()
            ->values()
            ->all();

        if (! in_array('status', $excluded, true)) {
            $this->applyStatusFilter($query, $selectedStatus);
        }

        if (! in_array('priority', $excluded, true)) {
            $this->applyPriorityFilter($query, $request);
        }

        if (! in_array('category', $excluded, true)) {
            $this->applyCategoryFilters($query, $request);
        }

        if (! in_array('province', $excluded, true) || ! in_array('municipality', $excluded, true)) {
            $this->applyLocationFilters($query, $request, $excluded);
        }

        if (! in_array('account', $excluded, true)) {
            $this->applyAccountFilters($query, $request);
        }

        if (! in_array('assigned_to', $excluded, true)) {
            $this->applyAssignmentFilter($query, $request);
        }

        if (! in_array('search', $excluded, true)) {
            $this->applySearchFilter($query, $request);
        }

        if (! in_array('created_date', $excluded, true) && ! in_array('month', $excluded, true)) {
            $this->applyCreatedDateRangeFilter($query, $createdDateRange);
        }
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

    private function applyLocationFilters(Builder $query, Request $request, array $excluded = []): void
    {
        if (! in_array('province', $excluded, true) && $request->filled('province') && $request->province !== 'all') {
            $this->applyCaseInsensitiveExactMatch($query, 'province', (string) $request->province);
        }

        if (! in_array('municipality', $excluded, true) && $request->filled('municipality') && $request->municipality !== 'all') {
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
            Ticket::applyUnassignedConstraint($query);

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

        $query->where(function (Builder $builder) use ($bucket, $patterns) {
            if ($bucket === 'other') {
                $builder->whereNull('category_id')
                    ->orWhereHas('category', function (Builder $categoryQuery) use ($patterns) {
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

            $builder->whereHas('category', function (Builder $categoryQuery) use ($bucket, $patterns) {
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
