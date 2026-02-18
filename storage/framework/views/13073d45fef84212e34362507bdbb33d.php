<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - DAR | iOne Resources Ticketing</title>
    <link rel="stylesheet" href="<?php echo e(asset('css/simple-styles.css')); ?>">
    <?php echo app('Illuminate\Foundation\Vite')(['resources/js/app.js']); ?>
</head>
<body class="login-container">
    <div class="login-card">
        <div class="login-header">
            <div style="text-align: center; margin-bottom: 1.5rem;">
                <img src="<?php echo e(asset('images/dar-logo.png')); ?>" alt="DAR Logo" style="max-width: 200px; height: auto;">
            </div>
            <h1 class="login-title">DAR | iOne Resources</h1>
            <p class="login-subtitle">Ticketing System</p>
            <h2 class="login-form-title">Sign in to your account</h2>
        </div>
        <form action="<?php echo e(route('login')); ?>" method="POST">
            <?php echo csrf_field(); ?>
            <div class="form-group">
                <label for="email" class="form-label">Email address</label>
                <input id="email" name="email" type="email" autocomplete="email" required
                       class="form-input <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?> error <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>"
                       placeholder="Email address" value="<?php echo e(old('email')); ?>">
            </div>
            <div class="form-group">
                <label for="password" class="form-label">Password</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required
                       class="form-input" placeholder="Password">
            </div>

            <?php if($errors->any()): ?>
                <div class="form-group">
                    <?php $__currentLoopData = $errors->all(); $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $error): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="error-message"><?php echo e($error); ?></div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                </div>
            <?php endif; ?>

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
                    <a href="<?php echo e(route('register')); ?>">Register here</a>
                </p>
            </div>
        </form>
    </div>
</body>
</html><?php /**PATH C:\Users\iOne5\Desktop\app\Ticketing\DAR\ione-ticketing-system\resources\views/auth/login.blade.php ENDPATH**/ ?>