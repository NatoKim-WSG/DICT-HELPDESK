<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Sign In | iOne Helpdesk</title>
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
    <style>
        .login-card {
            background: rgba(255, 255, 255, 0.96);
        }

        .theme-dark .login-card {
            background: linear-gradient(160deg, #161b22 0%, #11161d 100%) !important;
            border-color: #2b323c !important;
            box-shadow: 0 24px 48px rgba(0, 0, 0, 0.58) !important;
        }

        .theme-dark .login-hero {
            background: linear-gradient(140deg, rgba(15, 118, 110, 0.12), rgba(14, 165, 233, 0.07)) !important;
            border-right: 1px solid #2b323c;
        }

        .theme-dark .login-orb-left {
            background-color: rgba(45, 119, 155, 0.12) !important;
            opacity: 0.45;
        }

        .theme-dark .login-orb-right {
            background-color: rgba(18, 106, 126, 0.12) !important;
            opacity: 0.4;
        }

        .login-toggle {
            background: rgba(255, 255, 255, 0.9);
        }

        .theme-dark .login-toggle {
            background: #161b22 !important;
            border-color: #2b323c !important;
            color: #e7ebf2 !important;
        }

        .theme-dark .login-input {
            background-color: #1d232b !important;
            border-color: #2b323c !important;
            color: #e7ebf2 !important;
        }

        .theme-dark .login-input::placeholder {
            color: #94a3b8 !important;
        }
    </style>
</head>
<body
    class="font-sans antialiased"
    x-data="{
        darkMode: false,
        legalModalOpen: false,
        legalModalTab: 'terms',
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
    <div class="motion-page-enter relative min-h-screen overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
        <div class="login-orb-left pointer-events-none absolute -left-20 top-8 h-72 w-72 rounded-full bg-ione-blue-300/20 blur-3xl"></div>
        <div class="login-orb-right pointer-events-none absolute -right-24 bottom-10 h-80 w-80 rounded-full bg-cyan-300/20 blur-3xl"></div>

        <button
            type="button"
            @click="toggleDarkMode"
            class="app-pressable login-toggle fixed right-4 top-4 z-30 inline-flex h-11 w-11 items-center justify-center rounded-xl border border-slate-200 text-slate-700 transition hover:bg-white sm:right-6 sm:top-6"
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

        <div class="login-card relative mx-auto grid max-w-5xl overflow-hidden rounded-3xl border border-slate-200 shadow-2xl shadow-slate-200/70 backdrop-blur md:grid-cols-2">
            <div class="login-hero hero-glow hidden h-full flex-col justify-between p-10 md:flex">
                <div>
                    <img src="{{ asset('images/iOne Logo.png') }}" alt="iOne Logo" class="h-14 w-auto">
                    <h1 class="mt-8 font-display text-3xl font-semibold text-slate-900">iOne Resources Inc. Helpdesk</h1>
                    <p class="mt-3 max-w-sm text-sm text-slate-600">
                        Centralized ticket management for iOne Resources.
                    </p>
                </div>
                <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Secure Internal Portal</p>
            </div>

            <div class="p-8 sm:p-10">
                <div class="mb-8">
                    <h2 class="font-display text-2xl font-semibold text-slate-900">Sign in</h2>
                    <p class="mt-1 text-sm text-slate-500">Access your helpdesk account.</p>
                </div>

                <form action="{{ route('login') }}" method="POST" class="space-y-5">
                    @csrf

                    <div>
                        <label for="login" class="form-label">Email or Username</label>
                        <input id="login" name="login" type="text" autocomplete="username" required
                               class="login-input form-input @error('login') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                               placeholder="you@example.com or Juan Dela Cruz" value="{{ old('login') }}">
                        <p class="mt-1 text-xs text-slate-500">Full name login is case-sensitive.</p>
                    </div>

                    <div>
                        <label for="password" class="form-label">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="login-input form-input" placeholder="Enter your password">
                    </div>

                    @if ($errors->any())
                        <div class="rounded-xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                            @foreach ($errors->all() as $error)
                                <p>{{ $error }}</p>
                            @endforeach
                        </div>
                    @endif

                    <label class="inline-flex items-center text-sm text-slate-600">
                        <input id="remember" name="remember" type="checkbox" value="1" {{ old('remember') ? 'checked' : '' }} class="mr-2 rounded border-slate-300 text-ione-blue-600 focus:ring-ione-blue-500">
                        Remember me
                    </label>

                    <button type="submit" class="btn-primary w-full">
                        Sign in
                    </button>
                </form>

                <p class="mt-6 text-center text-sm text-slate-600">
                    Account access is managed by your system administrator.
                </p>
                <p class="mt-3 text-center text-xs text-slate-500">
                    By signing in, you agree to the
                    <button type="button" @click="openLegalModal('terms')" class="app-menu-link border-0 bg-transparent p-0 font-semibold text-ione-blue-700 hover:text-ione-blue-900">Terms</button>,
                    <button type="button" @click="openLegalModal('privacy')" class="app-menu-link border-0 bg-transparent p-0 font-semibold text-ione-blue-700 hover:text-ione-blue-900">Privacy Notice</button>,
                    and
                    <button type="button" @click="openLegalModal('ticket-consent')" class="app-menu-link border-0 bg-transparent p-0 font-semibold text-ione-blue-700 hover:text-ione-blue-900">Ticket Consent</button>.
                </p>
            </div>
        </div>
    </div>
    @include('legal.modal')
</body>
</html>
