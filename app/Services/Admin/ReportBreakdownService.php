<?php

namespace App\Services\Admin;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportBreakdownService
{
    public function buildCategoryBreakdownForScope(Builder $scopedTickets, Carbon $start, Carbon $end): Collection
    {
        $categoryCounts = $this->categoryCountsForScope(clone $scopedTickets, $start, $end);
        $total = max(1, (int) $categoryCounts->sum(fn (object $row) => (int) $row->count));

        return $categoryCounts
            ->map(function (object $row) use ($total): array {
                $count = (int) $row->count;

                return [
                    'name' => (string) $row->category_name,
                    'count' => $count,
                    'share' => round(($count / $total) * 100, 1),
                ];
            })
            ->values();
    }

    public function buildCategoryBucketsForScope(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $bucketOrder = ['Hardware', 'Software', 'Network', 'Access / Permissions', 'Security', 'Other'];
        $bucketCounts = array_fill_keys($bucketOrder, 0);

        $categoryCounts = $this->categoryCountsForScope(clone $scopedTickets, $start, $end);
        foreach ($categoryCounts as $row) {
            $bucket = $this->normalizeCategoryBucket((string) $row->category_name);
            $bucketCounts[$bucket] = ($bucketCounts[$bucket] ?? 0) + (int) $row->count;
        }

        return collect($bucketCounts)->map(fn (int $count, string $name) => [
            'name' => $name,
            'count' => $count,
        ])->values()->all();
    }

    public function buildPriorityBucketsForScope(Builder $scopedTickets, Carbon $start, Carbon $end): array
    {
        $counts = (clone $scopedTickets)
            ->whereBetween('created_at', [$start, $end])
            ->selectRaw('priority, COUNT(*) as count')
            ->groupBy('priority')
            ->pluck('count', 'priority');

        return [
            ['name' => 'Critical', 'count' => (int) ($counts['urgent'] ?? 0)],
            ['name' => 'High', 'count' => (int) ($counts['high'] ?? 0)],
            ['name' => 'Medium', 'count' => (int) ($counts['medium'] ?? 0)],
            ['name' => 'Low', 'count' => (int) ($counts['low'] ?? 0)],
        ];
    }

    public function categoryCountsForScope(
        Builder $scopedTickets,
        ?Carbon $start = null,
        ?Carbon $end = null
    ): Collection {
        $query = (clone $scopedTickets)
            ->leftJoin('categories', 'tickets.category_id', '=', 'categories.id')
            ->reorder();

        if ($start && $end) {
            $query->whereBetween('tickets.created_at', [$start, $end]);
        }

        return $query
            ->selectRaw("COALESCE(categories.name, 'Uncategorized') as category_name, COUNT(*) as count")
            ->groupBy('categories.name')
            ->orderByDesc('count')
            ->get();
    }

    public function normalizeCategoryBucket(?string $categoryName): string
    {
        $value = strtolower(trim((string) $categoryName));

        if ($value === '') {
            return 'Other';
        }

        if (str_contains($value, 'hardware')) {
            return 'Hardware';
        }

        if (str_contains($value, 'software') || str_contains($value, 'application')) {
            return 'Software';
        }

        if (str_contains($value, 'network') || str_contains($value, 'connect')) {
            return 'Network';
        }

        if (str_contains($value, 'access') || str_contains($value, 'permission') || str_contains($value, 'account')) {
            return 'Access / Permissions';
        }

        if (str_contains($value, 'security')) {
            return 'Security';
        }

        return 'Other';
    }
}
