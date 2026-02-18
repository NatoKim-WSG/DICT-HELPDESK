<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register - DICT | iOne Resources Ticketing</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-gray-100 min-h-screen">
    <!-- Header with Logo -->
    <div class="bg-white shadow-sm">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-4">
            <div class="flex items-center">
                <img src="{{ asset('images/DICT-logo.png') }}" alt="DICT Logo" class="h-10 w-auto mr-3">
                <span class="text-xl font-bold text-ione-blue-600">DICT | iOne Resources</span>
            </div>
        </div>
    </div>

    <!-- Registration Form -->
    <div class="flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-2xl font-bold text-gray-900">
                    Create your account
                </h2>
            </div>
        <form class="mt-8 space-y-6" action="{{ route('register') }}" method="POST">
            @csrf
            <div class="space-y-4">
                <div>
                    <label for="name" class="form-label">Full Name</label>
                    <input id="name" name="name" type="text" required
                           class="form-input @error('name') border-red-500 @enderror"
                           value="{{ old('name') }}">
                    @error('name')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="email" class="form-label">Email Address</label>
                    <input id="email" name="email" type="email" required
                           class="form-input @error('email') border-red-500 @enderror"
                           value="{{ old('email') }}">
                    @error('email')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="phone" class="form-label">Phone Number</label>
                    <input id="phone" name="phone" type="text"
                           class="form-input @error('phone') border-red-500 @enderror"
                           value="{{ old('phone') }}">
                    @error('phone')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="department" class="form-label">Department</label>
                    <input id="department" name="department" type="text"
                           class="form-input @error('department') border-red-500 @enderror"
                           value="{{ old('department') }}">
                    @error('department')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password" class="form-label">Password</label>
                    <input id="password" name="password" type="password" required
                           class="form-input @error('password') border-red-500 @enderror">
                    @error('password')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div>
                    <label for="password_confirmation" class="form-label">Confirm Password</label>
                    <input id="password_confirmation" name="password_confirmation" type="password" required
                           class="form-input">
                </div>
            </div>

            <div>
                <button type="submit" class="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-ione-blue-600 hover:bg-ione-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-ione-blue-500">
                    Create Account
                </button>
            </div>

            <div class="text-center">
                <p class="text-sm text-gray-600">
                    Already have an account?
                    <a href="{{ route('login') }}" class="font-medium text-ione-blue-600 hover:text-ione-blue-500">
                        Sign in here
                    </a>
                </p>
            </div>
        </form>
        </div>
    </div>
</body>
</html>