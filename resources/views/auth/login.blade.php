<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Login - iOne Resources Inc. Helpdesk</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|space-grotesk:500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="relative min-h-screen overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
        <div class="pointer-events-none absolute -left-20 top-8 h-72 w-72 rounded-full bg-ione-blue-300/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-24 bottom-10 h-80 w-80 rounded-full bg-cyan-300/20 blur-3xl"></div>

        <div class="relative mx-auto grid max-w-5xl overflow-hidden rounded-3xl border border-white/70 bg-white/90 shadow-2xl shadow-slate-200/70 backdrop-blur md:grid-cols-2">
            <div class="hero-glow hidden h-full flex-col justify-between p-10 md:flex">
                <div>
                    <img src="{{ asset('images/ione-logo.png') }}" alt="iOne Logo" class="h-14 w-auto">
                    <h1 class="mt-8 font-display text-3xl font-semibold text-slate-900">iOne Resources Inc. Helpdesk</h1>
                    <p class="mt-3 max-w-sm text-sm text-slate-600">
                        Centralized ticket management for iOne Resources with faster response tracking and clearer workflows.
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
                        <label for="login" class="form-label">Email address</label>
                        <input id="login" name="login" type="email" autocomplete="username" required
                               class="form-input @error('login') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                               placeholder="you@example.com" value="{{ old('login') }}">
                    </div>

                    <div>
                        <label for="password" class="form-label">Password</label>
                        <input id="password" name="password" type="password" autocomplete="current-password" required
                               class="form-input" placeholder="Enter your password">
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
            </div>
        </div>
    </div>
</body>
</html>
