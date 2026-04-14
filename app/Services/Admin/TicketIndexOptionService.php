<?php

namespace App\Services\Admin;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class TicketIndexOptionService
{
    public function distinctTicketColumnOptions(string $column, ?Builder $scopedBaseQuery = null): Collection
    {
        $this->assertSupportedLocationColumn($column);

        $query = $scopedBaseQuery ? clone $scopedBaseQuery : Ticket::query();

        return $query
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column)
            ->values();
    }

    public function accountOptionsFor(?User $currentUser, Builder $scopedTickets): Collection
    {
        if ($currentUser && $currentUser->isTechnician()) {
            $visibleClientIds = (clone $scopedTickets)
                ->whereNotNull('user_id')
                ->select('user_id')
                ->distinct()
                ->pluck('user_id');

            return User::where('role', User::ROLE_CLIENT)
                ->where('is_active', true)
                ->whereIn('id', $visibleClientIds)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values();
        }

        return Cache::remember('admin_ticket_account_options_active_clients_v1', now()->addSeconds(60), function () {
            return User::where('role', User::ROLE_CLIENT)
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name'])
                ->values();
        });
    }

    public function monthOptionsFor(Builder $scopedTickets): Collection
    {
        $monthKeyExpression = $this->monthKeyExpression('created_at', $scopedTickets);

        return (clone $scopedTickets)
            ->whereNotNull('created_at')
            ->selectRaw("{$monthKeyExpression} as month_key")
            ->distinct()
            ->orderByDesc('month_key')
            ->pluck('month_key')
            ->map(function (mixed $monthKey): array {
                $month = Carbon::createFromFormat('Y-m', (string) $monthKey)->startOfMonth();

                return [
                    'value' => $month->format('Y-m'),
                    'label' => $month->format('F Y'),
                ];
            })
            ->unique('value')
            ->values();
    }

    public function activeAssignableAgents(): Collection
    {
        return Cache::remember('admin_ticket_active_agents_v2', now()->addSeconds(45), function () {
            return User::whereIn('role', User::TICKET_CONSOLE_ROLES)
                ->visibleDirectory()
                ->where('is_active', true)
                ->orderBy('name')
                ->get(['id', 'name', 'role']);
        });
    }

    private function monthKeyExpression(string $column, Builder $query): string
    {
        /** @var Connection $connection */
        $connection = $query->getQuery()->getConnection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'pgsql' => "TO_CHAR({$column}, 'YYYY-MM')",
            'sqlite' => "strftime('%Y-%m', {$column})",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }

    private function assertSupportedLocationColumn(string $column): void
    {
        if (! in_array($column, ['province', 'municipality'], true)) {
            throw new \InvalidArgumentException('Unsupported ticket location column.');
        }
    }
}
