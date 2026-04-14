<?php

namespace App\Services\Admin\Reports;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ReportPageScopeResolverService
{
    public function __construct(
        private ReportScopeService $reportScopes,
        private MonthlyReportDatasetService $monthlyReportDatasets,
    ) {}

    public function resolveSelectedMonthContext(Request $request, Collection $monthlyReportRows, Builder $scopedTickets): array
    {
        $selectedMonthKey = $this->reportScopes->resolveSelectedMonthKey(
            $request->query('month'),
            $monthlyReportRows,
            allowAllTime: true
        );
        $selectedMonthIsAllTime = $selectedMonthKey === ReportScopeService::ALL_TIME_MONTH_KEY;
        $selectedMonthRange = $selectedMonthIsAllTime
            ? $this->reportScopes->allTimeRangeForScope(clone $scopedTickets)
            : $this->reportScopes->monthRangeFromKey($selectedMonthKey);
        $selectedMonthRow = $selectedMonthIsAllTime
            ? $this->reportScopes->allTimeReportRow(clone $scopedTickets, $selectedMonthRange, $this->monthlyReportDatasets)
            : ($monthlyReportRows->firstWhere('month_key', $selectedMonthKey)
                ?? $this->reportScopes->emptyMonthlyReportRow($selectedMonthKey, $selectedMonthRange));

        return [
            'selectedMonthKey' => $selectedMonthKey,
            'selectedMonthIsAllTime' => $selectedMonthIsAllTime,
            'selectedMonthRange' => $selectedMonthRange,
            'selectedMonthRow' => $selectedMonthRow,
            'selectedPeriodStart' => $selectedMonthRange['start']->copy(),
            'selectedPeriodEnd' => $selectedMonthRange['end']->copy(),
            'detailFilterApplied' => $request->boolean('apply_details_filter'),
        ];
    }

    public function resolveDetailScope(Request $request, Collection $monthlyReportRows, string $selectedMonthKey): array
    {
        $detailMonthKey = $this->reportScopes->resolveSelectedMonthKey(
            $request->query('detail_month'),
            $monthlyReportRows,
            $selectedMonthKey
        );
        $detailMonthRange = $this->reportScopes->monthRangeFromKey($detailMonthKey);
        $detailDateOptions = $this->reportScopes->buildDateOptionsForRange($detailMonthRange['start'], $detailMonthRange['end']);
        $detailSelectedDate = $this->reportScopes->resolveRequestedDate($request->query('detail_date'));
        if (
            $detailSelectedDate
            && (
                $detailSelectedDate->lt($detailMonthRange['start']->copy()->startOfDay())
                || $detailSelectedDate->gt($detailMonthRange['end']->copy()->startOfDay())
            )
        ) {
            $detailSelectedDate = null;
        }

        return [
            'detailMonthKey' => $detailMonthKey,
            'detailMonthRange' => $detailMonthRange,
            'detailDateOptions' => $detailDateOptions,
            'detailSelectedDate' => $detailSelectedDate,
            'detailDateValue' => $detailSelectedDate?->toDateString(),
            'detailScopeStart' => $detailSelectedDate?->copy()->startOfDay() ?? $detailMonthRange['start']->copy(),
            'detailScopeEnd' => $detailSelectedDate?->copy()->endOfDay() ?? $detailMonthRange['end']->copy(),
            'detailScopeLabel' => $detailSelectedDate?->format('M j, Y') ?? (string) $detailMonthRange['label'],
        ];
    }

    public function resolveDailyScope(
        Request $request,
        Collection $monthlyReportRows,
        bool $detailFilterApplied,
        string $detailMonthKey,
        array $detailMonthRange,
        ?Carbon $detailSelectedDate
    ): array {
        $dailyMonthKey = $this->reportScopes->resolveSelectedMonthKey(
            $request->query('daily_month'),
            $monthlyReportRows,
            now()->format('Y-m')
        );
        $dailyMonthRange = $this->reportScopes->monthRangeFromKey($dailyMonthKey);
        if ($detailFilterApplied) {
            $dailyMonthKey = $detailMonthKey;
            $dailyMonthRange = $detailMonthRange;
        }

        $dailyDateOptions = $this->reportScopes->buildDateOptionsForRange($dailyMonthRange['start'], $dailyMonthRange['end']);
        $requestedDailyDate = is_string($request->query('daily_date'))
            ? trim((string) $request->query('daily_date'))
            : '';
        $dailyAllDaysSelected = $requestedDailyDate === 'all';
        $dailySelectedDate = $dailyAllDaysSelected
            ? null
            : $this->reportScopes->resolveRequestedDate($request->query('daily_date'));
        if ($detailFilterApplied && $detailSelectedDate) {
            $dailySelectedDate = $detailSelectedDate->copy();
            $dailyAllDaysSelected = false;
        }
        if (
            ! $dailyAllDaysSelected
            && (
                ! $dailySelectedDate
                || $dailySelectedDate->lt($dailyMonthRange['start']->copy()->startOfDay())
                || $dailySelectedDate->gt($dailyMonthRange['end']->copy()->startOfDay())
            )
        ) {
            $today = now()->startOfDay();
            if (
                $today->gte($dailyMonthRange['start']->copy()->startOfDay())
                && $today->lte($dailyMonthRange['end']->copy()->startOfDay())
            ) {
                $dailySelectedDate = $today;
            } else {
                $dailySelectedDate = $dailyMonthRange['end']->copy()->startOfDay();
            }
        }

        return [
            'dailyMonthKey' => $dailyMonthKey,
            'dailyMonthRange' => $dailyMonthRange,
            'dailyDateOptions' => $dailyDateOptions,
            'dailyAllDaysSelected' => $dailyAllDaysSelected,
            'dailySelectedDate' => $dailySelectedDate,
            'dailySelectedDateValue' => $dailyAllDaysSelected ? 'all' : $dailySelectedDate->toDateString(),
        ];
    }
}
