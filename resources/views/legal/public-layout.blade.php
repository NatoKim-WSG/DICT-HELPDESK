<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title')</title>
    <link rel="icon" type="image/png" href="{{ asset('images/iOne Logo.png') }}">
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
<body
    class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased"
    x-data="{
        darkMode: false,
        init() {
            this.darkMode = document.documentElement.classList.contains('theme-dark');
        },
        toggleDarkMode() {
            const root = document.documentElement;
            this.darkMode = !this.darkMode;
            root.classList.add('theme-switching');
            requestAnimationFrame(() => {
                root.classList.toggle('theme-dark', this.darkMode);
                localStorage.setItem('ione_theme', this.darkMode ? 'dark' : 'light');
                window.setTimeout(() => {
                    root.classList.remove('theme-switching');
                }, 120);
            });
        }
    }"
>
    <div class="motion-page-enter mx-auto w-full max-w-5xl px-4 py-8 sm:px-6 lg:px-8">
        <div class="mb-6 flex flex-wrap items-center justify-between gap-3">
            <a href="{{ route('login') }}" class="app-pressable inline-flex items-center gap-2 rounded-xl border border-slate-200 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">
                <img src="{{ asset('images/iOne Logo.png') }}" alt="iOne logo" class="h-6 w-auto">
                <span>iOne Helpdesk</span>
            </a>
            <div class="flex flex-wrap items-center gap-2 text-xs sm:text-sm">
                <button
                    type="button"
                    @click="toggleDarkMode"
                    class="app-pressable inline-flex h-9 w-9 items-center justify-center rounded-xl border border-slate-200 bg-white text-slate-700 transition hover:bg-slate-50"
                    :aria-pressed="darkMode.toString()"
                    aria-label="Toggle dark mode"
                >
                    <svg x-cloak x-show="!darkMode" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m8.66-10h-1M4.34 12h-1m14.02 6.36-.7-.7M7.02 7.02l-.7-.7m12.02 0-.7.7M7.02 16.98l-.7.7M12 8a4 4 0 100 8 4 4 0 000-8z"></path>
                    </svg>
                    <svg x-cloak x-show="darkMode" class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none;">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.8A9 9 0 1111.2 3a7 7 0 109.8 9.8z"></path>
                    </svg>
                </button>
                <a href="{{ route('legal.terms') }}" class="app-pressable rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-700 hover:bg-slate-50">Terms</a>
                <a href="{{ route('legal.privacy') }}" class="app-pressable rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-700 hover:bg-slate-50">Privacy</a>
                <a href="{{ route('legal.ticket-consent') }}" class="app-pressable rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-slate-700 hover:bg-slate-50">Ticket Consent</a>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm sm:p-8">
            @yield('content')
        </div>
    </div>
</body>
</html>
