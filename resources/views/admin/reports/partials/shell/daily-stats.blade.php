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
