<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Register - DICT | iOne Resources Ticketing</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|space-grotesk:500,600,700&display=swap" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="font-sans antialiased">
    <div class="relative min-h-screen overflow-hidden px-4 py-10 sm:px-6 lg:px-8">
        <div class="pointer-events-none absolute -left-16 top-0 h-72 w-72 rounded-full bg-sky-300/20 blur-3xl"></div>
        <div class="pointer-events-none absolute -right-20 bottom-8 h-80 w-80 rounded-full bg-ione-blue-300/20 blur-3xl"></div>

        <div class="relative mx-auto max-w-3xl rounded-3xl border border-white/70 bg-white/92 p-8 shadow-2xl shadow-slate-200/70 backdrop-blur sm:p-10">
            <div class="mb-8 flex items-center gap-3">
                <img src="{{ asset('images/DICT-logo.png') }}" alt="DICT Logo" class="h-11 w-auto">
                <div>
                    <h1 class="font-display text-2xl font-semibold text-slate-900">Create your account</h1>
                    <p class="text-sm text-slate-500">Join the DICT helpdesk system.</p>
                </div>
            </div>

            <form class="grid grid-cols-1 gap-5 sm:grid-cols-2" action="{{ route('register') }}" method="POST">
                @csrf

                <div class="sm:col-span-2">
                    <label for="name" class="form-label">Full Name</label>
                    <input id="name" name="name" type="text" required
                           class="form-input @error('name') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                           value="{{ old('name') }}">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="sm:col-span-2">
                    <label for="email" class="form-label">Email Address</label>
                    <input id="email" name="email" type="email" required
                           class="form-input @error('email') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                           value="{{ old('email') }}">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="form-label">Phone Number</label>
                    <input id="phone" name="phone" type="text"
                           class="form-input @error('phone') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                           value="{{ old('phone') }}">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="department" class="form-label">Department</label>
                    <input id="department" name="department" type="text"
                           class="form-input @error('department') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror"
                           value="{{ old('department') }}">
                    @error('department')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="form-label">Password</label>
                    <input id="password" name="password" type="password" required
                           class="form-input @error('password') border-red-300 focus:border-red-400 focus:ring-red-200 @enderror">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required class="form-input">
                </div>

                <div class="sm:col-span-2 mt-1">
                    <button type="submit" class="btn-primary w-full">
                        Create Account
                    </button>
                </div>
            </form>

            <p class="mt-6 text-center text-sm text-slate-600">
                Already have an account?
                <a href="{{ route('login') }}" class="font-semibold text-ione-blue-600 hover:text-ione-blue-700">
                    Sign in here
                </a>
            </p>
        </div>
    </div>
</body>
</html>
