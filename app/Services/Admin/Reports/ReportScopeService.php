<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ReportScopeService
{
    public const ALL_TIME_MONTH_KEY = 'all';

    public function resolveSelectedMonthKey(
        mixed $requestedMonth,
        Collection $monthlyReportRows,
        mixed $preferredFallbackMonth = null,
        bool $allowAllTime = false
    ): string {
        $availableMonths = $monthlyReportRows->pluck('month_key')->all();
        $fallbackMonth = ! empty($availableMonths)
            ? (string) end($availableMonths)
            : now()->format('Y-m');

        if ($allowAllTime && is_string($requestedMonth) && trim($requestedMonth) === self::ALL_TIME_MONTH_KEY) {
            return self::ALL_TIME_MONTH_KEY;
        }

        $preferredFallback = $this->normalizeMonthKey($preferredFallbackMonth);
        if ($preferredFallback && in_array($preferredFallback, $availableMonths, true)) {
            $fallbackMonth = $preferredFallback;
        }

        $normalized = $this->normalizeMonthKey($requestedMonth);
        if (! $normalized) {
            return $fallbackMonth;
        }

        return in_array($normalized, $availableMonths, true)
            ? $normalized
            : $fallbackMonth;
    }

    public function latestAvailableMonthKey(Collection $monthlyReportRows): string
    {
        return (string) ($monthlyReportRows->pluck('month_key')->last() ?? now()->format('Y-m'));
    }

    public function monthRangeFromKey(string $monthKey): array
    {
        try {
            $start = Carbon::createFromFormat('Y-m', $monthKey)->startOfMonth();
        } catch (\Throwable) {
            $start = now()->startOfMonth();
        }

        return [
            'start' => $start->copy()->startOfDay(),
            'end' => $start->copy()->endOfMonth()->endOfDay(),
            'label' => $start->format('M Y'),
        ];
    }

    public function allTimeRangeForScope(Builder $scopedTickets): array
    {
        $firstCreatedAt = (clone $scopedTickets)
            ->toBase()
            ->selectRaw('MIN(created_at) as first_created_at')
            ->value('first_created_at');

        $start = $firstCreatedAt
            ? Carbon::parse((string) $firstCreatedAt)->startOfDay()
            : now()->startOfDay();
        $end = now()->endOfDay();

        return [
            'start' => $start,
            'end' => $end,
            'label' => 'All Time',
        ];
    }

    public function allTimeReportRow(
        Builder $scopedTickets,
        array $range,
        MonthlyReportDatasetService $monthlyReportDatasets
    ): array {
        $totalCreated = (clone $scopedTickets)->count();
        $completed = (clone $scopedTickets)
            ->where(function ($query) {
                $query->whereNotNull('resolved_at')
                    ->orWhereNotNull('closed_at')
                    ->orWhereIn('status', Ticket::CLOSED_STATUSES);
            })
            ->count();

        return [
            'month_key' => self::ALL_TIME_MONTH_KEY,
            'month_label' => 'All Time',
            'month_start' => $range['start']->toDateString(),
            'month_end' => $range['end']->toDateString(),
            'received' => $totalCreated,
            'resolved' => $completed,
            'completed_in_period' => $completed,
            'open_end_of_month' => $monthlyReportDatasets->countOpenTicketsAtCutoff(clone $scopedTickets, $range['end']),
            'resolution_rate' => $totalCreated > 0
                ? round(($completed / $totalCreated) * 100, 1)
                : 0.0,
        ];
    }

    public function emptyMonthlyReportRow(string $monthKey, array $monthRange): array
    {
        return [
            'month_key' => $monthKey,
            'month_label' => (string) ($monthRange['label'] ?? $monthKey),
            'month_start' => isset($monthRange['start']) ? $monthRange['start']->toDateString() : null,
            'month_end' => isset($monthRange['end']) ? $monthRange['end']->toDateString() : null,
            'received' => 0,
            'resolved' => 0,
            'open_end_of_month' => 0,
            'resolution_rate' => 0.0,
        ];
    }

    public function resolveRequestedDate(mixed $requestedDate): ?Carbon
    {
        if (! is_string($requestedDate)) {
            return null;
        }

        $normalized = trim($requestedDate);
        if ($normalized === '') {
            return null;
        }

        try {
            return Carbon::createFromFormat('Y-m-d', $normalized)->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    public function buildDateOptionsForRange(Carbon $start, Carbon $end): Collection
    {
        $options = collect();
        $rangeStart = $start->copy()->startOfDay();
        $rangeEnd = $end->copy()->startOfDay();

        for ($cursor = $rangeStart->copy(); $cursor->lte($rangeEnd); $cursor->addDay()) {
            $options->push([
                'value' => $cursor->toDateString(),
                'label' => $cursor->format('M j, Y'),
            ]);
        }

        return $options->reverse()->values();
    }

    private function normalizeMonthKey(mixed $monthKey): ?string
    {
        if (! is_string($monthKey)) {
            return null;
        }

        $normalized = trim($monthKey);
        if (! preg_match('/^\d{4}-\d{2}$/', $normalized)) {
            return null;
        }

        return $normalized;
    }
}
