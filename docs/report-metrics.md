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

- Definition: the average/median minutes from `ticket created` to the first staff action.
- Staff action is the earliest of:
  - first public staff reply
  - first assignment timestamp
  - resolution timestamp
  - closure timestamp

### Resolution Time

- Definition: the average/median minutes from `ticket created` to `resolved_at` or `closed_at`.
- Only completed tickets are included in this metric.

### SLA Breach Rate

- Definition: tickets that reached or crossed `4 hours` before completion, or are still open after `4 hours`.
- Purpose: aligns with the current 4-hour reminder logic already used by ticket alert emails.

### Ticket Acknowledgment Rate

- Definition: tickets seen by a `super_user` within `1 hour` of creation.
- Source data: earliest `ticket_user_states.last_seen_at` for a super user.

### Customer Satisfaction SLA

- Definition: percentage of rated tickets with a client satisfaction score of `4/5` or higher.
- Also display the average rating for the rated sample.

### Severity Bands

- `Under 1 Hour`: still inside the super-user acknowledgment window
- `Severity 1`: `>= 1 hour` and `< 4 hours`
- `Severity 2`: `>= 4 hours` and `< 24 hours`
- `Severity 3`: `>= 24 hours`
