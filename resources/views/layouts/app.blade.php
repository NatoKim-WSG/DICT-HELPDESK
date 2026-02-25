<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php
        $tabUser = auth()->user();
        $tabYieldedTitle = trim((string) $__env->yieldContent('title'));
        $tabRouteName = (string) optional(request()->route())->getName();
        $tabDepartmentBrand = $tabUser
            ? \App\Models\User::departmentBrandAssets($tabUser->department, $tabUser->role)
            : ['name' => 'iOne'];

        $tabPageLabel = '';
        if ($tabYieldedTitle !== '') {
            $tabPageLabel = preg_replace('/\s*[-|]\s*DICT.*$/i', '', $tabYieldedTitle) ?? '';
            $tabPageLabel = preg_replace('/\s*[-|]\s*iOne Resources.*$/i', '', $tabPageLabel) ?? '';
            $tabPageLabel = trim($tabPageLabel);
        }

        if ($tabPageLabel === '') {
            $tabPageLabel = match (true) {
                str_starts_with($tabRouteName, 'admin.dashboard') => 'Dashboard',
                str_starts_with($tabRouteName, 'admin.tickets.show') => isset($ticket) ? 'Ticket #' . $ticket->ticket_number : 'Ticket Details',
                str_starts_with($tabRouteName, 'admin.tickets.') => 'Tickets',
                str_starts_with($tabRouteName, 'admin.reports.') => 'Reports',
                str_starts_with($tabRouteName, 'admin.users.') => 'Users',
                str_starts_with($tabRouteName, 'admin.system-logs.') => 'System Logs',
                str_starts_with($tabRouteName, 'client.dashboard') => 'Dashboard',
                str_starts_with($tabRouteName, 'client.tickets.create') => 'New Ticket',
                str_starts_with($tabRouteName, 'client.tickets.show') => isset($ticket) ? 'Ticket #' . $ticket->ticket_number : 'Ticket Details',
                str_starts_with($tabRouteName, 'client.tickets.') => 'My Tickets',
                str_starts_with($tabRouteName, 'account.settings') => 'Account Settings',
                default => 'Helpdesk',
            };
        }

        $tabContextLabel = 'Helpdesk';
        if ($tabUser) {
            $tabContextLabel = match ($tabUser->normalizedRole()) {
                \App\Models\User::ROLE_SHADOW => 'Admin Console',
                \App\Models\User::ROLE_ADMIN => 'Admin Console',
                \App\Models\User::ROLE_SUPER_USER => 'Super User Console',
                \App\Models\User::ROLE_TECHNICAL => 'Technical Console',
                default => $tabDepartmentBrand['name'] . ' Client Portal',
            };
        }

        $tabTitle = trim($tabPageLabel . ' | ' . $tabContextLabel);
    @endphp
    <title>{{ $tabTitle }}</title>
    <link rel="icon" type="image/png" href="{{ asset('images/iOne Logo.png') }}">
    <link rel="shortcut icon" href="{{ asset('images/iOne Logo.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/iOne Logo.png') }}">

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|space-grotesk:500,600,700&display=swap" rel="stylesheet" />
    <script>
        (function () {
            if (localStorage.getItem('ione_theme') === 'dark') {
                document.documentElement.classList.add('theme-dark');
            }
        })();
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f3f5f7] font-sans antialiased text-slate-900">
    <div
        class="min-h-screen bg-[#f3f5f7]"
        x-data="{
            sidebarOpen: false,
            darkMode: false,
            legalModalOpen: false,
            legalModalTab: 'terms',
            init() {
                this.darkMode = document.documentElement.classList.contains('theme-dark');
            },
            toggleDarkMode() {
                this.darkMode = !this.darkMode;
                document.documentElement.classList.toggle('theme-dark', this.darkMode);
                localStorage.setItem('ione_theme', this.darkMode ? 'dark' : 'light');
            },
            openLegalModal(tab = 'terms') {
                this.legalModalTab = tab;
                this.legalModalOpen = true;
                document.body.classList.add('overflow-hidden');
            },
            closeLegalModal() {
                this.legalModalOpen = false;
                document.body.classList.remove('overflow-hidden');
            }
        }"
    >
        @include('layouts.navigation')

        <div class="min-h-screen lg:pl-64">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-[#f3f5f7]/95 backdrop-blur">
                @php
                    $user = auth()->user();
                    $isClient = !$user->canAccessAdminTickets();
                    $canAccessAccountSettings = $user->canAccessAdminTickets();
                    $departmentBrand = \App\Models\User::departmentBrandAssets($user->department, $user->role);
                    $clientCompanyName = $departmentBrand['name'];
                    $clientCompanyLogo = $departmentBrand['logo_url'];
                    $activeRouteName = (string) optional(request()->route())->getName();
                    $headerSearchRouteName = match (true) {
                        str_starts_with($activeRouteName, 'admin.users.') => 'admin.users.index',
                        str_starts_with($activeRouteName, 'admin.tickets.') => 'admin.tickets.index',
                        str_starts_with($activeRouteName, 'client.tickets.') => 'client.tickets.index',
                        default => $isClient ? 'client.tickets.index' : 'admin.tickets.index',
                    };
                    $headerSearchAction = route($headerSearchRouteName);
                    $headerSearchQuery = trim((string) request('search', ''));
                    $headerSearchPlaceholder = $isClient ? 'Search my tickets...' : 'Search tickets or users...';
                    $headerSearchCarryParams = collect(request()->query())
                        ->except(['search', 'page'])
                        ->all();
                    $notifications = $headerNotifications ?? collect();
                    $notificationCount = $notifications->where('is_viewed', false)->count();
                    $consoleLabel = match ($user->normalizedRole()) {
                        'shadow' => 'Admin Console',
                        'admin' => 'Admin Console',
                        'super_user' => 'Super User Console',
                        default => 'Technical Console',
                    };
                @endphp

                <div class="flex h-20 items-center gap-3 px-3 sm:px-5 lg:px-8">
                    <div class="flex min-w-0 items-center gap-3">
                        <button
                            @click="sidebarOpen = true"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 lg:hidden"
                            aria-label="Open sidebar"
                        >
                            <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"></path>
                            </svg>
                        </button>
                        <button
                            type="button"
                            @click="toggleDarkMode"
                            class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                            :aria-pressed="darkMode.toString()"
                            aria-label="Toggle dark mode"
                        >
                            <svg x-cloak x-show="!darkMode" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m8.66-10h-1M4.34 12h-1m14.02 6.36-.7-.7M7.02 7.02l-.7-.7m12.02 0-.7.7M7.02 16.98l-.7.7M12 8a4 4 0 100 8 4 4 0 000-8z"></path>
                            </svg>
                            <svg x-cloak x-show="darkMode" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.8A9 9 0 1111.2 3a7 7 0 109.8 9.8z"></path>
                            </svg>
                        </button>
                    </div>

                    <div class="hidden min-w-0 flex-1 xl:block">
                        <form method="GET" action="{{ $headerSearchAction }}" @class([
                            'relative w-full',
                            'mx-auto max-w-3xl' => $isClient,
                            'mx-auto max-w-xl' => !$isClient,
                        ])>
                            @foreach($headerSearchCarryParams as $paramKey => $paramValue)
                                @if(is_array($paramValue))
                                    @foreach($paramValue as $arrayValue)
                                        <input type="hidden" name="{{ $paramKey }}[]" value="{{ $arrayValue }}">
                                    @endforeach
                                @else
                                    <input type="hidden" name="{{ $paramKey }}" value="{{ $paramValue }}">
                                @endif
                            @endforeach
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input
                                type="text"
                                name="search"
                                value="{{ $headerSearchQuery }}"
                                class="h-12 w-full rounded-2xl border border-slate-300 bg-white pl-10 pr-4 text-sm text-slate-700 placeholder-slate-400 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20"
                                placeholder="{{ $headerSearchPlaceholder }}"
                            >
                        </form>
                    </div>

                    <div class="relative z-10 ml-auto flex min-w-0 items-center gap-2 sm:gap-3">
                        @if($isClient)
                            <a href="{{ route('client.tickets.create') }}" class="inline-flex items-center rounded-xl bg-[#033b3d] px-3 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-[#022a2c] sm:px-4 sm:py-2.5">
                                New ticket
                            </a>
                        @endif

                        @if($user->canAccessAdminTickets())
                            <span class="hidden items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 xl:inline-flex">
                                {{ $consoleLabel }}
                            </span>
                        @endif

                        <div class="relative" x-data="{ notificationOpen: false }">
                            <button
                                @click="notificationOpen = !notificationOpen"
                                type="button"
                                class="relative inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-slate-200 bg-white text-slate-600 transition hover:bg-slate-50"
                                aria-label="Notifications"
                            >
                                <svg class="h-6 w-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-3.5-3.5A6.965 6.965 0 0012 5a6.965 6.965 0 00-7.5 8.5L1 17h5m9 0v1a3 3 0 11-6 0v-1m6 0H9"></path>
                                </svg>
                                @if($notificationCount > 0)
                                    <span class="js-header-notification-badge absolute -right-1 -top-1 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white" data-count="{{ $notificationCount }}">
                                        {{ $notificationCount > 9 ? '9+' : $notificationCount }}
                                    </span>
                                @endif
                            </button>

                            <div
                                x-cloak
                                x-show="notificationOpen"
                                @click.away="notificationOpen = false"
                                x-transition:enter="transition duration-220 ease-out"
                                x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition duration-160 ease-in"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                class="fixed left-2 right-2 top-20 z-40 max-h-[70vh] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg sm:absolute sm:left-auto sm:right-0 sm:top-full sm:mt-2 sm:w-80 sm:max-w-[calc(100vw-1rem)]"
                            >
                                <div class="border-b border-slate-200 px-4 py-3">
                                    <h3 class="text-sm font-semibold text-slate-900">Notifications</h3>
                                </div>
                                @if($notifications->count() > 0)
                                    <div class="js-header-notification-list max-h-72 overflow-y-auto">
                                        @foreach($notifications as $notification)
                                            <div
                                                @class([
                                                'js-header-notification flex items-start gap-2 border-b border-slate-100 px-2 py-1 hover:bg-slate-50',
                                                'bg-slate-50/70' => !empty($notification['is_viewed']),
                                                ])
                                                data-ticket-id="{{ $notification['ticket_id'] }}"
                                                data-activity-at="{{ $notification['activity_at'] }}"
                                                data-viewed="{{ !empty($notification['is_viewed']) ? '1' : '0' }}"
                                            >
                                                <a href="{{ $notification['url'] }}" class="block min-w-0 flex-1 px-2 py-2">
                                                    <p class="text-sm font-semibold text-slate-900">{{ $notification['title'] }}</p>
                                                    <p class="mt-0.5 truncate text-sm text-slate-600">{{ $notification['meta'] }}</p>
                                                    <p class="mt-1 text-xs text-slate-400">
                                                        {{ $notification['time'] }}
                                                        @if(!empty($notification['is_viewed']))
                                                            <span class="ml-1 rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-600">Viewed</span>
                                                        @endif
                                                    </p>
                                                </a>
                                                @if(!empty($notification['can_dismiss']) && !empty($notification['dismiss_url']) && !empty($notification['key']))
                                                    <form method="POST" action="{{ $notification['dismiss_url'] }}" class="pt-2">
                                                        @csrf
                                                        <input type="hidden" name="ticket_id" value="{{ $notification['ticket_id'] }}">
                                                        <input type="hidden" name="activity_at" value="{{ $notification['activity_at'] }}">
                                                        <button type="submit" class="inline-flex h-7 w-7 items-center justify-center rounded-full text-slate-400 transition hover:bg-slate-200 hover:text-slate-700" aria-label="Dismiss notification">
                                                            <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                                            </svg>
                                                        </button>
                                                    </form>
                                                @endif
                                            </div>
                                        @endforeach
                                    </div>
                                    <div class="js-header-notification-empty hidden px-4 py-6 text-center">
                                        <p class="text-sm font-semibold text-slate-700">No notifications</p>
                                        <p class="mt-1 text-xs text-slate-500">You are all caught up.</p>
                                    </div>
                                @else
                                    <div class="js-header-notification-list hidden max-h-72 overflow-y-auto"></div>
                                    <div class="js-header-notification-empty px-4 py-6 text-center">
                                        <p class="text-sm font-semibold text-slate-700">No notifications</p>
                                        <p class="mt-1 text-xs text-slate-500">You are all caught up.</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 sm:px-4">
                                <span class="inline-flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                                    <img src="{{ $clientCompanyLogo }}" alt="{{ $clientCompanyName }} logo" class="avatar-logo">
                                </span>
                                <span class="hidden text-left xl:block">
                                    <span class="block max-w-[11rem] truncate text-base font-semibold text-slate-800">{{ $user->name }}</span>
                                    <span class="block text-sm text-slate-500">{{ $clientCompanyName }}</span>
                                </span>
                            </button>

                            <div
                                x-cloak
                                x-show="open"
                                @click.away="open = false"
                                x-transition:enter="transition duration-220 ease-out"
                                x-transition:enter-start="opacity-0 -translate-y-1 scale-95"
                                x-transition:enter-end="opacity-100 translate-y-0 scale-100"
                                x-transition:leave="transition duration-150 ease-in"
                                x-transition:leave-start="opacity-100 translate-y-0 scale-100"
                                x-transition:leave-end="opacity-0 -translate-y-1 scale-95"
                                class="absolute right-0 z-40 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-lg"
                            >
                                @if($canAccessAccountSettings)
                                    <a href="{{ route('account.settings') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Account Settings</a>
                                @endif
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-slate-700 hover:bg-slate-50">Sign out</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <main class="px-4 py-6 sm:px-6 lg:px-8">
                @if (session('success') && !session('suppress_success_banner'))
                    <div class="mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="alert">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session('error'))
                    <div class="mb-5 rounded-xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800" role="alert">
                        {{ session('error') }}
                    </div>
                @endif

                @if (isset($header))
                    <header class="mb-6">
                        {{ $header }}
                    </header>
                @endif

                @yield('content')

                <div class="mt-8 border-t border-slate-200 pt-4 text-xs text-slate-500">
                    <span class="mr-2">Legal:</span>
                    <button type="button" @click="openLegalModal('terms')" class="border-0 bg-transparent p-0 font-semibold text-slate-600 hover:text-slate-900">Terms</button>
                    <span class="mx-1">|</span>
                    <button type="button" @click="openLegalModal('privacy')" class="border-0 bg-transparent p-0 font-semibold text-slate-600 hover:text-slate-900">Privacy</button>
                    <span class="mx-1">|</span>
                    <button type="button" @click="openLegalModal('ticket-consent')" class="border-0 bg-transparent p-0 font-semibold text-slate-600 hover:text-slate-900">Ticket Consent</button>
                </div>
            </main>
        </div>
        @include('legal.modal')
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const notificationList = document.querySelector('.js-header-notification-list');
            const emptyState = document.querySelector('.js-header-notification-empty');
            const badge = document.querySelector('.js-header-notification-badge');

            if (!notificationList) {
                return;
            }

            const parseTimestamp = function (value) {
                const parsed = Date.parse(value || '');
                return Number.isNaN(parsed) ? 0 : parsed;
            };

            const updateNotificationUi = function () {
                const items = Array.from(notificationList.querySelectorAll('.js-header-notification'));
                const unreadCount = items.filter(function (item) {
                    return item.dataset.viewed !== '1';
                }).length;

                if (items.length === 0) {
                    notificationList.classList.add('hidden');
                    if (emptyState) {
                        emptyState.classList.remove('hidden');
                    }
                } else {
                    notificationList.classList.remove('hidden');
                    if (emptyState) {
                        emptyState.classList.add('hidden');
                    }
                }

                if (!badge) {
                    return;
                }

                if (unreadCount <= 0) {
                    badge.remove();
                    return;
                }

                badge.textContent = unreadCount > 9 ? '9+' : String(unreadCount);
                badge.dataset.count = String(unreadCount);
            };

            const removeNotificationsForSeenEvent = function (ticketId, seenAt) {
                const normalizedTicketId = Number(ticketId || 0);
                const seenTimestamp = parseTimestamp(seenAt);
                if (!normalizedTicketId || !seenTimestamp) {
                    return;
                }

                notificationList.querySelectorAll('.js-header-notification').forEach(function (item) {
                    const itemTicketId = Number(item.dataset.ticketId || 0);
                    const itemActivityAt = parseTimestamp(item.dataset.activityAt || '');
                    if (itemTicketId === normalizedTicketId && itemActivityAt > 0 && itemActivityAt <= seenTimestamp) {
                        item.remove();
                    }
                });

                updateNotificationUi();
            };

            window.addEventListener('ticket-notification-seen', function (event) {
                const detail = event && event.detail ? event.detail : {};
                removeNotificationsForSeenEvent(detail.ticketId, detail.seenAt);
            });

            updateNotificationUi();
        });
    </script>
    @stack('scripts')
</body>
</html>


