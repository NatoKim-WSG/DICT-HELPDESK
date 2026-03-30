<div data-admin-reports-shell>
@php
    $monthlyPerformanceSeries = ($monthlyPerformanceGraphPoints ?? $monthlyGraphPoints)->values();
    $monthlyPerformanceFocusMonthKey = $monthlyPerformanceFocusMonthKey ?? $selectedMonthKey;
    $monthlyCountMax = max(1, (int) $monthlyPerformanceSeries->max(fn ($point) => max((int) $point['received'], (int) $point['resolved'])));
    $chartHeight = 320;
    $chartWidth = max(760, ($monthlyPerformanceSeries->count() * 70));
    $paddingLeft = 44;
    $paddingRight = 24;
    $paddingTop = 18;
    $paddingBottom = 52;
    $plotWidth = max(1, $chartWidth - $paddingLeft - $paddingRight);
    $plotHeight = max(1, $chartHeight - $paddingTop - $paddingBottom);
    $step = $monthlyPerformanceSeries->count() > 0 ? ($plotWidth / $monthlyPerformanceSeries->count()) : $plotWidth;
    $selectedPerformancePoint = $monthlyPerformanceSeries->firstWhere('key', $monthlyPerformanceFocusMonthKey)
        ?? $monthlyPerformanceSeries->last();

    $pieTotalCreated = (int) ($ticketsBreakdownOverview['total_created'] ?? 0);
    $pieResolved = (int) ($ticketsBreakdownOverview['resolved'] ?? 0);
    $pieClosed = (int) ($ticketsBreakdownOverview['closed'] ?? 0);
    $pieResolvedOnly = max($pieResolved - $pieClosed, 0);
    $ticketPieSlices = [
        ['label' => 'Open', 'count' => (int) ($ticketsBreakdownOverview['open'] ?? 0), 'display_count' => (int) ($ticketsBreakdownOverview['open'] ?? 0), 'color' => '#8b5cf6', 'tab' => 'tickets', 'status' => 'open'],
        ['label' => 'In Progress', 'count' => (int) ($ticketsBreakdownOverview['in_progress'] ?? 0), 'display_count' => (int) ($ticketsBreakdownOverview['in_progress'] ?? 0), 'color' => '#0ea5e9', 'tab' => 'tickets', 'status' => 'in_progress'],
        ['label' => 'Pending', 'count' => (int) ($ticketsBreakdownOverview['pending'] ?? 0), 'display_count' => (int) ($ticketsBreakdownOverview['pending'] ?? 0), 'color' => '#f59e0b', 'tab' => 'tickets', 'status' => 'pending'],
        ['label' => 'Resolved', 'count' => $pieResolvedOnly, 'display_count' => $pieResolved, 'color' => '#10b981', 'tab' => 'history', 'status' => null],
        ['label' => 'Closed', 'count' => $pieClosed, 'display_count' => $pieClosed, 'color' => '#64748b', 'tab' => 'history', 'status' => 'closed'],
    ];
    $ticketPieTotal = max(0, (int) collect($ticketPieSlices)->sum('count'));
    $mixScopeLabel = (string) ($ticketsBreakdownOverview['label'] ?? 'All Time');
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
        'pending review' => '#64748b',
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
    $ticketHistoryScopeParams = collect($ticketHistoryScope ?? [])
        ->filter(fn ($value) => $value !== null && $value !== '')
        ->all();
    $formatMinutes = function (?float $minutes): string {
        if ($minutes === null) {
            return 'N/A';
        }

        $roundedMinutes = (int) round($minutes);
        if ($roundedMinutes < 60) {
            return $roundedMinutes.'m';
        }

        $hours = intdiv($roundedMinutes, 60);
        $remainingMinutes = $roundedMinutes % 60;

        if ($hours < 24) {
            return $remainingMinutes > 0
                ? $hours.'h '.$remainingMinutes.'m'
                : $hours.'h';
        }

        $days = intdiv($hours, 24);
        $remainingHours = $hours % 24;

        return $remainingHours > 0
            ? $days.'d '.$remainingHours.'h'
            : $days.'d';
    };
