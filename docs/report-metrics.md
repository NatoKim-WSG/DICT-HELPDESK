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

## SLA Overview

- Source area: `App\Http\Controllers\Admin\ReportController` via `App\Services\Admin\Reports\SlaReportService`
- Scope: tickets created inside the selected reports scope (`selected month`, or `detail filter` when applied)

### First Response Time

- Definition: percentage of tickets acknowledged by a `super_user` within `1 hour` of creation.
- Source data: earliest `ticket_user_states.acknowledged_at` for a super user.
- Current reports UI: shows the percent reviewed under `1 hour` for the selected scope.

### Resolution Time

- Definition: percentage of completed tickets resolved inside `Severity 1` (`< 4 hours`).
- Completion uses `resolved_at`, with `closed_at` as a fallback when `resolved_at` is missing.
- Current reports UI: shows the percent of completed tickets resolved inside `Severity 1`.

### SLA Breach Rate

- Definition: tickets that reached or crossed `4 hours` before completion, or are still open after `4 hours`.
- Purpose: aligns with the current 4-hour reminder logic already used by ticket alert emails.

### Ticket Acknowledgment Rate

- Definition: tickets acknowledged by a `super_user` within `1 hour` of creation.
- Source data: earliest `ticket_user_states.acknowledged_at` for a super user.

### Customer Satisfaction SLA

- Definition: percentage of rated tickets with a client satisfaction score of `4/5` or higher.
- Current reports UI: shows the SLA percentage only.

### Severity Bands

- Scope: completed tickets only
- `Severity 1`: `< 4 hours`
- `Severity 2`: `>= 4 hours` and `< 24 hours`
- `Severity 3`: `>= 24 hours`
- Current reports UI: shows the percentage distribution across completed tickets, with counts as supporting detail.
