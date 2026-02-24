@php
    $user = auth()->user();
    $isTicketConsole = $user->canAccessAdminTickets();
    $canManageConsole = $user->canManageTickets();
    $isClient = !$isTicketConsole;
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
    $departmentLogo = asset($departmentLogoPath);
    $departmentName = $departmentNameMap[$departmentKey] ?? 'DICT';

    if ($isTicketConsole) {
        $menuItems = [
            ['label' => 'Dashboard', 'icon' => 'home', 'href' => route('admin.dashboard'), 'active' => request()->routeIs('admin.dashboard'), 'disabled' => false],
            ['label' => 'Tickets', 'icon' => 'ticket', 'href' => route('admin.tickets.index'), 'active' => request()->routeIs('admin.tickets.*'), 'disabled' => false],
            ['label' => 'Account Settings', 'icon' => 'user', 'href' => route('account.settings'), 'active' => request()->routeIs('account.settings'), 'disabled' => false],
        ];

        if ($canManageConsole) {
            $menuItems[] = ['label' => 'Users', 'icon' => 'users', 'href' => route('admin.users.index'), 'active' => request()->routeIs('admin.users.*'), 'disabled' => false];
        }
    } else {
        $menuItems = [
            ['label' => 'New Ticket', 'icon' => 'plus', 'href' => route('client.tickets.create'), 'active' => request()->routeIs('client.tickets.create'), 'disabled' => false],
            ['label' => 'Dashboard', 'icon' => 'home', 'href' => route('client.dashboard'), 'active' => request()->routeIs('client.dashboard'), 'disabled' => false],
            ['label' => 'My Tickets', 'icon' => 'ticket', 'href' => route('client.tickets.index'), 'active' => request()->routeIs('client.tickets.index') || request()->routeIs('client.tickets.show'), 'disabled' => false],
            ['label' => 'Account Settings', 'icon' => 'user', 'href' => route('account.settings'), 'active' => request()->routeIs('account.settings'), 'disabled' => false],
        ];
    }

    $iconMap = [
        'home' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 12l9-8 9 8M5 10v10h14V10"/>',
        'ticket' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 6h8m-8 4h8m-8 4h5M5 3h14a2 2 0 012 2v4a2 2 0 000 4v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 000-4V5a2 2 0 012-2z"/>',
        'users' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2M16 3.13a4 4 0 010 7.75M23 21v-2a4 4 0 00-3-3.87M13 7a4 4 0 11-8 0 4 4 0 018 0z"/>',
        'monitor' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 5h16v10H4zM8 21h8M12 15v6"/>',
        'alert' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18A2 2 0 003.53 21h16.94a2 2 0 001.71-3l-8.47-14.14a2 2 0 00-3.42 0z"/>',
        'book' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 19.5A2.5 2.5 0 016.5 17H20M6.5 17A2.5 2.5 0 004 19.5V5a2 2 0 012-2h14v14M6.5 17H20"/>',
        'chart' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M4 20V10m6 10V4m6 16v-7m6 7V7"/>',
        'wallet' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 7h18v10H3zM15 12h4M3 9h18"/>',
        'plus' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 5v14m7-7H5"/>',
        'user' => '<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M20 21a8 8 0 10-16 0M12 11a4 4 0 100-8 4 4 0 000 8z"/>',
    ];
@endphp

<div
    x-show="sidebarOpen"
    x-transition.opacity
    @click="sidebarOpen = false"
    class="fixed inset-0 z-40 bg-slate-900/45 lg:hidden"
    style="display: none;"
></div>

<aside
    class="fixed inset-y-0 left-0 z-50 w-64 -translate-x-full border-r border-[#0b5658] bg-[#033b3d] text-slate-100 transition-transform duration-200 lg:translate-x-0"
    :class="{ 'translate-x-0': sidebarOpen }"
>
    <div class="flex h-full flex-col">
        <div @class([
            'relative flex items-center border-b border-[#0d5053] px-5',
            'h-24' => $canManageConsole,
            'h-28' => $isClient,
            'justify-center' => $canManageConsole,
            'justify-center' => $isClient,
        ])>
            <a href="{{ $isTicketConsole ? route('admin.dashboard') : route('client.dashboard') }}" @class([
                'mx-auto',
                'flex w-full justify-center' => $isTicketConsole,
                'flex w-full justify-center' => $isClient,
            ])>
                <span class="flex flex-col items-center text-center">
                    <span class="inline-flex items-center justify-center px-1 py-1">
                        <img
                            src="{{ $departmentLogo }}"
                            alt="{{ $departmentName }} Logo"
                            class="h-14 w-auto"
                        >
                    </span>
                    <span class="mt-1 text-[11px] font-medium tracking-wide text-slate-300">iOne Resources. Inc</span>
                </span>
            </a>

            <button @click="sidebarOpen = false" @class([
                'inline-flex h-9 w-9 items-center justify-center rounded-lg text-slate-300 hover:bg-white/10 hover:text-white lg:hidden',
                'absolute right-5 top-1/2 -translate-y-1/2' => $canManageConsole,
                'absolute right-5 top-1/2 -translate-y-1/2' => $isClient,
            ]) aria-label="Close sidebar">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                </svg>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4">
            @foreach($menuItems as $item)
                <a
                    href="{{ $item['href'] }}"
                    @class([
                        'group flex items-center gap-3 rounded-xl px-3 py-3 transition',
                        'text-base font-semibold',
                        'bg-white/10 text-white' => $item['active'],
                        'text-slate-200 hover:bg-white/5 hover:text-white' => !$item['active'] && !$item['disabled'],
                        'cursor-not-allowed text-slate-400/80' => $item['disabled'],
                    ])
                    @if($item['disabled']) aria-disabled="true" @endif
                >
                    <svg @class([
                        'shrink-0',
                        'h-5 w-5',
                    ]) fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        {!! $iconMap[$item['icon']] !!}
                    </svg>
                    <span>{{ $item['label'] }}</span>
                    @if($item['disabled'])
                        <span class="ml-auto rounded-md bg-white/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-slate-300">Soon</span>
                    @endif
                </a>
            @endforeach
        </nav>

    </div>
</aside>
