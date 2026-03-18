# Report Metric Definitions

This document explains the business meaning of the report metrics so future UI or query changes do not silently shift how people interpret the numbers.

## Monthly Completion Rate

- Source area: `App\Http\Controllers\Admin\ReportController` via `App\Services\Admin\Reports\MonthlyReportDatasetService`
- Definition: `tickets created in the month that are now resolved/closed / total tickets created in the month`
- Important nuance: the ticket is credited to its **creation month**, not the month it was resolved.

Example:

- A ticket created on `June 24, 2025` and resolved on `July 16, 2025` still contributes to the **June 2025** completion rate.
- It does **not** increase June's monthly `resolved` count, because `resolved` is still tracked by the month the completion timestamp happened.

## Monthly Resolved Count

- Definition: tickets with `resolved_at` or `closed_at` inside the selected month.
- Purpose: measures operational throughput by completion month.

## Open Backlog (Month End)

- Definition: tickets created on or before the month-end cutoff that were still not resolved/closed at that point in time.
- Purpose: historical snapshot of open workload at period end.

## Resolution Rate Card Copy

The reports page and PDF should describe the metric as:

- "Tickets created this period that are now resolved/closed"

That wording matches the current report calculation and avoids confusing it with same-month throughput.
