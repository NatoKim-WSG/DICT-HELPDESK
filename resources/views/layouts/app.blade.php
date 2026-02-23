<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'DICT | iOne Resources Ticketing System')</title>

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|space-grotesk:500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f3f5f7] font-sans antialiased text-slate-900">
    <div class="min-h-screen bg-[#f3f5f7]" x-data="{ sidebarOpen: false }">
        @include('layouts.navigation')

        <div class="min-h-screen lg:pl-64">
            <header class="sticky top-0 z-30 border-b border-slate-200 bg-[#f3f5f7]/95 backdrop-blur">
                @php
                    $user = auth()->user();
                    $isClient = !$user->canAccessAdminTickets();
                    $isAdmin = $user->canManageTickets();
                    $departmentRaw = strtolower(trim((string) $user->department));
                    $departmentKey = 'dict';
                    if (str_contains($departmentRaw, 'ione')) {
                        $departmentKey = 'ione';
                    } elseif (str_contains($departmentRaw, 'deped')) {
                        $departmentKey = 'deped';
                    } elseif (str_contains($departmentRaw, 'dar')) {
                        $departmentKey = 'dar';
                    } elseif (str_contains($departmentRaw, 'dict')) {
                        $departmentKey = 'dict';
                    }

                    $departmentLogoMap = [
                        'ione' => 'images/ione-logo.png',
                        'dict' => 'images/DICT-logo.png',
                        'deped' => 'images/deped-logo.png',
                        'dar' => 'images/dar-logo.png',
                    ];
                    $departmentNameMap = [
                        'ione' => 'iOne',
                        'dict' => 'DICT',
                        'deped' => 'DEPED',
                        'dar' => 'DAR',
                    ];

                    $departmentLogoPath = $departmentLogoMap[$departmentKey] ?? 'images/DICT-logo.png';
                    if (!file_exists(public_path($departmentLogoPath))) {
                        $departmentLogoPath = 'images/DICT-logo.png';
                    }
                    $clientCompanyName = $departmentNameMap[$departmentKey] ?? 'DICT';
                    $clientCompanyLogo = asset($departmentLogoPath);
                    $notifications = $headerNotifications ?? collect();
                    $notificationCount = $notifications->count();
                @endphp

                <div class="relative flex h-20 items-center px-4 sm:px-6 lg:px-8">
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
                    </div>

                    <div class="pointer-events-none absolute left-1/2 hidden w-full -translate-x-1/2 px-16 sm:block lg:px-24">
                        <div @class([
                            'pointer-events-auto relative w-full',
                            'mx-auto max-w-3xl' => $isClient,
                            'mx-auto max-w-xl' => !$isClient,
                        ])>
                            <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="m21 21-4.35-4.35m1.85-5.15a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                            </svg>
                            <input
                                type="text"
                                class="h-12 w-full rounded-2xl border border-slate-300 bg-white pl-10 pr-4 text-sm text-slate-700 placeholder-slate-400 focus:border-[#0f8d88] focus:outline-none focus:ring-2 focus:ring-[#0f8d88]/20"
                                placeholder="{{ $isClient ? 'Search' : 'Search tickets, users...' }}"
                            >
                        </div>
                    </div>

                    <div class="relative z-10 ml-auto flex items-center gap-2 sm:gap-3">
                        @if($isClient)
                            <a href="{{ route('client.tickets.create') }}" class="inline-flex items-center rounded-2xl bg-[#033b3d] px-6 py-3 text-base font-bold text-white shadow-sm transition hover:bg-[#022a2c]">
                                New ticket
                            </a>
                        @endif

                        @if($user->canAccessAdminTickets())
                            <span class="hidden items-center rounded-xl border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 md:inline-flex">
                                {{ $isAdmin ? 'Admin Console' : 'Technician Console' }}
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
                                    <span class="absolute -right-1 -top-1 inline-flex h-5 min-w-[20px] items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white">
                                        {{ $notificationCount > 9 ? '9+' : $notificationCount }}
                                    </span>
                                @endif
                            </button>

                            <div
                                x-show="notificationOpen"
                                @click.away="notificationOpen = false"
                                x-transition
                                class="absolute right-0 z-40 mt-2 w-80 max-w-[calc(100vw-1rem)] overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-lg"
                            >
                                <div class="border-b border-slate-200 px-4 py-3">
                                    <h3 class="text-sm font-semibold text-slate-900">Notifications</h3>
                                </div>
                                @if($notificationCount > 0)
                                    <div class="max-h-72 overflow-y-auto">
                                        @foreach($notifications as $notification)
                                            <div class="flex items-start gap-2 border-b border-slate-100 px-2 py-1 hover:bg-slate-50">
                                                <a href="{{ $notification['url'] }}" class="block min-w-0 flex-1 px-2 py-2">
                                                    <p class="text-sm font-semibold text-slate-900">{{ $notification['title'] }}</p>
                                                    <p class="mt-0.5 truncate text-sm text-slate-600">{{ $notification['meta'] }}</p>
                                                    <p class="mt-1 text-xs text-slate-400">{{ $notification['time'] }}</p>
                                                </a>
                                                @if(!empty($notification['can_dismiss']) && !empty($notification['dismiss_url']) && !empty($notification['key']))
                                                    <form method="POST" action="{{ $notification['dismiss_url'] }}" class="pt-2">
                                                        @csrf
                                                        <input type="hidden" name="notification_key" value="{{ $notification['key'] }}">
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
                                @else
                                    <div class="px-4 py-6 text-center">
                                        <p class="text-sm font-semibold text-slate-700">No notifications</p>
                                        <p class="mt-1 text-xs text-slate-500">You are all caught up.</p>
                                    </div>
                                @endif
                            </div>
                        </div>

                        <div class="relative" x-data="{ open: false }">
                            <button @click="open = !open" class="inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 sm:px-4">
                                <span class="inline-flex h-11 w-11 items-center justify-center overflow-hidden rounded-full border border-slate-200 bg-white">
                                    <img src="{{ $clientCompanyLogo }}" alt="{{ $clientCompanyName }} logo" class="h-8 w-8 object-contain">
                                </span>
                                <span class="hidden text-left sm:block">
                                    <span class="block max-w-[13rem] truncate text-base font-semibold text-slate-800">{{ $user->name }}</span>
                                    <span class="block text-sm text-slate-500">{{ $clientCompanyName }}</span>
                                </span>
                            </button>

                            <div
                                x-show="open"
                                @click.away="open = false"
                                x-transition
                                class="absolute right-0 z-40 mt-2 w-52 overflow-hidden rounded-2xl border border-slate-200 bg-white py-1 shadow-lg"
                            >
                                <a href="{{ route('account.settings') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">Account Settings</a>
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
            </main>
        </div>
    </div>
    @stack('scripts')
</body>
</html>
