@extends('layouts.app')

@section('title', 'Reports - iOne Resources Inc.')

@section('content')
<div class="mx-auto max-w-[1460px] px-4 sm:px-6 lg:px-8">
    <div class="panel mb-6 overflow-hidden">
        <div class="flex flex-col gap-5 border-b border-slate-200 bg-slate-50 px-5 py-5 sm:px-6 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="font-display text-3xl font-semibold text-slate-900">Statistics & Reports</h1>
                <p class="mt-1 text-sm text-slate-500">Operational ticket metrics with monthly breakdown and downloadable report output.</p>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
                <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-end gap-2">
                    <div>
                        <label for="month" class="mb-1 block text-xs font-semibold uppercase tracking-wide text-slate-500">Detail Month</label>
                        <select id="month" name="month" onchange="this.form.submit()" class="form-input min-w-[170px] py-2 text-sm">
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

                <a
                    href="{{ route('admin.reports.monthly.pdf', ['month' => $selectedMonthKey]) }}"
                    class="btn-primary py-2"
                >
                    Download Monthly PDF
                </a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 px-5 py-5 md:grid-cols-2 xl:grid-cols-5 sm:px-6">
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total Tickets</p>
                <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ $stats['total_tickets'] }}</p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Open Tickets</p>
                <p class="mt-2 font-display text-3xl font-semibold text-sky-600">{{ $stats['open_tickets'] }}</p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Resolution Rate</p>
                <p class="mt-2 font-display text-3xl font-semibold text-emerald-600">{{ number_format($stats['resolution_rate'], 1) }}%</p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Avg. Completion Time</p>
                <p class="mt-2 font-display text-3xl font-semibold text-slate-900">{{ $stats['average_resolution_minutes'] }} min</p>
            </div>
            <div class="stat-card">
                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Unassigned Open</p>
                <p class="mt-2 font-display text-3xl font-semibold text-amber-600">{{ $stats['unassigned_open_tickets'] }}</p>
            </div>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        <div class="space-y-8 xl:col-span-2">
            <div class="panel p-5 sm:p-6">
                <div class="mb-4 flex items-center justify-between">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Ticket Volume (Last 30 Days)</h2>
                    <span class="rounded-full bg-slate-100 px-3 py-1 text-xs font-semibold text-slate-600">{{ $ticketTrend->sum('count') }} total</span>
                </div>
                @php
                    $maxTrendCount = max(1, $ticketTrend->max('count'));
                @endphp
                <div class="flex items-end gap-1 overflow-x-auto pb-2">
                    @foreach($ticketTrend as $point)
                        @php
                            $barHeightPercent = max(6, (int) round(($point['count'] / $maxTrendCount) * 100));
                        @endphp
                        <div class="group flex min-w-[22px] flex-col items-center justify-end gap-2">
                            <div class="w-full rounded-t bg-[#0f8d88]/85 transition group-hover:bg-[#0f8d88]" style="height: {{ $barHeightPercent }}px;" title="{{ $point['count'] }} tickets"></div>
                            <span class="text-[10px] text-slate-500">{{ \Illuminate\Support\Carbon::parse($point['date'])->format('m/d') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel p-5 sm:p-6">
                <div class="mb-4">
                    <h2 class="font-display text-xl font-semibold text-slate-900">Monthly Performance (Last 12 Months)</h2>
                    <p class="mt-1 text-sm text-slate-500">Received, completed (resolved/closed), and completion rate per month.</p>
                </div>
                @php
                    $maxMonthlyCount = max(1, $monthlyGraphPoints->max(fn ($point) => max($point['received'], $point['resolved'])));
                    $resolutionSeries = $monthlyGraphPoints->values();
                    $graphHeight = 140;
                    $graphWidth = 600;
                    $xStep = $resolutionSeries->count() > 1 ? ($graphWidth / ($resolutionSeries->count() - 1)) : $graphWidth;
                    $linePoints = $resolutionSeries->map(function ($point, $index) use ($xStep, $graphHeight) {
                        $x = (int) round($index * $xStep);
                        $y = (int) round($graphHeight - (($point['resolution_rate'] / 100) * $graphHeight));
                        return $x . ',' . $y;
                    })->implode(' ');
                @endphp

                <div class="mb-6 flex items-end gap-2 overflow-x-auto pb-2">
                    @foreach($monthlyGraphPoints as $point)
                        @php
                            $receivedHeight = max(6, (int) round(($point['received'] / $maxMonthlyCount) * 100));
                            $resolvedHeight = max(6, (int) round(($point['resolved'] / $maxMonthlyCount) * 100));
                        @endphp
                        <div class="flex min-w-[50px] flex-col items-center gap-1">
                            <div class="flex h-[110px] items-end gap-1">
                                <div class="w-3 rounded-t bg-sky-500" style="height: {{ $receivedHeight }}px;" title="Received: {{ $point['received'] }}"></div>
                                <div class="w-3 rounded-t bg-emerald-500" style="height: {{ $resolvedHeight }}px;" title="Completed: {{ $point['resolved'] }}"></div>
                            </div>
                            <span class="text-[10px] font-medium text-slate-500">{{ $point['label'] }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="rounded-xl border border-slate-200 bg-slate-50 p-3">
                    <div class="mb-2 flex items-center gap-4 text-xs text-slate-600">
                        <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-sky-500"></span> Received</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span> Completed</span>
                        <span class="inline-flex items-center gap-1"><span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span> Completion Rate</span>
                    </div>
                    <div class="overflow-x-auto">
                        <svg viewBox="0 0 {{ $graphWidth }} {{ $graphHeight }}" class="h-36 min-w-[600px] w-full">
                            <line x1="0" y1="{{ $graphHeight }}" x2="{{ $graphWidth }}" y2="{{ $graphHeight }}" stroke="#cbd5e1" stroke-width="1"></line>
                            <polyline points="{{ $linePoints }}" fill="none" stroke="#f59e0b" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"></polyline>
                            @foreach($resolutionSeries as $pointIndex => $point)
                                @php
                                    $dotX = (int) round($pointIndex * $xStep);
                                    $dotY = (int) round($graphHeight - (($point['resolution_rate'] / 100) * $graphHeight));
                                @endphp
                                <circle cx="{{ $dotX }}" cy="{{ $dotY }}" r="4" fill="#f59e0b"></circle>
                            @endforeach
                        </svg>
                    </div>
                </div>
            </div>

            <div class="panel overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Selected Month Details ({{ $selectedMonthRow['month_label'] ?? '' }})</h2>
                </div>
                <div class="grid grid-cols-1 gap-4 px-5 py-4 sm:grid-cols-2 xl:grid-cols-4">
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Received</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ $selectedMonthRow['received'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completed</p>
                        <p class="mt-1 text-2xl font-semibold text-emerald-600">{{ $selectedMonthRow['resolved'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Open at End</p>
                        <p class="mt-1 text-2xl font-semibold text-amber-600">{{ $selectedMonthRow['open_end_of_month'] ?? 0 }}</p>
                    </div>
                    <div class="rounded-xl border border-slate-200 bg-slate-50 px-4 py-3">
                        <p class="text-xs font-semibold uppercase tracking-wide text-slate-500">Completion Rate</p>
                        <p class="mt-1 text-2xl font-semibold text-slate-900">{{ number_format((float) ($selectedMonthRow['resolution_rate'] ?? 0), 1) }}%</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 border-t border-slate-100 px-5 py-4 md:grid-cols-2">
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-slate-800">Status (Created in Month)</h3>
                        <div class="space-y-2">
                            @foreach(['open', 'in_progress', 'pending', 'resolved', 'closed'] as $status)
                                <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                    <span class="text-slate-600">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                                    <span class="font-semibold text-slate-900">{{ $selectedMonthStatuses[$status] ?? 0 }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                    <div>
                        <h3 class="mb-2 text-sm font-semibold text-slate-800">Priority (Created in Month)</h3>
                        <div class="space-y-2">
                            @foreach(['urgent', 'high', 'medium', 'low'] as $priority)
                                <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                                    <span class="text-slate-600">{{ ucfirst($priority) }}</span>
                                    <span class="font-semibold text-slate-900">{{ $selectedMonthPriorities[$priority] ?? 0 }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel overflow-hidden">
                <div class="flex items-center justify-between border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Monthly Statistics Detail</h2>
                    <a href="{{ route('admin.reports.monthly.pdf', ['month' => $selectedMonthKey]) }}" class="btn-secondary py-2 text-xs">Download Selected PDF</a>
                </div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200 text-sm">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Month</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Received</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Completed</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Open at Month End</th>
                                <th class="px-4 py-3 text-left font-semibold text-slate-600">Completion Rate</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            @foreach($monthlyReportRowsDescending as $row)
                                <tr class="{{ $row['month_key'] === $selectedMonthKey ? 'bg-sky-50/60' : '' }}">
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
                    <h2 class="font-display text-lg font-semibold text-slate-900">Status Summary (All Time)</h2>
                </div>
                <div class="space-y-3 px-5 py-4">
                    @foreach(['open', 'in_progress', 'pending', 'resolved', 'closed'] as $status)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                            <span class="text-slate-600">{{ ucfirst(str_replace('_', ' ', $status)) }}</span>
                            <span class="font-semibold text-slate-900">{{ $ticketsByStatus->get($status, 0) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Priority Summary (All Time)</h2>
                </div>
                <div class="space-y-3 px-5 py-4">
                    @foreach(['urgent', 'high', 'medium', 'low'] as $priority)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-2 text-sm">
                            <span class="text-slate-600">{{ ucfirst($priority) }}</span>
                            <span class="font-semibold text-slate-900">{{ $ticketsByPriority->get($priority, 0) }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="panel overflow-hidden">
                <div class="border-b border-slate-100 px-5 py-4">
                    <h2 class="font-display text-lg font-semibold text-slate-900">Selected Month Categories</h2>
                </div>
                <div class="divide-y divide-slate-100">
                    @forelse($selectedMonthCategories as $category)
                        <div class="px-5 py-3">
                            <div class="mb-2 flex items-center justify-between text-sm">
                                <span class="font-medium text-slate-700">{{ $category['name'] }}</span>
                                <span class="text-slate-500">{{ $category['count'] }} ({{ number_format($category['share'], 1) }}%)</span>
                            </div>
                            <div class="h-2 w-full rounded-full bg-slate-100">
                                <div class="h-2 rounded-full bg-[#0f8d88]" style="width: {{ max(2, $category['share']) }}%;"></div>
                            </div>
                        </div>
                    @empty
                        <p class="px-5 py-8 text-sm text-slate-500">No tickets were created in the selected month.</p>
                    @endforelse
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
@endsection
