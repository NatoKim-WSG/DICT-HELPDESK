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

            if (!$user->canAccessAdminTickets()) {
                $notifications = Ticket::where('user_id', $user->id)
                    ->latest('updated_at')
                    ->take(5)
                    ->get()
                    ->map(function ($ticket) {
                        return [
                            'title' => 'Ticket update',
                            'meta' => $ticket->subject,
                            'time' => $ticket->updated_at->diffForHumans(),
                            'url' => route('client.tickets.show', $ticket),
                        ];
                    });
            } else {
                $notifications = Ticket::with('user')
                    ->open()
                    ->latest('created_at')
                    ->take(5)
                    ->get()
                    ->map(function ($ticket) {
                        return [
                            'title' => 'New open ticket',
                            'meta' => $ticket->subject . ' - ' . optional($ticket->user)->name,
                            'time' => $ticket->created_at->diffForHumans(),
                            'url' => route('admin.tickets.show', $ticket),
                        ];
                    });
            }

            $view->with('headerNotifications', $notifications);
        });
    }
}
