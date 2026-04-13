<div data-admin-reports-shell>
@php
    $ticketPie = $reportVisuals['ticket_pie'];
    $categoryPie = $reportVisuals['category_pie'];
    $priorityPie = $reportVisuals['priority_pie'];
    $monthlyPerformance = $reportVisuals['monthly_performance'];
    $mixScopeLabel = $reportVisuals['mix_scope_label'];
    $pieRadius = $reportVisuals['pie_radius'];
    $pieCircumference = $reportVisuals['pie_circumference'];
    $pieTotalCreated = (int) ($ticketsBreakdownOverview['total_created'] ?? 0);
@endphp

<div class="mx-auto max-w-[1760px] px-4 sm:px-6 lg:px-8" data-admin-reports-page data-route-base="{{ route('admin.reports.index', absolute: false) }}">
    @include('admin.reports.partials.shell.period-summary')

    <div class="grid grid-cols-1 gap-8 xl:grid-cols-3">
        <div class="flex flex-col gap-8 xl:col-span-2">
            @include('admin.reports.partials.shell.ticket-mix-panels')
            @include('admin.reports.partials.shell.monthly-performance')
            @include('admin.reports.partials.shell.daily-stats')
        </div>

        @include('admin.reports.partials.shell.sidebar')
    </div>
</div>

@include('admin.reports.partials.shell.volume-chart-modal')
</div>
