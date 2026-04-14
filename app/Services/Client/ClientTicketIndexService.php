<?php

namespace App\Services\Client;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

class ClientTicketIndexService
{
    /**
     * @return Builder<Ticket>
     */
    public function scopedTicketQueryFor(User $user): Builder
    {
        return Ticket::query()
            ->whereBelongsTo($user, 'user')
            ->with(['category', 'assignedUser', 'assignedUsers']);
    }

    public function resolveActiveTab(string $requestedTab): string
    {
        return in_array($requestedTab, ['tickets', 'history'], true)
            ? $requestedTab
            : 'tickets';
    }

    public function resolveSelectedStatus(string $requestedStatus, string $activeTab): string
    {
        $selectedStatus = trim($requestedStatus);
        if ($selectedStatus === '') {
            $selectedStatus = 'all';
        }

        return in_array($selectedStatus, $this->allowedStatusesForTab($activeTab), true)
            ? $selectedStatus
            : 'all';
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function applyTabScope(Builder $query, string $activeTab): void
    {
        $query->whereIn(
            'status',
            $activeTab === 'history' ? Ticket::CLOSED_STATUSES : Ticket::OPEN_STATUSES
        );
    }

    /**
     * @param  Builder<Ticket>  $query
     */
    public function applyFilters(Builder $query, Request $request, string $selectedStatus): void
    {
        $query
            ->when($selectedStatus !== 'all', function (Builder $builder) use ($selectedStatus) {
                if (in_array($selectedStatus, ['open', 'open_group'], true)) {
                    $builder->whereIn('status', Ticket::OPEN_STATUSES);

                    return;
                }

                $builder->where('status', $selectedStatus);
            })
            ->when($request->filled('search'), function (Builder $builder) use ($request) {
                $search = mb_strtolower($request->string('search')->toString());
                $pattern = '%'.$search.'%';

                $builder->where(function (Builder $nested) use ($pattern) {
                    $nested->whereRaw('LOWER(subject) LIKE ?', [$pattern])
                        ->orWhereRaw('LOWER(ticket_number) LIKE ?', [$pattern]);
                });
            });
    }

    /**
     * @param  Builder<Ticket>  $ticketQuery
     */
    public function buildSnapshotToken(Builder $ticketQuery): string
    {
        $openStatusesSqlList = "'".implode("','", Ticket::OPEN_STATUSES)."'";
        $summary = (clone $ticketQuery)
            ->toBase()
            ->selectRaw('COUNT(*) as total_tickets')
            ->selectRaw("SUM(CASE WHEN status IN ({$openStatusesSqlList}) THEN 1 ELSE 0 END) as open_tickets")
            ->selectRaw('MAX(updated_at) as latest_updated_at')
            ->first();
        $summaryRow = is_object($summary) ? (array) $summary : [];
        $latestUpdatedTimestamp = ! empty($summaryRow['latest_updated_at'])
            ? strtotime((string) $summaryRow['latest_updated_at'])
            : 0;

        return sha1(json_encode([
            'latest_updated_at' => $latestUpdatedTimestamp,
            'open_tickets' => (int) ($summaryRow['open_tickets'] ?? 0),
            'total_tickets' => (int) ($summaryRow['total_tickets'] ?? 0),
        ]));
    }

    private function allowedStatusesForTab(string $activeTab): array
    {
        return $activeTab === 'history'
            ? array_merge(['all'], Ticket::CLOSED_STATUSES)
            : array_merge(['all', 'open_group'], Ticket::OPEN_STATUSES);
    }
}
