<div class="panel mb-6 overflow-hidden">
    <div class="flex flex-col gap-5 border-b border-slate-200 bg-slate-50 px-5 py-5 sm:px-6 lg:flex-row lg:items-end lg:justify-between">
        <div>
            <h1 class="font-display text-3xl font-semibold text-slate-900">Statistics & Reports</h1>
        </div>

        <div class="flex flex-col gap-2 sm:flex-row sm:items-end">
            <form method="GET" action="{{ route('admin.reports.index') }}" class="flex items-end gap-2" data-submit-feedback data-reports-filter-form>
                <input type="hidden" name="daily_month" value="{{ $dailyMonthKey }}">
                <input type="hidden" name="daily_date" value="{{ $dailySelectedDateValue }}">
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
            Severity 1 tickets created this period
        </x-ui.stat-card>
        <x-ui.stat-card label="Resolution Rate" :value="number_format((float) ($selectedMonthRow['resolution_rate'] ?? 0), 1) . '%'" value-class="text-sky-600">
            Tickets created in the selected scope that are now resolved/closed
        </x-ui.stat-card>
    </div>

    @include('admin.reports.partials.sla-overview')
</div>
