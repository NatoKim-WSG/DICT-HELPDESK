<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class TicketIndexScopeService
{
    public function resolveActiveTab(string $requestedTab, ?User $user = null): string
    {
        $activeTab = in_array($requestedTab, ['all', 'tickets', 'attention', 'history'], true)
            ? $requestedTab
            : 'tickets';

        if ($activeTab === 'attention' && $user?->isTechnician()) {
            return 'tickets';
        }

        return $activeTab;
    }

    public function resolveSelectedStatus(string $requestedStatus, string $activeTab): string
    {
        $selectedStatus = trim($requestedStatus);
        if ($selectedStatus === '') {
            $selectedStatus = 'all';
        }

        $allowedStatuses = $this->allowedStatusesForTab($activeTab);

        return in_array($selectedStatus, $allowedStatuses, true)
            ? $selectedStatus
            : 'all';
    }

    public function scopedTicketQueryFor(?User $user): Builder
    {
        $query = Ticket::query();

        if ($user && $user->isTechnician()) {
            Ticket::applyAssignedToConstraint($query, (int) $user->id);
        }

        return $query;
    }

    public function applyTabScope(Builder $query, string $activeTab): void
    {
        if ($activeTab === 'all') {
            return;
        }

        if ($activeTab === 'history') {
            $query->whereIn('status', Ticket::CLOSED_STATUSES);

            return;
        }

        if ($activeTab === 'attention') {
            $query->whereNotIn('status', Ticket::CLOSED_STATUSES)
                ->where('created_at', '<=', now()->subHours(16));

            return;
        }

        $query->whereIn('status', Ticket::OPEN_STATUSES);
    }

    private function allowedStatusesForTab(string $activeTab): array
    {
        return $activeTab === 'history'
            ? array_merge(['all'], Ticket::CLOSED_STATUSES)
            : ($activeTab === 'all'
                ? array_merge(['all'], Ticket::OPEN_STATUSES, Ticket::CLOSED_STATUSES)
                : array_merge(['all'], Ticket::OPEN_STATUSES));
    }
}