@endphp

<div class="mx-auto max-w-[1760px] px-4 sm:px-6 lg:px-8" data-admin-reports-page data-route-base="{{ route('admin.reports.index', absolute: false) }}">
    <div class="panel mb-6 overflow-hidden">
        <div class="flex flex-col gap-5 border-b border-slate-200 bg-slate-50 px-5 py-5 sm:px-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="font-display text-3xl font-semibold text-slate-900">Statistics & Reports</h1>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-end gap-2" data-submit-feedback data-reports-filter-form>
                    @if($detailFilterApplied)
                        <input type="hidden" name="apply_details_filter" value="1">
                    @endif
                    <input type="hidden" name="daily_month" value="{{ $dailyMonthKey }}">
                    <input type="hidden" name="daily_date" value="{{ $dailySelectedDateValue }}">
                    <input type="hidden" name="detail_month" value="{{ $detailMonthKey }}">
                    <input type="hidden" name="detail_date" value="{{ $detailDateValue }}">
                    <div>
                        <label for="month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Reporting Period</label>
                        <select id="month" name="month" data-auto-submit-change class="form-input min-w-[190px] py-2 text-sm">
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

                @unless($selectedMonthIsAllTime ?? false)
                    <a href="{{ route('admin.reports.monthly.pdf', ['month' => $selectedMonthKey]) }}" class="btn-primary py-2">
                        Download Monthly PDF
                    </a>
                @endunless
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 px-5 py-5 md:grid-cols-2 xl:grid-cols-3 sm:px-6">
            <x-ui.stat-card label="Total Tickets in Scope" :value="$periodOverview['total_tickets']">
                {{ $periodOverview['label'] }}
            </x-ui.stat-card>
            <x-ui.stat-card label="Major Issues / Incidents" :value="$majorIssueSummary['major_count'] ?? 0" value-class="text-rose-600">
                High or urgent tickets created this period
            </x-ui.stat-card>
            <x-ui.stat-card label="Resolution Rate" :value="number_format((float) ($selectedMonthRow['resolution_rate'] ?? 0), 1) . '%'" value-class="text-sky-600">
                Tickets created in the selected scope that are now resolved/closed
            </x-ui.stat-card>
        </div>

        <details class="border-t border-slate-200 px-5 py-5 sm:px-6">
            <summary class="flex cursor-pointer list-none flex-col gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-4 marker:hidden transition hover:border-slate-300 hover:bg-slate-100 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <h2 class="font-display text-xl font-semibold text-slate-900">SLA Overview</h2>
                </div>
                <div class="flex items-center gap-3">
                    <svg class="h-5 w-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="m6 9 6 6 6-6" />
                    </svg>
                </div>
            </summary>

            <div class="mt-4">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <x-ui.stat-card label="First Response Time" :value="$formatMinutes($slaReport['first_response']['average_minutes'])" value-class="text-sky-600" />
                    <x-ui.stat-card label="Resolution Time" :value="$formatMinutes($slaReport['resolution']['average_minutes'])" value-class="text-emerald-600" />
                    <x-ui.stat-card label="SLA Breach Rate" :value="number_format((float) ($slaReport['breach_rate']['rate'] ?? 0), 1) . '%'" value-class="text-rose-600" />
                    <x-ui.stat-card label="Acknowledgment Rate" :value="number_format((float) ($slaReport['acknowledgment_rate']['rate'] ?? 0), 1) . '%'" value-class="text-amber-600" />
                    <x-ui.stat-card label="Customer Satisfaction SLA" :value="number_format((float) ($slaReport['customer_satisfaction']['rate'] ?? 0), 1) . '%'" value-class="text-fuchsia-600" />
                </div>

                <div class="mt-5 rounded-2xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-3 flex items-center justify-between gap-3">
                        <div>
                            <h3 class="font-display text-lg font-semibold text-slate-900">Severity Bands</h3>
                        </div>
                        <span class="text-xs text-slate-500">{{ $slaReport['label'] }}</span>
                    </div>
                    <div class="grid grid-cols-1 gap-3 md:grid-cols-2 xl:grid-cols-4">
                        @foreach($slaReport['severity_bands'] as $band)
                            <div class="rounded-xl border border-slate-200 bg-white px-4 py-3">
                                <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $band['label'] }}</p>
                                <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $band['count'] }}</p>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </details>
    </div>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        <div class="flex flex-col gap-8 xl:col-span-2">
            <div class="panel order-3 p-5 sm:p-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-semibold text-slate-900">Total Tickets Breakdown</h2>
                    </div>
                    <button type="button" class="btn-secondary py-2 text-xs js-open-volume-chart" data-chart-title="Total Tickets Breakdown (Detailed View)" data-chart-source="total-tickets-breakdown-chart">
                        Enlarge
                    </button>
                </div>
                <div id="total-tickets-breakdown-chart" class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Total Tickets</p>
                        <span class="text-xs text-slate-500">{{ $mixScopeLabel }} · {{ $pieTotalCreated }} total</span>
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
                                    $sliceCount = (int) ($slice['display_count'] ?? $slice['count'] ?? 0);
                                    $statusFilter = $slice['status'] ?? null;
                                    $statusTab = $slice['tab'] ?? 'tickets';
                                    $statusLinkParams = array_merge($ticketHistoryScopeParams, ['tab' => $statusTab]);
                                    if ($statusFilter !== null) {
                                        $statusLinkParams['status'] = $statusFilter;
                                    }
                                    $statusLink = route('admin.tickets.index', $statusLinkParams);
                                @endphp
                                <a href="{{ $statusLink }}" class="pie-legend-row group flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2 text-sm transition hover:bg-slate-200">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-2.5 w-2.5" viewBox="0 0 10 10" aria-hidden="true">
                                            <circle cx="5" cy="5" r="5" fill="{{ $slice['color'] }}"></circle>
                                        </svg>
                                        <span class="pie-legend-text text-slate-600">{{ $slice['label'] }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="pie-legend-value font-semibold text-slate-900">{{ $sliceCount }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel order-4 p-5 sm:p-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-semibold text-slate-900">Category Mix</h2>
                    </div>
                    <button type="button" class="btn-secondary py-2 text-xs js-open-volume-chart" data-chart-title="Category Mix (Detailed View)" data-chart-source="category-mix-chart">
                        Enlarge
                    </button>
                </div>
                <div id="category-mix-chart" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">By Category</p>
                        <span class="text-xs text-slate-500">{{ $mixScopeLabel }} · {{ $categoryPieTotal }} total</span>
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
                                    $categoryBucket = strtolower(str_replace([' / ', ' '], ['_', '_'], (string) ($slice['label'] ?? 'other')));
                                    $categoryLink = route('admin.tickets.index', array_merge($ticketHistoryScopeParams, [
                                        'tab' => 'all',
                                        'category_bucket' => $categoryBucket,
                                    ]));
                                @endphp
                                <a href="{{ $categoryLink }}" class="pie-legend-row group flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2 text-sm transition">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-2.5 w-2.5" viewBox="0 0 10 10" aria-hidden="true">
                                            <circle cx="5" cy="5" r="5" fill="{{ $slice['color'] }}"></circle>
                                        </svg>
                                        <span class="pie-legend-text text-slate-600">{{ $slice['label'] }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="pie-legend-value font-semibold text-slate-900">{{ $slice['count'] }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel order-5 p-5 sm:p-6">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h2 class="font-display text-xl font-semibold text-slate-900">Priority Mix</h2>
                    </div>
                    <button type="button" class="btn-secondary py-2 text-xs js-open-volume-chart" data-chart-title="Priority Mix (Detailed View)" data-chart-source="priority-mix-chart">
                        Enlarge
                    </button>
                </div>
                <div id="priority-mix-chart" class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 flex items-center justify-between">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">By Priority</p>
                        <span class="text-xs text-slate-500">{{ $mixScopeLabel }} · {{ $priorityPieTotal }} total</span>
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
                                    $priorityFilter = match (strtolower((string) ($slice['label'] ?? ''))) {
                                        'pending review' => 'unassigned',
                                        'critical' => 'urgent',
                                        'high' => 'high',
                                        'medium' => 'medium',
                                        'low' => 'low',
                                        default => null,
                                    };
                                    $priorityLink = $priorityFilter
                                        ? route('admin.tickets.index', array_merge($ticketHistoryScopeParams, ['tab' => 'all', 'priority' => $priorityFilter]))
                                        : route('admin.tickets.index', array_merge($ticketHistoryScopeParams, ['tab' => 'all']));
                                @endphp
                                <a href="{{ $priorityLink }}" class="pie-legend-row group flex items-center justify-between rounded-lg bg-slate-100 px-3 py-2 text-sm transition">
                                    <div class="flex items-center gap-2">
                                        <svg class="h-2.5 w-2.5" viewBox="0 0 10 10" aria-hidden="true">
                                            <circle cx="5" cy="5" r="5" fill="{{ $slice['color'] }}"></circle>
                                        </svg>
                                        <span class="pie-legend-text text-slate-600">{{ $slice['label'] }}</span>
                                    </div>
                                    <div class="text-right">
                                        <span class="pie-legend-value font-semibold text-slate-900">{{ $slice['count'] }}</span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel order-2 p-5 sm:p-6">
                <div class="mb-4 flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 class="font-display text-xl font-semibold text-slate-900">Monthly Performance (Last 12 Months)</h2>
                    </div>
                    <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-end gap-2" data-submit-feedback data-reports-filter-form>
                        @if($detailFilterApplied)
                            <input type="hidden" name="apply_details_filter" value="1">
                        @endif
                        <input type="hidden" name="daily_month" value="{{ $dailyMonthKey }}">
                        <input type="hidden" name="daily_date" value="{{ $dailySelectedDateValue }}">
                        <input type="hidden" name="detail_month" value="{{ $detailMonthKey }}">
                        <input type="hidden" name="detail_date" value="{{ $detailDateValue }}">
                        <div>
                            <label for="monthly-focus-month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Focus Month</label>
                                <select id="monthly-focus-month" name="month" data-auto-submit-change class="form-input min-w-[190px] py-2 text-sm">
                                    @foreach($monthOptions->where('key', '!=', 'all') as $option)
                                        <option value="{{ $option['key'] }}" {{ $monthlyPerformanceFocusMonthKey === $option['key'] ? 'selected' : '' }}>
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

            <div class="panel order-1 overflow-visible">
                <div class="border-b border-slate-100 px-5 py-4">
                    <div class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-start xl:gap-6">
                        <div>
                            <h2 class="font-display text-lg font-semibold text-slate-900">Daily Ticket Statistics</h2>
                        </div>
                        <form
                            method="GET"
                            action="{{ route('admin.reports.index') }}"
                            class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:min-w-[460px]"
                            data-submit-feedback
                            data-reports-filter-form
                        >
                            <input type="hidden" name="month" value="{{ $selectedMonthKey }}">
                            <input type="hidden" name="detail_month" value="{{ $detailMonthKey }}">
                            <input type="hidden" name="detail_date" value="{{ $detailDateValue }}">
                            @if($detailFilterApplied)
                                <input type="hidden" name="apply_details_filter" value="1">
                            @endif
                            <div class="min-w-0">
                                <label for="daily-month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Month</label>
                                <select id="daily-month" name="daily_month" data-auto-submit-change class="form-input mt-0 w-full py-2 text-sm">
                                    @foreach($monthOptions->where('key', '!=', 'all') as $option)
                                        <option value="{{ $option['key'] }}" {{ $dailyMonthKey === $option['key'] ? 'selected' : '' }}>
                                            {{ $option['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="min-w-0">
                                <label for="daily-date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Day</label>
                                <select id="daily-date" name="daily_date" data-auto-submit-change class="form-input mt-0 w-full py-2 text-sm">
                                    <option value="all" {{ $dailySelectedDateValue === 'all' ? 'selected' : '' }}>
                                        All days in {{ $monthOptions->firstWhere('key', $dailyMonthKey)['label'] ?? 'selected month' }}
                                    </option>
                                    @foreach($dailyDateOptions as $option)
                                        <option value="{{ $option['value'] }}" {{ $dailySelectedDateValue === $option['value'] ? 'selected' : '' }}>
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
                </div>
                <div class="grid grid-cols-1 gap-3 p-5 sm:grid-cols-2 xl:grid-cols-4 sm:p-6">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Received</p>
                        <p class="mt-1 text-2xl font-semibold text-sky-600">{{ $dailySelectedStats['received'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $dailySelectedStats['label'] }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">In Progress</p>
                        <p class="mt-1 text-2xl font-semibold text-amber-600">{{ $dailySelectedStats['in_progress'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $dailySelectedStats['label'] }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Resolved</p>
                        <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ $dailySelectedStats['resolved'] }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $dailySelectedStats['label'] }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Closed</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-700">{{ $dailySelectedStats['closed'] ?? 0 }}</p>
                        <p class="mt-1 text-xs text-slate-500">{{ $dailySelectedStats['label'] }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="space-y-8">
            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Details Filter</h2>
                </div>
                <form method="GET" action="{{ route('admin.reports.index') }}" class="grid grid-cols-1 gap-3 px-5 py-4 sm:grid-cols-2" data-submit-feedback data-reports-filter-form>
                    <input type="hidden" name="month" value="{{ $selectedMonthKey }}">
                    <input type="hidden" name="daily_month" value="{{ $dailyMonthKey }}">
                    <input type="hidden" name="daily_date" value="{{ $dailySelectedDateValue }}">
                    <div>
                        <label for="detail-month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Month</label>
                        <select id="detail-month" name="detail_month" class="form-input w-full py-2 text-sm">
                            @foreach($monthOptions->where('key', '!=', 'all') as $option)
                                <option value="{{ $option['key'] }}" {{ $detailMonthKey === $option['key'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="detail-date" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Day (Optional)</label>
                        <select id="detail-date" name="detail_date" class="form-input w-full py-2 text-sm">
                            <option value="">All days in {{ $monthOptions->firstWhere('key', $detailMonthKey)['label'] ?? 'selected month' }}</option>
                            @foreach($detailDateOptions as $option)
                                <option value="{{ $option['value'] }}" {{ $detailDateValue === $option['value'] ? 'selected' : '' }}>
                                    {{ $option['label'] }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                    <div class="sm:col-span-2">
                        <div class="flex items-center gap-2">
                            <button type="submit" name="apply_details_filter" value="1" class="btn-primary py-2 text-sm">Filter</button>
                            <a href="{{ route('admin.reports.index') }}" class="btn-secondary py-2 text-sm" data-admin-reports-clear>Clear</a>
                        </div>
                    </div>
                </form>
            </div>

            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Operational KPIs</h2>
                </div>
                <div class="space-y-3 px-5 py-4">
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
                                <span class="text-slate-500">{{ $category['count'] }} tickets</span>
                            </div>
                            <svg viewBox="0 0 100 8" class="h-2 w-full" role="img" aria-label="{{ $category['name'] }} category bar">
                                <rect x="0" y="0" width="100" height="8" rx="4" fill="#e2e8f0"></rect>
                                <rect x="0" y="0" width="{{ max(2, $category['share']) }}" height="8" rx="4" fill="#0f8d88"></rect>
                            </svg>
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
</div>

