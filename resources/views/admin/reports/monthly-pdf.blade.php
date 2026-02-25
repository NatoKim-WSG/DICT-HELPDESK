<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Monthly Ticket Report {{ $selectedMonthKey }}</title>
    <style>
        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            color: #0f172a;
            font-size: 12px;
            line-height: 1.4;
        }
        .header {
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 10px;
            margin-bottom: 14px;
        }
        .title {
            font-size: 20px;
            font-weight: 700;
            margin: 0;
        }
        .subtitle {
            margin: 2px 0 0;
            color: #475569;
        }
        .meta {
            margin-top: 8px;
            color: #475569;
            font-size: 11px;
        }
        .section {
            margin-top: 16px;
        }
        .section h2 {
            margin: 0 0 8px;
            font-size: 14px;
        }
        .metrics {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 12px;
        }
        .metrics td {
            border: 1px solid #cbd5e1;
            padding: 8px;
            width: 25%;
            vertical-align: top;
        }
        .metrics .label {
            color: #64748b;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .metrics .value {
            margin-top: 4px;
            font-size: 18px;
            font-weight: 700;
        }
        table.report {
            width: 100%;
            border-collapse: collapse;
        }
        table.report th,
        table.report td {
            border: 1px solid #cbd5e1;
            padding: 6px 8px;
            text-align: left;
        }
        table.report th {
            background: #f1f5f9;
            font-weight: 700;
            font-size: 11px;
        }
        .two-col {
            width: 100%;
            border-collapse: collapse;
        }
        .two-col td {
            width: 50%;
            vertical-align: top;
            padding-right: 8px;
        }
        .muted {
            color: #64748b;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1 class="title">Monthly Ticket Report</h1>
        <p class="subtitle">{{ $selectedMonthRow['month_label'] ?? $selectedMonthRange['label'] }}</p>
        <div class="meta">
            <div>Coverage: {{ $selectedMonthRange['start']->format('M d, Y') }} to {{ $selectedMonthRange['end']->format('M d, Y') }}</div>
            <div>Generated: {{ $generatedAt->format('M d, Y h:i A') }}</div>
        </div>
    </div>

    <table class="metrics">
        <tr>
            <td>
                <div class="label">Received</div>
                <div class="value">{{ $selectedMonthRow['received'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">Completed</div>
                <div class="value">{{ $selectedMonthRow['resolved'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">Open End Of Month</div>
                <div class="value">{{ $selectedMonthRow['open_end_of_month'] ?? 0 }}</div>
            </td>
            <td>
                <div class="label">Completion Rate</div>
                <div class="value">{{ number_format((float) ($selectedMonthRow['resolution_rate'] ?? 0), 1) }}%</div>
            </td>
        </tr>
    </table>

    <table class="two-col">
        <tr>
            <td>
                <div class="section">
                    <h2>Status Breakdown (Created In Month)</h2>
                    <table class="report">
                        <thead>
                            <tr>
                                <th>Status</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(['open', 'in_progress', 'pending', 'resolved', 'closed'] as $status)
                                <tr>
                                    <td>{{ ucfirst(str_replace('_', ' ', $status)) }}</td>
                                    <td>{{ $statusBreakdown[$status] ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </td>
            <td>
                <div class="section">
                    <h2>Priority Breakdown (Created In Month)</h2>
                    <table class="report">
                        <thead>
                            <tr>
                                <th>Priority</th>
                                <th>Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach(['urgent', 'high', 'medium', 'low'] as $priority)
                                <tr>
                                    <td>{{ ucfirst($priority) }}</td>
                                    <td>{{ $priorityBreakdown[$priority] ?? 0 }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <div class="section">
        <h2>Category Breakdown (Created In Month)</h2>
        <table class="report">
            <thead>
                <tr>
                    <th>Category</th>
                    <th>Count</th>
                    <th>Share</th>
                </tr>
            </thead>
            <tbody>
                @forelse($categoryBreakdown as $category)
                    <tr>
                        <td>{{ $category['name'] }}</td>
                        <td>{{ $category['count'] }}</td>
                        <td>{{ number_format($category['share'], 1) }}%</td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="3" class="muted">No tickets were created in this month.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="section">
        <h2>Last 12 Months Summary</h2>
        <table class="report">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Received</th>
                    <th>Completed</th>
                    <th>Open End Of Month</th>
                    <th>Completion Rate</th>
                </tr>
            </thead>
            <tbody>
                @foreach($monthlyReportRows as $row)
                    <tr>
                        <td>{{ $row['month_label'] }}</td>
                        <td>{{ $row['received'] }}</td>
                        <td>{{ $row['resolved'] }}</td>
                        <td>{{ $row['open_end_of_month'] }}</td>
                        <td>{{ number_format($row['resolution_rate'], 1) }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
