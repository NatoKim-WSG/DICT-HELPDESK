<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DAR | iOne Resources Ticketing</title>
    <link rel="stylesheet" href="{{ asset('css/simple-styles.css') }}">
    @vite(['resources/js/app.js'])
</head>
<body class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <img src="{{ asset('images/dar-logo.png') }}" alt="DAR Logo" style="max-width: 200px; height: auto;">
            </div>
            <h1 class="login-title">DAR | iOne Resources</h1>
            <p class="login-subtitle">Ticketing System</p>
            <h2 class="login-form-title">Sign in to your account</h2>
        </div>
        <form action="{{ route('login') }}" method="POST">
            @csrf
            <div class="form-group">
                <label for="email" class="form-label">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                       class="form-input @error('email') error @enderror"
                       placeholder="Email address" value="{{ old('email') }}">
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="form-input" placeholder="Password">
            </div>

            @if ($errors->any())
                <div class="form-group">
                    @foreach ($errors->all() as $error)
                        <div class="error-message">{{ $error }}</div>
                    @endforeach
                </div>
            @endif

            <div class="form-group">
                <label>
                    <input id="remember" name="remember" type="checkbox" style="margin-right: 0.5rem;">
                    Remember me
                </label>
            </div>

            <div class="form-group">
                <button type="submit" class="btn btn-primary" style="width: 100%; justify-content: center;">
                    Sign in
                </button>
            </div>

            <div class="text-center">
                <p>
                    Don't have an account?
                    <a href="{{ route('register') }}">Register here</a>
                </p>
            </div>
        </form>
    </div>
</body>
</html>