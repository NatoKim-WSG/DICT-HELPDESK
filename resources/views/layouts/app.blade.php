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
        $supportLogoUrl = \App\Models\User::supportLogoUrl();
        $tabDepartmentBrand = $tabUser
            ? \App\Models\User::departmentBrandAssets($tabUser->department, $tabUser->role)
            : \App\Models\User::departmentBrandAssets(\App\Models\User::supportDepartment());

        $tabPageLabel = '';
        if ($tabYieldedTitle !== '') {
            $titleSuffixes = array_filter([
                config('app.name'),
                \App\Models\User::supportOrganizationName(),
                \App\Models\User::supportBrandName(),
            ]);

            $tabPageLabel = $tabYieldedTitle;
            foreach ($titleSuffixes as $titleSuffix) {
                $tabPageLabel = preg_replace('/\s*[-|]\s*'.preg_quote((string) $titleSuffix, '/').'.*$/i', '', $tabPageLabel) ?? $tabPageLabel;
            }
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
    <link rel="icon" type="image/png" href="{{ $supportLogoUrl }}">
    <link rel="shortcut icon" href="{{ $supportLogoUrl }}">
    <link rel="apple-touch-icon" href="{{ $supportLogoUrl }}">
    <link rel="preload" as="image" href="{{ $supportLogoUrl }}">
    @if($tabUser)
        <link rel="preload" as="image" href="{{ $tabDepartmentBrand['logo_url'] }}">
    @endif

    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|space-grotesk:500,600,700&display=swap" rel="stylesheet" />
    @include('partials.theme-initializer')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f3f5f7] font-sans antialiased text-slate-900">
    <div
        class="min-h-screen bg-[#f3f5f7]"
        x-data="appShellState"
    >
        @include('layouts.navigation')

        <div class="min-h-screen lg:pl-64">
            @include('layouts.partials.app-header')

            <main class="motion-page-enter px-4 py-6 sm:px-6 lg:px-8">
                @if (session('success') && !session('suppress_success_banner'))
                    <div class="app-success-alert mb-5 rounded-xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" role="alert">
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
        @include('legal.modal')
    </div>
</body>
</html>


