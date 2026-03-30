<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @php($supportLogoUrl = \App\Models\User::supportLogoUrl())
    <title>Sign In | {{ config('app.name') }}</title>
    <link rel="icon" type="image/png" href="{{ $supportLogoUrl }}">
    <link rel="shortcut icon" href="{{ $supportLogoUrl }}">
    <link rel="apple-touch-icon" href="{{ $supportLogoUrl }}">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|space-grotesk:500,600,700&display=swap" rel="stylesheet" />
    @include('partials.theme-initializer')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body
    class="font-sans antialiased"
    x-data="loginPageState"
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
            <svg x-cloak :class="darkMode ? 'hidden' : ''" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m8.66-10h-1M4.34 12h-1m14.02 6.36-.7-.7M7.02 7.02l-.7-.7m12.02 0-.7.7M7.02 16.98l-.7.7M12 8a4 4 0 100 8 4 4 0 000-8z"></path>
            </svg>
            <svg x-cloak :class="darkMode ? '' : 'hidden'" class="h-5 w-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 12.8A9 9 0 1111.2 3a7 7 0 109.8 9.8z"></path>
            </svg>
        </button>

        <div class="login-card relative mx-auto grid max-w-5xl overflow-hidden rounded-3xl border border-slate-200 shadow-2xl shadow-slate-200/70 backdrop-blur md:grid-cols-2">
            <div class="login-hero hero-glow hidden h-full flex-col justify-between p-10 md:flex">
                <div>
                    <img src="{{ $supportLogoUrl }}" alt="{{ \App\Models\User::supportOrganizationName() }} logo" class="h-14 w-auto">
                    <h1 class="mt-8 font-display text-3xl font-semibold text-slate-900">{{ config('app.name') }}</h1>
                    <p class="mt-3 max-w-sm text-sm text-slate-600">
                        Managed by {{ \App\Models\User::supportOrganizationName() }} for secure ticket intake and support coordination.
                    </p>
                </div>
                <p class="text-xs font-medium uppercase tracking-[0.2em] text-slate-500">Secure Internal Portal</p>
            </div>

            <div class="p-8 sm:p-10">
                <div class="mb-8">
                    <h2 class="font-display text-2xl font-semibold text-slate-900">Sign in</h2>
                </div>

                <form action="{{ route('login') }}" method="POST" class="space-y-5">
                    @csrf

                    <div>
                        <label for="login" class="form-label">Username or Email</label>
                        <input id="login" name="login" type="text" autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required
                               class="login-input form-input @error('login') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                               placeholder="Username or email" value="{{ old('login') }}">
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
