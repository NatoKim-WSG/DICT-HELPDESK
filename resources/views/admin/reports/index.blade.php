@extends('layouts.app')

@section('title', 'Reports - iOne Resources Inc.')

@section('content')
@php
    $periodChange = (float) ($periodOverview['percent_change_vs_previous'] ?? 0);
    $periodChangePrefix = $periodChange > 0 ? '+' : '';
    $periodChangeTone = $periodChange > 0 ? 'text-emerald-600' : ($periodChange < 0 ? 'text-rose-600' : 'text-slate-600');
    $slaRate = (float) ($periodOverview['sla_compliance_rate'] ?? 0);
    $monthlyPerformanceSeries = $monthlyGraphPoints->values();
    $monthlyCountMax = max(1, (int) $monthlyPerformanceSeries->max(fn ($point) => max((int) $point['received'], (int) $point['resolved'])));
    $averageResolutionMinutes = (int) ($stats['average_resolution_minutes'] ?? 0);
    $averageResolutionLabel = $averageResolutionMinutes >= 60
        ? number_format($averageResolutionMinutes / 60, 1).' hrs'
        : $averageResolutionMinutes.' min';
    $chartHeight = 320;
    $chartWidth = max(760, ($monthlyPerformanceSeries->count() * 70));
    $paddingLeft = 44;
    $paddingRight = 24;
    $paddingTop = 18;
    $paddingBottom = 52;
    $plotWidth = max(1, $chartWidth - $paddingLeft - $paddingRight);
    $plotHeight = max(1, $chartHeight - $paddingTop - $paddingBottom);
    $step = $monthlyPerformanceSeries->count() > 0 ? ($plotWidth / $monthlyPerformanceSeries->count()) : $plotWidth;
    $selectedPerformancePoint = $monthlyPerformanceSeries->firstWhere('key', $selectedMonthKey)
        ?? $monthlyPerformanceSeries->last();

    $pieInProgress = (int) ($periodOverview['in_progress'] ?? 0);
    $piePending = (int) ($periodOverview['pending'] ?? 0);
    $pieResolved = (int) ($periodOverview['resolved'] ?? 0);
    $pieClosed = (int) ($periodOverview['closed'] ?? 0);
    $pieTotalCreated = (int) ($periodOverview['total_created'] ?? 0);
    $pieOther = max($pieTotalCreated - ($pieInProgress + $piePending + $pieResolved + $pieClosed), 0);

    $ticketPieSlices = [
        ['label' => 'In progress', 'count' => $pieInProgress, 'color' => '#0ea5e9'],
        ['label' => 'Pending', 'count' => $piePending, 'color' => '#f59e0b'],
        ['label' => 'Resolved', 'count' => $pieResolved, 'color' => '#10b981'],
        ['label' => 'Closed', 'count' => $pieClosed, 'color' => '#64748b'],
    ];
    if ($pieOther > 0) {
        $ticketPieSlices[] = ['label' => 'Other', 'count' => $pieOther, 'color' => '#8b5cf6'];
    }

    $ticketPieTotal = max(0, (int) collect($ticketPieSlices)->sum('count'));
    $ticketPieRadius = 58;
    $ticketPieCircumference = 2 * pi() * $ticketPieRadius;
    $buildPieSegments = function (array $slices, int $total) use ($ticketPieCircumference): array {
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

            $segmentLength = ($count / $total) * $ticketPieCircumference;
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
    };
    $ticketPieSegments = $buildPieSegments($ticketPieSlices, $ticketPieTotal);

    $categoryPalette = [
        'hardware' => '#0ea5e9',
        'software' => '#8b5cf6',
        'network' => '#14b8a6',
        'access / permissions' => '#f59e0b',
        'security' => '#ef4444',
        'other' => '#64748b',
    ];
    $categoryPieSlices = collect($categoryBreakdownBuckets ?? [])
        ->map(function ($bucket) use ($categoryPalette) {
            $label = (string) ($bucket['name'] ?? 'Other');
            $count = (int) ($bucket['count'] ?? 0);
            $color = $categoryPalette[strtolower($label)] ?? '#94a3b8';

            return [
                'label' => $label,
                'count' => $count,
                'color' => $color,
            ];
        })
        ->filter(fn ($slice) => $slice['count'] > 0)
        ->values()
        ->all();
    $categoryPieTotal = max(0, (int) collect($categoryPieSlices)->sum('count'));

    $priorityPalette = [
        'critical' => '#ef4444',
        'high' => '#f59e0b',
        'medium' => '#0ea5e9',
        'low' => '#10b981',
    ];
    $priorityPieSlices = collect($priorityBreakdownBuckets ?? [])
        ->map(function ($bucket) use ($priorityPalette) {
            $label = (string) ($bucket['name'] ?? 'Other');
            $count = (int) ($bucket['count'] ?? 0);
            $color = $priorityPalette[strtolower($label)] ?? '#94a3b8';

            return [
                'label' => $label,
                'count' => $count,
                'color' => $color,
            ];
        })
        ->filter(fn ($slice) => $slice['count'] > 0)
        ->values()
        ->all();
    $priorityPieTotal = max(0, (int) collect($priorityPieSlices)->sum('count'));
    $categoryPieSegments = $buildPieSegments($categoryPieSlices, $categoryPieTotal);
    $priorityPieSegments = $buildPieSegments($priorityPieSlices, $priorityPieTotal);
