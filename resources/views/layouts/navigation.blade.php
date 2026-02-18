<nav class="sticky top-0 z-40 border-b border-white/70 bg-white/75 shadow-sm backdrop-blur-xl" x-data="{ mobileOpen: false }">
    <div class="app-shell mx-auto">
        <div class="flex h-20 items-center justify-between gap-4">
            <div class="flex min-w-0 items-center gap-5">
                <a href="{{ auth()->user()->canManageTickets() ? route('admin.dashboard') : route('client.dashboard') }}" class="group flex min-w-0 items-center gap-3">
                    <img src="{{ asset('images/DICT-logo.png') }}" alt="DICT Logo" class="h-9 w-auto transition duration-300 group-hover:scale-105">
                    <span class="hidden truncate font-display text-xl font-semibold text-ione-blue-700 sm:block">
                        DICT | iOne Resources
                    </span>
                </a>

                <div class="hidden items-center gap-1 md:flex">
                    @if(auth()->user()->canManageTickets())
                        <a href="{{ route('admin.dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.dashboard') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('admin.tickets.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.tickets.*') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            All Tickets
                        </a>
                        @if(auth()->user()->canManageUsers())
                            <a href="{{ route('admin.users.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.users.*') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                                Users
                            </a>
                        @endif
                    @else
                        <a href="{{ route('client.dashboard') }}" class="rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('client.dashboard') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            Dashboard
                        </a>
                        <a href="{{ route('client.tickets.index') }}" class="rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('client.tickets.*') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                            My Tickets
                        </a>
                    @endif
                </div>
            </div>

            <div class="hidden items-center gap-3 md:flex">
                @if(!auth()->user()->canManageTickets())
                    <a href="{{ route('client.tickets.create') }}" class="btn-primary">
                        New Ticket
                    </a>
                @endif

                @if(auth()->user()->canManageTickets())
                    <div class="relative" x-data="{ notificationOpen: false }">
                        <button @click="notificationOpen = !notificationOpen" class="relative rounded-xl border border-slate-200 bg-white p-2.5 text-slate-600 transition hover:border-slate-300 hover:text-slate-900 focus:outline-none focus:ring-2 focus:ring-ione-blue-300">
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-3.5-3.5A6.965 6.965 0 0012 5a6.965 6.965 0 00-7.5 8.5L1 17h5m9 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                        </button>
                        @php
                            $newTickets = \App\Models\Ticket::where('status', 'open')->where('created_at', '>=', now()->subHours(24))->with('user')->get();
                            $recentlyResolved = \App\Models\Ticket::where('status', 'resolved')->where('resolved_at', '>=', now()->subHours(24))->with('user')->get();
                            $dismissedNotifications = collect(session('dismissed_notifications', []));
                            $allNotifications = collect();

                            foreach($newTickets as $ticket) {
                                $allNotifications->push([
                                    'key' => 'new:' . $ticket->id . ':' . $ticket->created_at->timestamp,
                                    'type' => 'new',
                                    'ticket' => $ticket,
                                    'timestamp' => $ticket->created_at
                                ]);
                            }

                            foreach($recentlyResolved as $ticket) {
                                $resolvedTimestamp = optional($ticket->resolved_at)->timestamp ?? $ticket->updated_at->timestamp;
                                $allNotifications->push([
                                    'key' => 'resolved:' . $ticket->id . ':' . $resolvedTimestamp,
                                    'type' => 'resolved',
                                    'ticket' => $ticket,
                                    'timestamp' => $ticket->resolved_at
                                ]);
                            }

                            $allNotifications = $allNotifications
                                ->reject(fn($notification) => $dismissedNotifications->contains($notification['key']))
                                ->sortByDesc('timestamp')
                                ->values();

                            $totalNotifications = $allNotifications->count();
                            $displayNotifications = $allNotifications->take(4);
                            $hasMore = $totalNotifications > 4;
                        @endphp
                        @if($totalNotifications > 0)
                            <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full border-2 border-white bg-rose-500 px-1 text-xs font-bold text-white">
                                {{ $totalNotifications > 99 ? '99+' : $totalNotifications }}
                            </span>
                        @endif

                        <div x-show="notificationOpen" @click.away="notificationOpen = false"
                             class="absolute right-0 z-50 mt-3 w-80 overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xl"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-1"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-1">
                            <div class="hero-glow border-b border-slate-100 px-4 py-3">
                                <h3 class="font-display text-base font-semibold text-slate-900">Notifications</h3>
                            </div>

                            <div>
                                @if($totalNotifications > 0)
                                    @foreach($displayNotifications as $notification)
                                        <div class="border-b border-slate-100 px-4 py-3 hover:bg-slate-50">
                                            <div class="flex items-start">
                                                @if($notification['type'] === 'new')
                                                    <div class="mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full bg-ione-blue-500"></div>
                                                    <a href="{{ route('admin.notifications.open', ['ticket' => $notification['ticket']->id, 'notification_key' => $notification['key']]) }}" class="min-w-0 flex-1">
                                                        <p class="text-sm font-semibold text-slate-900">New ticket added</p>
                                                        <p class="truncate text-sm text-slate-500">{{ $notification['ticket']->subject }} - by {{ $notification['ticket']->user->name }}</p>
                                                        <p class="text-xs text-slate-400">{{ $notification['ticket']->created_at->diffForHumans() }}</p>
                                                    </a>
                                                @else
                                                    <div class="mr-3 mt-2 h-2 w-2 flex-shrink-0 rounded-full bg-emerald-500"></div>
                                                    <a href="{{ route('admin.notifications.open', ['ticket' => $notification['ticket']->id, 'notification_key' => $notification['key']]) }}" class="min-w-0 flex-1">
                                                        <p class="text-sm font-semibold text-slate-900">Ticket resolved</p>
                                                        <p class="truncate text-sm text-slate-500">{{ $notification['ticket']->subject }} - by {{ $notification['ticket']->user->name }}</p>
                                                        <p class="text-xs text-slate-400">{{ $notification['ticket']->resolved_at->diffForHumans() }}</p>
                                                    </a>
                                                @endif

                                                <form action="{{ route('admin.notifications.dismiss') }}" method="POST" class="ml-2">
                                                    @csrf
                                                    <input type="hidden" name="notification_key" value="{{ $notification['key'] }}">
                                                    <button type="submit" class="rounded-md p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" aria-label="Dismiss notification">
                                                        <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                        </svg>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    @endforeach

                                    <div class="border-t border-slate-100 bg-slate-50 px-4 py-3 text-center">
                                        <a href="{{ route('admin.tickets.index') }}" class="text-sm font-semibold text-ione-blue-600 hover:text-ione-blue-700">
                                            @if($hasMore)
                                                View all notifications ({{ $totalNotifications }})
                                            @else
                                                View all tickets
                                            @endif
                                        </a>
                                    </div>
                                @else
                                    <div class="px-4 py-7 text-center">
                                        <h3 class="text-sm font-semibold text-slate-900">No notifications</h3>
                                        <p class="mt-1 text-sm text-slate-500">You're all caught up.</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <div class="relative" x-data="{ open: false }">
                    <button @click="open = ! open" class="flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-left transition hover:border-slate-300 focus:outline-none focus:ring-2 focus:ring-ione-blue-300">
                        <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-ione-blue-600 text-sm font-semibold text-white">
                            {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                        </div>
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-slate-800">{{ auth()->user()->name }}</div>
                            <div class="truncate text-xs text-slate-500">{{ auth()->user()->role }}</div>
                        </div>
                        <svg class="h-4 w-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                        </svg>
                    </button>

                    <div x-show="open" @click.away="open = false" class="absolute right-0 z-50 mt-3 w-56 overflow-hidden rounded-2xl border border-slate-200 bg-white py-2 shadow-xl" x-transition>
                        <div class="px-4 pb-2 text-xs text-slate-500">{{ auth()->user()->email }}</div>
                        <a href="{{ route('account.settings') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                            Account Settings
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <button @click="mobileOpen = !mobileOpen" class="md:hidden rounded-lg border border-slate-200 bg-white p-2 text-slate-600 focus:outline-none focus:ring-2 focus:ring-ione-blue-300" aria-label="Toggle menu">
                <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                </svg>
                <svg x-show="mobileOpen" class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>
    </div>

    <div class="border-t border-slate-200 bg-white/95 md:hidden" x-show="mobileOpen" x-transition>
        <div class="space-y-1 px-4 py-3">
            @if(auth()->user()->canManageTickets())
                <a href="{{ route('admin.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.dashboard') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    Dashboard
                </a>
                <a href="{{ route('admin.tickets.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.tickets.*') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    All Tickets
                </a>
                @if(auth()->user()->canManageUsers())
                    <a href="{{ route('admin.users.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('admin.users.*') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                        Users
                    </a>
                @endif
            @else
                <a href="{{ route('client.dashboard') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('client.dashboard') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    Dashboard
                </a>
                <a href="{{ route('client.tickets.index') }}" class="block rounded-lg px-3 py-2 text-sm font-semibold {{ request()->routeIs('client.tickets.*') ? 'bg-ione-blue-50 text-ione-blue-700' : 'text-slate-600 hover:bg-slate-100 hover:text-slate-900' }}">
                    My Tickets
                </a>
                <a href="{{ route('client.tickets.create') }}" class="mt-2 block rounded-lg bg-ione-blue-600 px-3 py-2 text-center text-sm font-semibold text-white">
                    New Ticket
                </a>
            @endif
        </div>

        <div class="border-t border-slate-200 px-4 py-3">
            <p class="text-sm font-semibold text-slate-900">{{ auth()->user()->name }}</p>
            <p class="text-xs text-slate-500">{{ auth()->user()->email }}</p>
            <div class="mt-3 space-y-1">
                <a href="{{ route('account.settings') }}" class="block rounded-lg px-3 py-2 text-sm text-slate-700 hover:bg-slate-100">
                    Account Settings
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="block w-full rounded-lg px-3 py-2 text-left text-sm text-slate-700 hover:bg-slate-100">
                        Sign out
                    </button>
                </form>
            </div>
        </div>
    </div>
</nav>
