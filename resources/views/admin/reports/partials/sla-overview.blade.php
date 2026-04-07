<details class="border-t border-slate-200 px-5 py-5 sm:px-6" data-reports-sla-details>
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
            <x-ui.stat-card label="First Response Time" :value="$slaReport['first_response']['average_display'] ?? 'No data'" value-class="text-sky-600" />
            <x-ui.stat-card label="Resolution Time" :value="$slaReport['resolution']['average_display'] ?? 'No data'" value-class="text-emerald-600" />
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
