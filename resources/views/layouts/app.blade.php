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
<body class="font-sans antialiased text-slate-900">
    <div class="pointer-events-none fixed inset-0 -z-10 overflow-hidden">
        <div class="absolute -left-28 top-16 h-80 w-80 rounded-full bg-ione-blue-300/20 blur-3xl"></div>
        <div class="absolute -right-24 top-0 h-96 w-96 rounded-full bg-sky-300/20 blur-3xl"></div>
        <div class="absolute bottom-0 left-1/2 h-64 w-64 -translate-x-1/2 rounded-full bg-cyan-200/20 blur-3xl"></div>
    </div>

    <div class="min-h-screen">
        @include('layouts.navigation')

        <!-- Page Heading -->
        @if (isset($header))
            <header class="app-shell mx-auto py-6">
                <div class="panel hero-glow px-6 py-6 sm:px-8">
                    {{ $header }}
                </div>
            </header>
        @endif

        <!-- Page Content -->
        <main class="pb-10 pt-6">
            @if (session('success'))
                <div class="app-shell mx-auto mb-6">
                    <div class="panel border-emerald-200/80 bg-emerald-50/95 px-4 py-3 text-emerald-800" role="alert">
                        <span class="block sm:inline">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            @if (session('error'))
                <div class="app-shell mx-auto mb-6">
                    <div class="panel border-red-200/80 bg-red-50/95 px-4 py-3 text-red-800" role="alert">
                        <span class="block sm:inline">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            @yield('content')
        </main>
    </div>
</body>
</html>
