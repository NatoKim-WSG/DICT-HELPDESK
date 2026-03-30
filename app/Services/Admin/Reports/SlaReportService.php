<?php

namespace App\Services\Admin\Reports;

use App\Models\Ticket;
use App\Models\TicketReply;
use App\Models\TicketUserState;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class SlaReportService
{
    public const ACKNOWLEDGMENT_TARGET_MINUTES = 60;

    public const RESOLUTION_TARGET_MINUTES = 240;

    public const SATISFACTION_TARGET_RATING = 4;

    public const SEVERITY_ONE_MINUTES = 60;

    public const SEVERITY_TWO_MINUTES = 240;

    public const SEVERITY_THREE_MINUTES = 1440;

    public function build(Builder $scopedTickets, Carbon $start, Carbon $end, string $label): array
    {
        $now = now();
        $ticketRows = collect($this->ticketRows(clone $scopedTickets, $start, $end, $now));
        $ticketCount = $ticketRows->count();

        $firstResponseMinutes = $ticketRows
            ->pluck('first_response_minutes')
            ->filter(static fn ($value) => $value !== null)
            ->values();

        $resolutionMinutes = $ticketRows
            ->pluck('resolution_minutes')
            ->filter(static fn ($value) => $value !== null)
            ->values();

        $ratedTickets = $ticketRows
            ->filter(static fn (array $row): bool => $row['satisfaction_rating'] !== null)
            ->values();

        $acknowledgedWithinTarget = $ticketRows
            ->filter(static fn (array $row): bool => $row['acknowledged_within_target'])
            ->count();

        $breachedTickets = $ticketRows
            ->filter(static fn (array $row): bool => $row['breached_resolution_target'])
            ->count();

        $severityBands = [
            [
                'label' => 'Under 1 Hour',
                'count' => $ticketRows->filter(static fn (array $row): bool => $row['severity_band'] === 'under_1_hour')->count(),
            ],
            [
                'label' => 'Severity 1',
                'count' => $ticketRows->filter(static fn (array $row): bool => $row['severity_band'] === 'severity_1')->count(),
            ],
            [
                'label' => 'Severity 2',
                'count' => $ticketRows->filter(static fn (array $row): bool => $row['severity_band'] === 'severity_2')->count(),
            ],
            [
                'label' => 'Severity 3',
                'count' => $ticketRows->filter(static fn (array $row): bool => $row['severity_band'] === 'severity_3')->count(),
            ],
        ];

        $satisfactionMetCount = $ratedTickets
            ->filter(static fn (array $row): bool => (int) $row['satisfaction_rating'] >= self::SATISFACTION_TARGET_RATING)
            ->count();

        return [
            'label' => $label,
            'total_tickets' => $ticketCount,
            'first_response' => [
                'average_minutes' => $this->averageMinutes($firstResponseMinutes),
                'median_minutes' => $this->medianMinutes($firstResponseMinutes),
                'sample_count' => $firstResponseMinutes->count(),
            ],
            'resolution' => [
                'average_minutes' => $this->averageMinutes($resolutionMinutes),
                'median_minutes' => $this->medianMinutes($resolutionMinutes),
                'sample_count' => $resolutionMinutes->count(),
            ],
            'breach_rate' => [
                'breached_count' => $breachedTickets,
                'rate' => $ticketCount > 0 ? round(($breachedTickets / $ticketCount) * 100, 1) : 0.0,
            ],
            'acknowledgment_rate' => [
                'acknowledged_count' => $acknowledgedWithinTarget,
                'rate' => $ticketCount > 0 ? round(($acknowledgedWithinTarget / $ticketCount) * 100, 1) : 0.0,
            ],
            'customer_satisfaction' => [
                'average_rating' => $ratedTickets->isNotEmpty()
                    ? round((float) $ratedTickets->avg('satisfaction_rating'), 1)
                    : null,
                'rated_count' => $ratedTickets->count(),
                'met_count' => $satisfactionMetCount,
                'rate' => $ratedTickets->isNotEmpty()
                    ? round(($satisfactionMetCount / $ratedTickets->count()) * 100, 1)
                    : 0.0,
            ],
            'severity_bands' => $severityBands,
        ];
    }

    /**
     * @return array<int, array{
     *     first_response_minutes: int|null,
     *     resolution_minutes: int|null,
     *     acknowledged_within_target: bool,
     *     breached_resolution_target: bool,
     *     severity_band: string,
     *     satisfaction_rating: int|null
     * }>
     */
    private function ticketRows(Builder $scopedTickets, Carbon $start, Carbon $end, Carbon $now): array
    {
        $firstPublicStaffReplySubquery = TicketReply::query()
            ->join('users', 'users.id', '=', 'ticket_replies.user_id')
            ->whereColumn('ticket_replies.ticket_id', 'tickets.id')
            ->whereNull('ticket_replies.deleted_at')
            ->where('ticket_replies.is_internal', false)
            ->where('users.role', '!=', User::ROLE_CLIENT)
            ->selectRaw('MIN(ticket_replies.created_at)');

        $firstSuperUserSeenSubquery = TicketUserState::query()
            ->join('users', 'users.id', '=', 'ticket_user_states.user_id')
            ->whereColumn('ticket_user_states.ticket_id', 'tickets.id')
            ->whereNotNull('ticket_user_states.last_seen_at')
            ->where('users.role', User::ROLE_SUPER_USER)
            ->selectRaw('MIN(ticket_user_states.last_seen_at)');

        $rows = (clone $scopedTickets)
            ->whereBetween('tickets.created_at', [$start, $end])
            ->select([
                'tickets.id',
                'tickets.created_at',
                'tickets.assigned_at',
                'tickets.resolved_at',
                'tickets.closed_at',
                'tickets.satisfaction_rating',
            ])
            ->selectSub($firstPublicStaffReplySubquery, 'first_public_staff_reply_at')
            ->selectSub($firstSuperUserSeenSubquery, 'first_super_user_seen_at')
            ->get()
            ->toBase()
            ->map(function ($ticket) use ($now): array {
                assert($ticket instanceof Ticket);

                $createdAt = $this->parseOptionalDateTime($ticket->created_at);
                $assignedAt = $this->parseOptionalDateTime($ticket->assigned_at);
                $completedAt = $this->parseOptionalDateTime($ticket->closed_at)
                    ?? $this->parseOptionalDateTime($ticket->resolved_at);
                $firstStaffReplyAt = $this->parseOptionalDateTime($ticket->getAttribute('first_public_staff_reply_at'));
                $firstSuperUserSeenAt = $this->parseOptionalDateTime($ticket->getAttribute('first_super_user_seen_at'));
                $firstResponseAt = $this->earliestDateTime([
                    $firstStaffReplyAt,
                    $assignedAt,
                    $completedAt,
                ]);
                $firstResponseMinutes = $createdAt && $firstResponseAt
                    ? max(0, (int) $createdAt->diffInMinutes($firstResponseAt))
                    : null;
                $acknowledgedWithinTarget = $createdAt
                    && $firstSuperUserSeenAt
                    && (int) $createdAt->diffInMinutes($firstSuperUserSeenAt) < self::ACKNOWLEDGMENT_TARGET_MINUTES;
                $resolutionReferenceAt = $completedAt ?? $now;
                $resolutionMinutes = $createdAt && $completedAt
                    ? max(0, (int) $createdAt->diffInMinutes($completedAt))
                    : null;
                $elapsedMinutes = $createdAt
                    ? max(0, (int) $createdAt->diffInMinutes($resolutionReferenceAt))
                    : 0;

                return [
                    'first_response_minutes' => $firstResponseMinutes,
                    'resolution_minutes' => $resolutionMinutes,
                    'acknowledged_within_target' => (bool) $acknowledgedWithinTarget,
                    'breached_resolution_target' => $elapsedMinutes >= self::RESOLUTION_TARGET_MINUTES,
                    'severity_band' => $this->severityBandForMinutes($elapsedMinutes),
                    'satisfaction_rating' => $ticket->satisfaction_rating,
                ];
            })
            ->values();

        return $rows->all();
    }

    private function parseOptionalDateTime(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }

        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<int, Carbon|null>  $dates
     */
    private function earliestDateTime(array $dates): ?Carbon
    {
        $validDates = array_values(array_filter($dates, static fn ($date) => $date instanceof Carbon));

        if ($validDates === []) {
            return null;
        }

        usort($validDates, static fn (Carbon $left, Carbon $right) => $left->lt($right) ? -1 : 1);

        return $validDates[0];
    }

    /**
     * @param  Collection<int, int|float>  $minutes
     */
    private function averageMinutes(Collection $minutes): ?float
    {
        if ($minutes->isEmpty()) {
            return null;
        }

        return round((float) $minutes->avg(), 1);
    }

    /**
     * @param  Collection<int, int|float>  $minutes
     */
    private function medianMinutes(Collection $minutes): ?float
    {
        if ($minutes->isEmpty()) {
            return null;
        }

        return round((float) $minutes->median(), 1);
    }

    private function severityBandForMinutes(int $minutes): string
    {
        if ($minutes < self::SEVERITY_ONE_MINUTES) {
            return 'under_1_hour';
        }

        if ($minutes < self::SEVERITY_TWO_MINUTES) {
            return 'severity_1';
        }

        if ($minutes < self::SEVERITY_THREE_MINUTES) {
            return 'severity_2';
        }

        return 'severity_3';
    }
}
