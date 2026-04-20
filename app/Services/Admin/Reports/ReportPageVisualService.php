<?php

namespace App\Services\Admin\Reports;

class ReportPageVisualService
{
    /**
     * @param  array<int, array<string, mixed>>  $monthlyPerformanceSeries
     * @param  array<int, array<string, mixed>>  $categoryBreakdownBuckets
     * @param  array<int, array<string, mixed>>  $priorityBreakdownBuckets
     * @param  array<string, mixed>  $ticketHistoryScope
     */
    public function build(
        array $monthlyPerformanceSeries,
        string $monthlyPerformanceFocusMonthKey,
        array $ticketsBreakdownOverview,
        array $categoryBreakdownBuckets,
        array $priorityBreakdownBuckets,
        array $ticketHistoryScope,
    ): array {
        $mixScopeLabel = (string) ($ticketsBreakdownOverview['label'] ?? 'All Time');
        $ticketHistoryScopeParams = collect($ticketHistoryScope)
            ->filter(fn ($value) => $value !== null && $value !== '')
            ->all();
        $pieRadius = 58;
        $pieCircumference = 2 * pi() * $pieRadius;

        $ticketPie = $this->buildPieVisualization(
            $this->buildTicketPieSlices($ticketsBreakdownOverview, $ticketHistoryScopeParams),
            $pieCircumference
        );
        $categoryPie = $this->buildPieVisualization(
            $this->buildCategoryPieSlices($categoryBreakdownBuckets, $ticketHistoryScopeParams),
            $pieCircumference
        );
        $priorityPie = $this->buildPieVisualization(
            $this->buildPriorityPieSlices($priorityBreakdownBuckets, $ticketHistoryScopeParams),
            $pieCircumference
        );

        $monthlyCountMax = max(1, collect($monthlyPerformanceSeries)->map(
            fn (array $point): int => max((int) ($point['received'] ?? 0), (int) ($point['resolved'] ?? 0))
        )->max() ?? 0);
        $chartHeight = 320;
        $chartWidth = max(760, count($monthlyPerformanceSeries) * 70);
        $paddingLeft = 44;
        $paddingRight = 24;
        $paddingTop = 18;
        $paddingBottom = 52;
        $plotWidth = max(1, $chartWidth - $paddingLeft - $paddingRight);
        $plotHeight = max(1, $chartHeight - $paddingTop - $paddingBottom);
        $step = count($monthlyPerformanceSeries) > 0 ? ($plotWidth / count($monthlyPerformanceSeries)) : $plotWidth;
        $gridLines = collect([0, 25, 50, 75, 100])
            ->map(function (int $lineRate) use ($paddingTop, $plotHeight, $monthlyCountMax): array {
                $lineY = $paddingTop + ($plotHeight - (($lineRate / 100) * $plotHeight));

                return [
                    'rate' => $lineRate,
                    'y' => $lineY,
                    'count_label' => (int) round(($lineRate / 100) * $monthlyCountMax),
                ];
            })
            ->all();
        $barWidth = max(8, (int) floor(($step * 0.28)));
        $bars = collect($monthlyPerformanceSeries)
            ->values()
            ->map(function (array $point, int $pointIndex) use ($paddingLeft, $step, $barWidth, $monthlyCountMax, $paddingTop, $plotHeight): array {
                $centerX = $paddingLeft + ($pointIndex * $step) + ($step / 2);
                $received = (int) ($point['received'] ?? 0);
                $resolved = (int) ($point['resolved'] ?? 0);
                $receivedHeight = $received > 0 ? (($received / $monthlyCountMax) * $plotHeight) : 0;
                $resolvedHeight = $resolved > 0 ? (($resolved / $monthlyCountMax) * $plotHeight) : 0;

                return [
                    'label' => (string) ($point['label'] ?? ''),
                    'center_x' => $centerX,
                    'bar_width' => $barWidth,
                    'received_height' => max(1, $receivedHeight),
                    'resolved_height' => max(1, $resolvedHeight),
                    'received_y' => $paddingTop + ($plotHeight - $receivedHeight),
                    'resolved_y' => $paddingTop + ($plotHeight - $resolvedHeight),
                    'label_y' => $paddingTop + $plotHeight + 14,
                ];
            })
            ->all();

        return [
            'mix_scope_label' => $mixScopeLabel,
            'pie_radius' => $pieRadius,
            'pie_circumference' => $pieCircumference,
            'ticket_pie' => $ticketPie,
            'category_pie' => $categoryPie,
            'priority_pie' => $priorityPie,
            'monthly_performance' => [
                'series' => $monthlyPerformanceSeries,
                'count_max' => $monthlyCountMax,
                'chart_height' => $chartHeight,
                'chart_width' => $chartWidth,
                'padding_left' => $paddingLeft,
                'padding_right' => $paddingRight,
                'padding_top' => $paddingTop,
                'padding_bottom' => $paddingBottom,
                'plot_width' => $plotWidth,
                'plot_height' => $plotHeight,
                'step' => $step,
                'grid_lines' => $gridLines,
                'bars' => $bars,
                'selected_point' => collect($monthlyPerformanceSeries)->firstWhere('key', $monthlyPerformanceFocusMonthKey)
                    ?? collect($monthlyPerformanceSeries)->last(),
            ],
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slices
     */
    private function buildPieVisualization(array $slices, float $circumference): array
    {
        $total = max(0, (int) collect($slices)->sum('count'));

        return [
            'total' => $total,
            'slices' => $slices,
            'segments' => $this->buildPieSegments($slices, $total, $circumference),
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $slices
     * @return array<int, array<string, mixed>>
     */
    private function buildPieSegments(array $slices, int $total, float $circumference): array
    {
        if ($total <= 0) {
            return [];
        }

        $segments = [];
        $accumulatedLength = 0.0;
        foreach ($slices as $slice) {
            $count = (int) ($slice['count'] ?? 0);
            if ($count <= 0) {
                continue;
            }

            $segmentLength = ($count / $total) * $circumference;
            $segments[] = [
                'label' => (string) ($slice['label'] ?? ''),
                'count' => $count,
                'color' => (string) ($slice['color'] ?? '#94a3b8'),
                'length' => $segmentLength,
                'offset' => -$accumulatedLength,
            ];
            $accumulatedLength += $segmentLength;
        }

        return $segments;
    }

    /**
     * @param  array<string, mixed>  $ticketsBreakdownOverview
     * @param  array<string, mixed>  $ticketHistoryScopeParams
     * @return array<int, array<string, mixed>>
     */
    private function buildTicketPieSlices(array $ticketsBreakdownOverview, array $ticketHistoryScopeParams): array
    {
        $pieResolved = (int) ($ticketsBreakdownOverview['resolved'] ?? 0);
        $pieClosed = (int) ($ticketsBreakdownOverview['closed'] ?? 0);
        $pieResolvedOnly = max($pieResolved - $pieClosed, 0);

        return collect([
            ['label' => 'Open', 'count' => (int) ($ticketsBreakdownOverview['open'] ?? 0), 'display_count' => (int) ($ticketsBreakdownOverview['open'] ?? 0), 'color' => '#8b5cf6', 'tab' => 'tickets', 'status' => 'open'],
            ['label' => 'In Progress', 'count' => (int) ($ticketsBreakdownOverview['in_progress'] ?? 0), 'display_count' => (int) ($ticketsBreakdownOverview['in_progress'] ?? 0), 'color' => '#0ea5e9', 'tab' => 'tickets', 'status' => 'in_progress'],
            ['label' => 'Pending', 'count' => (int) ($ticketsBreakdownOverview['pending'] ?? 0), 'display_count' => (int) ($ticketsBreakdownOverview['pending'] ?? 0), 'color' => '#f59e0b', 'tab' => 'tickets', 'status' => 'pending'],
            ['label' => 'Resolved', 'count' => $pieResolvedOnly, 'display_count' => $pieResolved, 'color' => '#10b981', 'tab' => 'history', 'status' => null],
            ['label' => 'Closed', 'count' => $pieClosed, 'display_count' => $pieClosed, 'color' => '#64748b', 'tab' => 'history', 'status' => 'closed'],
        ])->map(function (array $slice) use ($ticketHistoryScopeParams): array {
            $statusLinkParams = array_merge($ticketHistoryScopeParams, ['tab' => $slice['tab']]);
            if ($slice['status'] !== null) {
                $statusLinkParams['status'] = $slice['status'];
            }

            $slice['link'] = route('admin.tickets.index', $statusLinkParams);

            return $slice;
        })->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $categoryBreakdownBuckets
     * @param  array<string, mixed>  $ticketHistoryScopeParams
     * @return array<int, array<string, mixed>>
     */
    private function buildCategoryPieSlices(array $categoryBreakdownBuckets, array $ticketHistoryScopeParams): array
    {
        $categoryPalette = [
            'hardware' => '#0ea5e9',
            'software' => '#8b5cf6',
            'network' => '#14b8a6',
            'access / permissions' => '#f59e0b',
            'security' => '#ef4444',
            'other' => '#64748b',
        ];

        return collect($categoryBreakdownBuckets)
            ->map(function (array $bucket) use ($categoryPalette, $ticketHistoryScopeParams): array {
                $label = (string) ($bucket['name'] ?? 'Other');

                return [
                    'label' => $label,
                    'count' => (int) ($bucket['count'] ?? 0),
                    'color' => $categoryPalette[strtolower($label)] ?? '#94a3b8',
                    'link' => route('admin.tickets.index', array_merge($ticketHistoryScopeParams, [
                        'tab' => 'all',
                        'category_bucket' => strtolower(str_replace([' / ', ' '], ['_', '_'], $label)),
                    ])),
                ];
            })
            ->filter(fn (array $slice): bool => $slice['count'] > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $priorityBreakdownBuckets
     * @param  array<string, mixed>  $ticketHistoryScopeParams
     * @return array<int, array<string, mixed>>
     */
    private function buildPriorityPieSlices(array $priorityBreakdownBuckets, array $ticketHistoryScopeParams): array
    {
        $priorityPalette = [
            'pending review' => '#64748b',
            'severity 1' => '#10b981',
            'severity 2' => '#f59e0b',
            'severity 3' => '#ef4444',
        ];

        return collect($priorityBreakdownBuckets)
            ->map(function (array $bucket) use ($priorityPalette, $ticketHistoryScopeParams): array {
                $label = (string) ($bucket['name'] ?? 'Other');
                $priorityFilter = match (strtolower($label)) {
                    'pending review' => 'unassigned',
                    'severity 1' => 'severity_1',
                    'severity 2' => 'severity_2',
                    'severity 3' => 'severity_3',
                    default => null,
                };

                return [
                    'label' => $label,
                    'count' => (int) ($bucket['count'] ?? 0),
                    'color' => $priorityPalette[strtolower($label)] ?? '#94a3b8',
                    'link' => $priorityFilter
                        ? route('admin.tickets.index', array_merge($ticketHistoryScopeParams, ['tab' => 'all', 'priority' => $priorityFilter]))
                        : route('admin.tickets.index', array_merge($ticketHistoryScopeParams, ['tab' => 'all'])),
                ];
            })
            ->filter(fn (array $slice): bool => $slice['count'] > 0)
            ->values()
            ->all();
    }
}
