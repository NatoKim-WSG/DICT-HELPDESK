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
                <span class="text-slate-600">Severity 1 open tickets</span>
                <span class="font-semibold text-slate-900">{{ $stats['severity_one_open_tickets'] ?? 0 }}</span>
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
