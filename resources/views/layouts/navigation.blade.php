<nav class="bg-white shadow-lg border-b border-gray-200">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ auth()->user()->canManageTickets() ? route('admin.dashboard') : route('client.dashboard') }}" class="flex items-center">
                        <img src="{{ asset('images/dar-logo.png') }}" alt="DAR Logo" class="h-8 w-auto mr-2">
                        <span class="text-xl font-bold text-ione-blue-600">DAR | iOne Resources</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-8 sm:-my-px sm:ml-10 sm:flex">
                    @if(auth()->user()->canManageTickets())
                        <a href="{{ route('admin.dashboard') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('admin.dashboard') ? 'border-ione-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 transition duration-150 ease-in-out">
                            Dashboard
                        </a>
                        <a href="{{ route('admin.tickets.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('admin.tickets.*') ? 'border-ione-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 transition duration-150 ease-in-out">
                            All Tickets
                        </a>
                        @if(auth()->user()->canManageUsers())
                            <a href="{{ route('admin.users.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('admin.users.*') ? 'border-ione-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 transition duration-150 ease-in-out">
                                User Management
                            </a>
                        @endif
                    @else
                        <a href="{{ route('client.dashboard') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('client.dashboard') ? 'border-ione-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 transition duration-150 ease-in-out">
                            Dashboard
                        </a>
                        <a href="{{ route('client.tickets.index') }}" class="inline-flex items-center px-1 pt-1 border-b-2 {{ request()->routeIs('client.tickets.*') ? 'border-ione-blue-500 text-gray-900' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300' }} text-sm font-medium leading-5 transition duration-150 ease-in-out">
                            My Tickets
                        </a>
                    @endif
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ml-6">
                @if(!auth()->user()->canManageTickets())
                    <a href="{{ route('client.tickets.create') }}" class="btn-primary mr-4">
                        New Ticket
                    </a>
                @endif

                @if(auth()->user()->canManageTickets())
                    <!-- Notification Icon -->
                    <div class="relative mr-4" x-data="{ notificationOpen: false }">
                        <button @click="notificationOpen = !notificationOpen" class="p-2 rounded-full text-gray-500 hover:text-gray-700 hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ione-blue-500 transition duration-150 ease-in-out">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-3.5-3.5A6.965 6.965 0 0012 5a6.965 6.965 0 00-7.5 8.5L1 17h5m9 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                            </svg>
                        </button>
                        @php
                            $newTickets = \App\Models\Ticket::where('status', 'open')->where('created_at', '>=', now()->subHours(24))->with('user')->get();
                            $recentlyResolved = \App\Models\Ticket::where('status', 'resolved')->where('resolved_at', '>=', now()->subHours(24))->with('user')->get();
                            $totalNotifications = $newTickets->count() + $recentlyResolved->count();
                        @endphp
                        @if($totalNotifications > 0)
                            <span class="absolute -top-1 -right-1 inline-flex items-center justify-center min-w-[18px] h-[18px] text-xs font-bold leading-none text-white bg-red-500 rounded-full border-2 border-white shadow-md">
                                {{ $totalNotifications > 99 ? '99+' : $totalNotifications }}
                            </span>
                        @endif

                        <!-- Notification Dropdown -->
                        <div x-show="notificationOpen" @click.away="notificationOpen = false"
                             class="origin-top-right absolute right-0 mt-2 w-80 rounded-md shadow-lg bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50"
                             x-transition:enter="transition ease-out duration-100"
                             x-transition:enter-start="transform opacity-0 scale-95"
                             x-transition:enter-end="transform opacity-100 scale-100"
                             x-transition:leave="transition ease-in duration-75"
                             x-transition:leave-start="transform opacity-100 scale-100"
                             x-transition:leave-end="transform opacity-0 scale-95">

                            <!-- Header -->
                            <div class="bg-blue-600 px-4 py-3 rounded-t-md">
                                <h3 class="text-white font-semibold text-lg">Notifications</h3>
                            </div>

                            <!-- Notification List -->
                            <div>
                                @if($totalNotifications > 0)
                                    @php
                                        $allNotifications = collect();

                                        // Add new tickets with timestamp
                                        foreach($newTickets as $ticket) {
                                            $allNotifications->push([
                                                'type' => 'new',
                                                'ticket' => $ticket,
                                                'timestamp' => $ticket->created_at
                                            ]);
                                        }

                                        // Add resolved tickets with timestamp
                                        foreach($recentlyResolved as $ticket) {
                                            $allNotifications->push([
                                                'type' => 'resolved',
                                                'ticket' => $ticket,
                                                'timestamp' => $ticket->resolved_at
                                            ]);
                                        }

                                        // Sort by timestamp (newest first) and take only first 4
                                        $displayNotifications = $allNotifications->sortByDesc('timestamp')->take(4);
                                        $hasMore = $allNotifications->count() > 4;
                                    @endphp

                                    @foreach($displayNotifications as $notification)
                                        <div class="px-4 py-3 border-b border-gray-100 hover:bg-gray-50">
                                            <div class="flex items-start">
                                                @if($notification['type'] === 'new')
                                                    <div class="w-2 h-2 bg-blue-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900">New ticket has been added</p>
                                                        <p class="text-sm text-gray-500 truncate">{{ $notification['ticket']->subject }} - by {{ $notification['ticket']->user->name }}</p>
                                                        <p class="text-xs text-gray-400">{{ $notification['ticket']->created_at->diffForHumans() }}</p>
                                                    </div>
                                                @else
                                                    <div class="w-2 h-2 bg-green-500 rounded-full mt-2 mr-3 flex-shrink-0"></div>
                                                    <div class="flex-1 min-w-0">
                                                        <p class="text-sm font-medium text-gray-900">Ticket resolved</p>
                                                        <p class="text-sm text-gray-500 truncate">{{ $notification['ticket']->subject }} - by {{ $notification['ticket']->user->name }}</p>
                                                        <p class="text-xs text-gray-400">{{ $notification['ticket']->resolved_at->diffForHumans() }}</p>
                                                    </div>
                                                @endif
                                                <button class="text-gray-400 hover:text-gray-600 ml-2">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                    </svg>
                                                </button>
                                            </div>
                                        </div>
                                    @endforeach

                                    <!-- Show All Notifications Button -->
                                    <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 rounded-b-md text-center">
                                        <a href="{{ route('admin.tickets.index') }}" class="text-blue-600 text-sm font-medium hover:text-blue-800 transition duration-150 ease-in-out">
                                            @if($hasMore)
                                                View all notifications ({{ $totalNotifications }})
                                            @else
                                                View all tickets
                                            @endif
                                        </a>
                                    </div>
                                @else
                                    <div class="px-4 py-8 text-center">
                                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-3.5-3.5A6.965 6.965 0 0012 5a6.965 6.965 0 00-7.5 8.5L1 17h5m9 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                        </svg>
                                        <h3 class="mt-2 text-sm font-medium text-gray-900">No notifications</h3>
                                        <p class="mt-1 text-sm text-gray-500">You're all caught up!</p>
                                    </div>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <div class="ml-3 relative" x-data="{ open: false }">
                    <div>
                        <button @click="open = ! open" class="flex items-center text-sm rounded-full focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ione-blue-500" id="user-menu-button">
                            <div class="w-8 h-8 bg-ione-blue-600 rounded-full flex items-center justify-center text-white font-semibold">
                                {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                            </div>
                            <div class="ml-2 text-gray-700">
                                {{ auth()->user()->name }}
                                <span class="text-xs text-gray-500">({{ auth()->user()->role }})</span>
                            </div>
                            <svg class="ml-2 h-4 w-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </button>
                    </div>

                    <div x-show="open" @click.away="open = false" class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-50" x-transition>
                        <div class="px-4 py-2 text-xs text-gray-500">
                            {{ auth()->user()->email }}
                        </div>
                        <div class="border-t border-gray-100"></div>
                        <a href="{{ route('account.settings') }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                            Account Settings
                        </a>
                        <div class="border-t border-gray-100"></div>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="block w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-100">
                                Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div class="sm:hidden" x-data="{ open: false }">
        <div class="pt-2 pb-3 space-y-1">
            @if(auth()->user()->canManageTickets())
                <a href="{{ route('admin.dashboard') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('admin.dashboard') ? 'border-ione-blue-500 text-ione-blue-700 bg-ione-blue-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300' }} text-base font-medium transition duration-150 ease-in-out">
                    Dashboard
                </a>
                <a href="{{ route('admin.tickets.index') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('admin.tickets.*') ? 'border-ione-blue-500 text-ione-blue-700 bg-ione-blue-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300' }} text-base font-medium transition duration-150 ease-in-out">
                    All Tickets
                </a>
                @if(auth()->user()->canManageUsers())
                    <a href="{{ route('admin.users.index') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('admin.users.*') ? 'border-ione-blue-500 text-ione-blue-700 bg-ione-blue-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300' }} text-base font-medium transition duration-150 ease-in-out">
                        User Management
                    </a>
                @endif
            @else
                <a href="{{ route('client.dashboard') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('client.dashboard') ? 'border-ione-blue-500 text-ione-blue-700 bg-ione-blue-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300' }} text-base font-medium transition duration-150 ease-in-out">
                    Dashboard
                </a>
                <a href="{{ route('client.tickets.index') }}" class="block pl-3 pr-4 py-2 border-l-4 {{ request()->routeIs('client.tickets.*') ? 'border-ione-blue-500 text-ione-blue-700 bg-ione-blue-50' : 'border-transparent text-gray-600 hover:text-gray-800 hover:bg-gray-50 hover:border-gray-300' }} text-base font-medium transition duration-150 ease-in-out">
                    My Tickets
                </a>
            @endif
        </div>
    </div>
</nav>