@endphp

<div class="mx-auto max-w-[1760px] px-4 sm:px-6 lg:px-8">
    <div class="panel mb-6 overflow-hidden">
        <div class="flex flex-col gap-5 border-b border-slate-200 bg-slate-50 px-5 py-5 sm:px-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="font-display text-3xl font-semibold text-slate-900">Statistics & Reports</h1>
                <p class="mt-1 text-sm text-slate-500">Monthly operations summary with trend and risk insights.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-end gap-2" data-submit-feedback>
                    <div>
                        <label for="month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Reporting Period</label>
                        <select id="month" name="month" onchange="this.form.submit()" class="form-input min-w-[190px] py-2 text-sm">
                            @foreach($monthOptions as $option)
                                <option value="{{ $option['key'] }}" {{ $selectedMonthKey === $option['key'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <noscript>
                        <button type="submit" class="btn-secondary py-2">Apply</button>
                    </noscript>
                </form>

                <a href="{{ route('admin.reports.monthly.pdf', ['month' => $selectedMonthKey]) }}" class="btn-primary py-2">
                    Download Monthly PDF
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 px-5 py-5 md:grid-cols-2 xl:grid-cols-3 sm:px-6">
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Tickets This Period</p>
                <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ $periodOverview['total_tickets'] }}</p>
                <p class="mt-2 text-xs text-slate-500">{{ $periodOverview['label'] }}</p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">% Change Vs Previous Period</p>
                <p class="mt-2 font-display text-3xl font-semibold {{ $periodChangeTone }}">{{ $periodChangePrefix }}{{ number_format($periodChange, 1) }}%</p>
                <p class="mt-2 text-xs text-slate-500">Compared with {{ $previousMonthRow['month_label'] }}</p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">SLA Compliance Rate</p>
                <p class="mt-2 font-display text-3xl font-semibold text-emerald-600">{{ number_format($slaRate, 1) }}%</p>
                <p class="mt-2 text-xs text-slate-500">Completed tickets with due date met</p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Major Issues / Incidents</p>
                <p class="mt-2 font-display text-3xl font-semibold text-rose-600">{{ $majorIssueSummary['major_count'] ?? 0 }}</p>
                <p class="mt-2 text-xs text-slate-500">High or urgent tickets created this period</p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Backlog (Period End)</p>
                <p class="mt-2 font-display text-3xl font-semibold text-amber-600">{{ $periodOverview['backlog_end'] }}</p>
                <p class="mt-2 text-xs text-slate-500">Open tickets at period end</p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Resolution Rate</p>
                <p class="mt-2 font-display text-3xl font-semibold text-sky-600">{{ number_format((float) ($selectedMonthRow['resolution_rate'] ?? 0), 1) }}%</p>
                <p class="mt-2 text-xs text-slate-500">Resolved/closed against created this period</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        <div class="flex flex-col gap-8 xl:col-span-2">
            <div class="panel order-1 p-5 sm:p-6">
                <div class="mb-4">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Volume Comparison</h2>
                    <p class="mt-1 text-sm text-slate-500">Current period volume compared with previous period.</p>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="flex flex-wrap items-center gap-3 text-sm">
                        <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">Current: {{ $periodOverview['total_tickets'] }}</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold text-slate-700">Previous: {{ $previousMonthRow['received'] ?? 0 }}</span>
                        <span class="rounded-full bg-slate-100 px-3 py-1 font-semibold {{ $periodChangeTone }}">Change: {{ $periodChangePrefix }}{{ number_format($periodChange, 1) }}%</span>
                    </div>
                </div>
            </div>

            <div class="panel order-3 p-5 sm:p-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-semibold text-slate-900">Total Tickets Breakdown</h2>
                        <p class="mt-1 text-sm text-slate-500">Status mix for tickets created in the selected period.</p>
                    </div>
                    <button type="button" class="btn-secondary py-2 text-xs js-open-volume-chart" data-chart-title="Total Tickets Breakdown (Detailed View)" data-chart-source="total-tickets-breakdown-chart">
                        Enlarge
                    </button>
                </div>
                <div id="total-tickets-breakdown-chart" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Tickets</p>
                        <span class="text-xs text-slate-500">{{ $pieTotalCreated }} total</span>
                    </div>
                    <div class="grid gap-4 md:grid-cols-[auto_1fr] md:items-center">
                        <div class="flex justify-center">
                            <svg viewBox="0 0 180 180" class="js-total-pie-chart h-44 w-44" role="img" aria-label="Total tickets breakdown">
                                <circle cx="90" cy="90" r="{{ $ticketPieRadius }}" fill="none" class="pie-track" stroke-width="20"></circle>
                                @if($ticketPieTotal > 0)
                                    @foreach($ticketPieSegments as $segment)
                                        <circle
                                            cx="90"
                                            cy="90"
                                            r="{{ $ticketPieRadius }}"
                                            fill="none"
                                            stroke="{{ $segment['color'] }}"
                                            stroke-width="20"
                                            stroke-linecap="butt"
                                            stroke-dasharray="{{ $segment['length'] }} {{ $ticketPieCircumference }}"
                                            stroke-dashoffset="{{ $segment['offset'] }}"
                                            transform="rotate(-90 90 90)"
                                        ></circle>
                                    @endforeach
                                @endif
                                <text x="90" y="84" text-anchor="middle" class="pie-center-label" font-size="11" font-weight="600">Total</text>
                                <text x="90" y="104" text-anchor="middle" class="pie-center-value" font-size="24" font-weight="700">{{ $pieTotalCreated }}</text>
                            </svg>
                        </div>
                        <div class="space-y-2">
                            @foreach($ticketPieSlices as $slice)
                                @php
                                    $sliceCount = (int) ($slice['count'] ?? 0);
                                    $sliceShare = $ticketPieTotal > 0 ? ($sliceCount / $ticketPieTotal) * 100 : 0;
                                @endphp
                                <div class="pie-legend-row flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2 text-sm">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $slice['color'] }};"></span>
                                        <span class="pie-legend-text text-slate-600">{{ $slice['label'] }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="pie-legend-value font-semibold text-slate-900">{{ $sliceCount }}</span>
                                        <span class="pie-legend-meta ml-1 text-xs text-slate-500">({{ number_format($sliceShare, 1) }}%)</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel order-4 p-5 sm:p-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-semibold text-slate-900">Category Mix</h2>
                        <p class="mt-1 text-sm text-slate-500">Category distribution for tickets created in the selected period.</p>
                    </div>
                    <button type="button" class="btn-secondary py-2 text-xs js-open-volume-chart" data-chart-title="Category Mix (Detailed View)" data-chart-source="category-mix-chart">
                        Enlarge
                    </button>
                </div>
                <div id="category-mix-chart" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">By Category</p>
                        <span class="text-xs text-slate-500">{{ $categoryPieTotal }} total</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[auto_1fr] sm:items-center">
                        <div class="flex justify-center">
                            <svg viewBox="0 0 180 180" class="js-total-pie-chart h-40 w-40" role="img" aria-label="Tickets by category">
                                <circle cx="90" cy="90" r="{{ $ticketPieRadius }}" fill="none" class="pie-track" stroke-width="20"></circle>
                                @if($categoryPieTotal > 0)
                                    @foreach($categoryPieSegments as $segment)
                                        <circle
                                            cx="90"
                                            cy="90"
                                            r="{{ $ticketPieRadius }}"
                                            fill="none"
                                            stroke="{{ $segment['color'] }}"
                                            stroke-width="20"
                                            stroke-linecap="butt"
                                            stroke-dasharray="{{ $segment['length'] }} {{ $ticketPieCircumference }}"
                                            stroke-dashoffset="{{ $segment['offset'] }}"
                                            transform="rotate(-90 90 90)"
                                        ></circle>
                                    @endforeach
                                @endif
                                <text x="90" y="84" text-anchor="middle" class="pie-center-label" font-size="11" font-weight="600">Category</text>
                                <text x="90" y="104" text-anchor="middle" class="pie-center-value" font-size="20" font-weight="700">{{ $categoryPieTotal }}</text>
                            </svg>
                        </div>
                        <div class="space-y-1.5">
                            @foreach($categoryPieSlices as $slice)
                                @php
                                    $sliceShare = $categoryPieTotal > 0 ? ($slice['count'] / $categoryPieTotal) * 100 : 0;
                                @endphp
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $slice['color'] }};"></span>
                                        <span class="text-slate-600">{{ $slice['label'] }}</span>
                                    </div>
                                    <span class="font-semibold text-slate-900">{{ $slice['count'] }} <span class="text-slate-500">({{ number_format($sliceShare, 1) }}%)</span></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel order-5 p-5 sm:p-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-semibold text-slate-900">Priority Mix</h2>
                        <p class="mt-1 text-sm text-slate-500">Priority distribution for tickets created in the selected period.</p>
                    </div>
                    <button type="button" class="btn-secondary py-2 text-xs js-open-volume-chart" data-chart-title="Priority Mix (Detailed View)" data-chart-source="priority-mix-chart">
                        Enlarge
                    </button>
                </div>
                <div id="priority-mix-chart" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">By Priority</p>
                        <span class="text-xs text-slate-500">{{ $priorityPieTotal }} total</span>
                    </div>
                    <div class="grid gap-3 sm:grid-cols-[auto_1fr] sm:items-center">
                        <div class="flex justify-center">
                            <svg viewBox="0 0 180 180" class="js-total-pie-chart h-40 w-40" role="img" aria-label="Tickets by priority">
                                <circle cx="90" cy="90" r="{{ $ticketPieRadius }}" fill="none" class="pie-track" stroke-width="20"></circle>
                                @if($priorityPieTotal > 0)
                                    @foreach($priorityPieSegments as $segment)
                                        <circle
                                            cx="90"
                                            cy="90"
                                            r="{{ $ticketPieRadius }}"
                                            fill="none"
                                            stroke="{{ $segment['color'] }}"
                                            stroke-width="20"
                                            stroke-linecap="butt"
                                            stroke-dasharray="{{ $segment['length'] }} {{ $ticketPieCircumference }}"
                                            stroke-dashoffset="{{ $segment['offset'] }}"
                                            transform="rotate(-90 90 90)"
                                        ></circle>
                                    @endforeach
                                @endif
                                <text x="90" y="84" text-anchor="middle" class="pie-center-label" font-size="11" font-weight="600">Priority</text>
                                <text x="90" y="104" text-anchor="middle" class="pie-center-value" font-size="20" font-weight="700">{{ $priorityPieTotal }}</text>
                            </svg>
                        </div>
                        <div class="space-y-1.5">
                            @foreach($priorityPieSlices as $slice)
                                @php
                                    $sliceShare = $priorityPieTotal > 0 ? ($slice['count'] / $priorityPieTotal) * 100 : 0;
                                @endphp
                                <div class="flex items-center justify-between text-xs">
                                    <div class="flex items-center gap-2">
                                        <span class="h-2.5 w-2.5 rounded-full" style="background-color: {{ $slice['color'] }};"></span>
                                        <span class="text-slate-600">{{ $slice['label'] }}</span>
                                    </div>
                                    <span class="font-semibold text-slate-900">{{ $slice['count'] }} <span class="text-slate-500">({{ number_format($sliceShare, 1) }}%)</span></span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel order-2 p-5 sm:p-6">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="font-display text-xl font-semibold text-slate-900">Monthly Performance (Last 12 Months)</h2>
                        <p class="mt-1 text-sm text-slate-500">Received and completed bars by month.</p>
                    </div>
                    <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-end gap-2" data-submit-feedback>
                        <div>
                            <label for="monthly-focus-month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Focus Month</label>
                            <select id="monthly-focus-month" name="month" onchange="this.form.submit()" class="form-input min-w-[190px] py-2 text-sm">
                                @foreach($monthOptions as $option)
                                    <option value="{{ $option['key'] }}" {{ $selectedMonthKey === $option['key'] ? 'selected' : '' }}>
                                        {{ $option['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <noscript>
                            <button type="submit" class="btn-secondary py-2">Apply</button>
                        </noscript>
                    </form>
                </div>
                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-3 flex flex-wrap items-center gap-4 text-xs text-slate-600">
                        <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span>Received</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>Completed</span>
                    </div>
                    <div class="w-full overflow-hidden">
                        <svg viewBox="0 0 {{ $chartWidth }} {{ $chartHeight }}" class="h-80 w-full">
                            @foreach([0, 25, 50, 75, 100] as $lineRate)
                                @php
                                    $lineY = $paddingTop + ($plotHeight - (($lineRate / 100) * $plotHeight));
                                    $countLabel = (int) round(($lineRate / 100) * $monthlyCountMax);
                                @endphp
                                <line x1="{{ $paddingLeft }}" y1="{{ $lineY }}" x2="{{ $chartWidth - $paddingRight }}" y2="{{ $lineY }}" stroke="#334155" stroke-width="1" stroke-dasharray="3 4"></line>
                                <text x="6" y="{{ $lineY + 4 }}" fill="#94a3b8" font-size="10">{{ $countLabel }}</text>
                            @endforeach

                            @foreach($monthlyPerformanceSeries as $pointIndex => $point)
                                @php
                                    $centerX = $paddingLeft + ($pointIndex * $step) + ($step / 2);
                                    $barWidth = max(8, (int) floor(($step * 0.28)));
                                    $receivedHeight = $point['received'] > 0 ? (($point['received'] / $monthlyCountMax) * $plotHeight) : 0;
                                    $resolvedHeight = $point['resolved'] > 0 ? (($point['resolved'] / $monthlyCountMax) * $plotHeight) : 0;
                                    $receivedY = $paddingTop + ($plotHeight - $receivedHeight);
                                    $resolvedY = $paddingTop + ($plotHeight - $resolvedHeight);
                                    $labelY = $paddingTop + $plotHeight + 14;
                                @endphp
                                <rect x="{{ $centerX - $barWidth - 2 }}" y="{{ $receivedY }}" width="{{ $barWidth }}" height="{{ max(1, $receivedHeight) }}" rx="2" fill="#0ea5e9"></rect>
                                <rect x="{{ $centerX + 2 }}" y="{{ $resolvedY }}" width="{{ $barWidth }}" height="{{ max(1, $resolvedHeight) }}" rx="2" fill="#10b981"></rect>
                                <text x="{{ $centerX }}" y="{{ $labelY }}" fill="#94a3b8" font-size="10" text-anchor="middle">{{ $point['label'] }}</text>
                            @endforeach
                        </svg>
                    </div>
                </div>
                <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Detailed Month</p>
                        <p class="mt-1 text-base font-semibold text-slate-900">{{ $selectedPerformancePoint['month_label'] ?? ($selectedMonthRow['month_label'] ?? '') }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Received</p>
                        <p class="mt-1 text-2xl font-semibold text-sky-600">{{ $selectedPerformancePoint['received'] ?? ($selectedMonthRow['received'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed</p>
                        <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ $selectedPerformancePoint['resolved'] ?? ($selectedMonthRow['resolved'] ?? 0) }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completion Rate</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format((float) ($selectedPerformancePoint['resolution_rate'] ?? ($selectedMonthRow['resolution_rate'] ?? 0)), 1) }}%</p>
                    </div>
                </div>
            </div>

            <div class="panel order-6 overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Monthly Statistics Detail</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="reports-month-table min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50/90">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Month</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Received</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Completed</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Open at Month End</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody class="app-table-body reports-month-table-body divide-y divide-slate-100 bg-white">
                            @foreach($monthlyReportRowsDescending as $row)
                                <tr class="reports-month-row {{ $row['month_key'] === $selectedMonthKey ? 'is-selected' : '' }}">
                                    <td class="px-4 py-3 font-medium text-slate-800">{{ $row['month_label'] }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $row['received'] }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $row['resolved'] }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ $row['open_end_of_month'] }}</td>
                                    <td class="px-4 py-3 text-slate-600">{{ number_format($row['resolution_rate'], 1) }}%</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Total Tickets</h2>
                </div>
                <div class="space-y-3 px-5 py-4">
                    <a href="{{ route('admin.tickets.index', ['tab' => 'tickets', 'include_closed' => 1]) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm transition hover:bg-slate-100">
                        <span class="text-slate-600 transition group-hover:text-slate-700">Total tickets created</span>
                        <span class="font-semibold text-slate-900">{{ $periodOverview['total_created'] }}</span>
                    </a>
                    <a href="{{ route('admin.tickets.index', ['tab' => 'tickets', 'status' => 'in_progress']) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm transition hover:bg-slate-100">
                        <span class="text-slate-600 transition group-hover:text-slate-700">Tickets in progress</span>
                        <span class="font-semibold text-slate-900">{{ $periodOverview['in_progress'] }}</span>
                    </a>
                    <a href="{{ route('admin.tickets.index', ['tab' => 'tickets', 'status' => 'pending']) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm transition hover:bg-slate-100">
                        <span class="text-slate-600 transition group-hover:text-slate-700">Tickets pending</span>
                        <span class="font-semibold text-slate-900">{{ $periodOverview['pending'] }}</span>
                    </a>
                    <a href="{{ route('admin.tickets.index', ['tab' => 'history', 'status' => 'resolved']) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm transition hover:bg-slate-100">
                        <span class="text-slate-600 transition group-hover:text-slate-700">Tickets resolved</span>
                        <span class="font-semibold text-slate-900">{{ $periodOverview['resolved'] }}</span>
                    </a>
                    <a href="{{ route('admin.tickets.index', ['tab' => 'history', 'status' => 'closed']) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm transition hover:bg-slate-100">
                        <span class="text-slate-600 transition group-hover:text-slate-700">Tickets closed</span>
                        <span class="font-semibold text-slate-900">{{ $periodOverview['closed'] }}</span>
                    </a>
                    <a href="{{ route('admin.tickets.index', ['tab' => 'tickets']) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm transition hover:bg-slate-100">
                        <span class="text-slate-600 transition group-hover:text-slate-700">Backlog (open tickets at period end)</span>
                        <span class="font-semibold text-slate-900">{{ $periodOverview['backlog_end'] }}</span>
                    </a>
                </div>
            </div>

            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Major Issues / Incidents</h2>
                </div>
                <div class="space-y-3 px-5 py-4">
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm"><span class="text-slate-600">Major issues this period</span><span class="font-semibold text-slate-900">{{ $majorIssueSummary['major_count'] ?? 0 }}</span></div>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm"><span class="text-slate-600">Still open</span><span class="font-semibold text-slate-900">{{ $majorIssueSummary['open_major_count'] ?? 0 }}</span></div>
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm"><span class="text-slate-600">Urgent incidents</span><span class="font-semibold text-slate-900">{{ $majorIssueSummary['urgent_total'] ?? 0 }}</span></div>
                    <div class="rounded-lg bg-slate-50 px-3 py-2">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Top issue groups</p>
                        <div class="mt-2 space-y-2">
                            @forelse(($majorIssueSummary['top_categories'] ?? collect()) as $category)
                                <div class="flex items-center justify-between text-sm">
                                    <span class="text-slate-600">{{ $category['name'] }}</span>
                                    <span class="font-semibold text-slate-900">{{ $category['count'] }}</span>
                                </div>
                            @empty
                                <p class="text-sm text-slate-500">No major incidents for this period.</p>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Operational KPIs</h2>
                </div>
                <div class="space-y-3 px-5 py-4">
                    <div class="dashboard-summary-link flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-600">Average resolution time</span>
                        <span class="font-semibold text-slate-900">{{ $averageResolutionLabel }}</span>
                    </div>
                    <div class="dashboard-summary-link flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-600">SLA compliance (this period)</span>
                        <span class="font-semibold text-slate-900">{{ number_format((float) ($periodOverview['sla_compliance_rate'] ?? 0), 1) }}%</span>
                    </div>
                    <div class="dashboard-summary-link flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-600">Open tickets (current)</span>
                        <span class="font-semibold text-slate-900">{{ $stats['open_tickets'] ?? 0 }}</span>
                    </div>
                    <div class="dashboard-summary-link flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-600">Unassigned open tickets</span>
                        <span class="font-semibold text-slate-900">{{ $stats['unassigned_open_tickets'] ?? 0 }}</span>
                    </div>
                    <div class="dashboard-summary-link flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                        <span class="text-slate-600">Urgent open tickets</span>
                        <span class="font-semibold text-slate-900">{{ $stats['urgent_open_tickets'] ?? 0 }}</span>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">By Category</h2>
                </div>
                <div class="space-y-3 px-5 py-4">
                    @foreach($categoryBreakdownBuckets as $bucket)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                            <span class="text-slate-600">{{ $bucket['name'] }}</span>
                            <span class="font-semibold text-slate-900">{{ $bucket['count'] }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">By Priority</h2>
                </div>
                <div class="space-y-3 px-5 py-4">
                    @foreach($priorityBreakdownBuckets as $bucket)
                        @php
                            $priorityFilter = match (strtolower((string) ($bucket['name'] ?? ''))) {
                                'critical' => 'urgent',
                                'high' => 'high',
                                'medium' => 'medium',
                                'low' => 'low',
                                default => null,
                            };
                        @endphp
                        <a href="{{ $priorityFilter ? route('admin.tickets.index', ['tab' => 'tickets', 'include_closed' => 1, 'priority' => $priorityFilter]) : route('admin.tickets.index', ['tab' => 'tickets', 'include_closed' => 1]) }}" class="dashboard-summary-link group flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm transition hover:bg-slate-100">
                            <span class="text-slate-600">{{ $bucket['name'] }}</span>
                            <span class="font-semibold text-slate-900">{{ $bucket['count'] }}</span>
                        </a>
                    @endforeach
                </div>
            </div>

            <div class="panel overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Top Technical Users</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($topTechnicians as $technician)
                        <div class="px-5 py-3">
                            <p class="text-sm font-semibold text-slate-900">{{ $technician['name'] }}</p>
                            <p class="mt-1 text-xs text-slate-500">{{ $technician['resolved_tickets'] }} completed of {{ $technician['total_tickets'] }} assigned</p>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-slate-500">No assigned technical tickets yet.</p>
                    @endforelse
                </div>
            </div>

            <div class="panel overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Category Breakdown (All Time)</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($ticketsByCategory as $category)
                        <div class="px-5 py-3">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-700">{{ $category['name'] }}</span>
                                <span class="text-slate-500">{{ $category['count'] }} tickets ({{ number_format($category['share'], 1) }}%)</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-[#0f8d88]" style="width: {{ max(2, $category['share']) }}%;"></div>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-slate-500">No ticket data is available for this period.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
</div>

<div id="volume-chart-modal" class="app-modal-root fixed inset-0 z-50 hidden">
    <div class="app-modal-overlay absolute inset-0 bg-slate-950/45 backdrop-blur-[1px]" data-modal-overlay="volume-chart"></div>
    <div class="relative z-10 flex min-h-screen items-center justify-center p-4">
        <div class="app-modal-panel w-full max-w-6xl rounded-2xl border border-slate-200 bg-white shadow-xl">
            <div class="flex items-center justify-between border-b border-slate-200 px-5 py-4">
                <h3 id="volume-chart-modal-title" class="font-display text-lg font-semibold text-slate-900">Volume Chart</h3>
                <button type="button" class="btn-secondary px-3 py-1.5 text-xs" data-modal-close="volume-chart">Close</button>
            </div>
            <div id="volume-chart-modal-content" class="max-h-[78vh] overflow-auto p-5"></div>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('volume-chart-modal');
    const modalTitle = document.getElementById('volume-chart-modal-title');
    const modalContent = document.getElementById('volume-chart-modal-content');
    const openButtons = document.querySelectorAll('.js-open-volume-chart');

    if (!modal || !modalTitle || !modalContent || openButtons.length === 0) {
        return;
    }

    const syncModalTheme = function () {
        const hasDarkTheme = Array.from(document.querySelectorAll('.theme-dark')).some(function (node) {
            return node !== modal && !modal.contains(node);
        });
        modal.classList.toggle('theme-dark', hasDarkTheme);
    };

    // Keep modal fixed to viewport even if the page container is transformed.
    if (modal.parentElement !== document.body) {
        document.body.appendChild(modal);
    }
    syncModalTheme();

    const modalController = window.ModalKit ? window.ModalKit.bind(modal) : null;
    const sourceChartHeight = 192; // h-48
    const enlargedChartHeight = 416; // h-[26rem]
    const barScale = enlargedChartHeight / sourceChartHeight;

    const fallbackOpen = function () {
        modal.classList.remove('hidden');
        requestAnimationFrame(function () {
            modal.classList.add('is-open');
        });
    };

    const fallbackClose = function () {
        modal.classList.remove('is-open');
        window.setTimeout(function () {
            modal.classList.add('hidden');
            modalContent.innerHTML = '';
        }, 180);
    };

    const tuneModalChartLayout = function (clone) {
        const chartArea = clone.querySelector('.h-48');
        if (chartArea) {
            chartArea.classList.remove('h-48', 'overflow-x-auto');
            chartArea.classList.add('h-[26rem]', 'overflow-hidden');
        }

        const pieCharts = clone.querySelectorAll('.js-total-pie-chart');
        pieCharts.forEach(function (pie) {
            pie.classList.remove('h-40', 'w-40', 'h-44', 'w-44');
            pie.classList.add('h-72', 'w-72');
        });

            const charts = clone.querySelectorAll('.js-volume-bars');
            charts.forEach(function (chart) {
                chart.classList.remove('min-w-[760px]', 'min-w-[720px]', 'pb-6');
                chart.classList.add('w-full', 'min-w-0', 'justify-between', 'pb-10');

                const groups = chart.querySelectorAll('.group');
                groups.forEach(function (group) {
                    group.classList.remove('min-w-[16px]', 'min-w-[34px]');
                    group.classList.add('flex-1', 'min-w-0');
                });

                const bars = chart.querySelectorAll('.js-volume-bar');
                bars.forEach(function (bar) {
                    const originalHeight = Number.parseFloat(bar.style.height || '0');
                    if (!Number.isFinite(originalHeight) || originalHeight <= 0) {
                        return;
                    }
                    const scaledHeight = Math.round(originalHeight * barScale);
                    bar.style.height = `${Math.min(340, Math.max(8, scaledHeight))}px`;
                });

                const labels = chart.querySelectorAll('span.text-\\[10px\\]');
                labels.forEach(function (label) {
                    label.classList.add('text-xs');
                });
            });
        };

    openButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            const sourceId = button.dataset.chartSource || '';
            const source = document.getElementById(sourceId);
            if (!source) {
                return;
            }

            syncModalTheme();
            modalTitle.textContent = button.dataset.chartTitle || 'Volume Chart';
            modalContent.innerHTML = '';

            const clone = source.cloneNode(true);
            clone.removeAttribute('id');
            tuneModalChartLayout(clone);

            modalContent.appendChild(clone);

            if (modalController) {
                modalController.open();
                return;
            }

            fallbackOpen();
        });
    });

    if (!modalController) {
        const closeButtons = modal.querySelectorAll('[data-modal-close="volume-chart"]');
        closeButtons.forEach(function (button) {
            button.addEventListener('click', fallbackClose);
        });

        const overlay = modal.querySelector('[data-modal-overlay="volume-chart"]');
        if (overlay) {
            overlay.addEventListener('click', fallbackClose);
        }

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape' && !modal.classList.contains('hidden')) {
                fallbackClose();
            }
        });
    }
});
</script>
@endpush
