<?php

namespace App\Providers;

use App\Models\Ticket;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function ($view) {
            $user = Auth::user();

            if (!$user) {
                $view->with('headerNotifications', collect());
                return;
            }

            $dismissedNotifications = collect(session('dismissed_notifications', []));
            $seenTicketUpdates = collect(session('seen_ticket_updates', []));

            if (!$user->canAccessAdminTickets()) {
                $notifications = Ticket::where('user_id', $user->id)
                    ->latest('updated_at')
                    ->take(12)
                    ->get()
                    ->map(function ($ticket) {
                        $key = 'client-ticket-' . $ticket->id . '-' . optional($ticket->updated_at)->timestamp;

                        return [
                            'title' => 'Ticket update',
                            'meta' => $ticket->subject,
                            'time' => $ticket->updated_at->diffForHumans(),
                            'url' => route('client.notifications.open', ['ticket' => $ticket, 'notification_key' => $key]),
                            'key' => $key,
                            'ticket_id' => $ticket->id,
                            'updated_ts' => optional($ticket->updated_at)->timestamp ?? 0,
                            'can_dismiss' => false,
                            'dismiss_url' => null,
                        ];
                    })
                    ->filter(function ($notification) use ($seenTicketUpdates) {
                        $lastSeen = (int) $seenTicketUpdates->get((string) $notification['ticket_id'], 0);
                        return (int) $notification['updated_ts'] > $lastSeen;
                    })
                    ->reject(fn ($notification) => $dismissedNotifications->contains($notification['key']))
                    ->take(5)
                    ->values();
            } else {
                $notifications = Ticket::with('user')
                    ->open()
                    ->latest('updated_at')
                    ->take(20)
                    ->get()
                    ->map(function ($ticket) {
                        $key = 'admin-ticket-' . $ticket->id . '-' . optional($ticket->updated_at)->timestamp;

                        return [
                            'title' => 'Open ticket update',
                            'meta' => $ticket->subject . ' - ' . optional($ticket->user)->name,
                            'time' => $ticket->updated_at->diffForHumans(),
                            'url' => route('admin.notifications.open', ['ticket' => $ticket, 'notification_key' => $key]),
                            'key' => $key,
                            'ticket_id' => $ticket->id,
                            'updated_ts' => optional($ticket->updated_at)->timestamp ?? 0,
                            'can_dismiss' => true,
                            'dismiss_url' => route('admin.notifications.dismiss'),
                        ];
                    })
                    ->filter(function ($notification) use ($seenTicketUpdates) {
                        $lastSeen = (int) $seenTicketUpdates->get((string) $notification['ticket_id'], 0);
                        return (int) $notification['updated_ts'] > $lastSeen;
                    })
                    ->reject(fn ($notification) => $dismissedNotifications->contains($notification['key']))
                    ->take(5)
                    ->values();
            }

            $view->with('headerNotifications', $notifications);
        });
    }
}
