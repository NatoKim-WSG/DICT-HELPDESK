<?php

namespace App\Http\Controllers;

use App\Models\CredentialHandoff;
use App\Models\User;
use App\Services\SystemLogService;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;

class AuthController extends Controller
{
    private const LOGIN_ACCOUNT_MAX_ATTEMPTS = 8;

    private const LOGIN_ACCOUNT_LOCKOUT_SECONDS = 900;

    public function __construct(
        private SystemLogService $systemLogs,
    ) {}

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect($this->dashboardPath(Auth::user()));
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'login' => 'required|string|max:255',
            'password' => 'required',
            'remember' => 'nullable|boolean',
        ]);

        $loginInput = trim($request->string('login')->toString());
        $remember = $request->boolean('remember');
        $isEmailLogin = filter_var($loginInput, FILTER_VALIDATE_EMAIL) !== false;
        $accountLockKey = $this->loginAccountThrottleKey($loginInput);

        if (RateLimiter::tooManyAttempts($accountLockKey, self::LOGIN_ACCOUNT_MAX_ATTEMPTS)) {
            $secondsUntilRetry = RateLimiter::availableIn($accountLockKey);

            return back()->withErrors([
                'login' => $this->loginLockoutErrorMessage($secondsUntilRetry),
            ])->onlyInput('login');
        }

        if ($isEmailLogin) {
            // Email login remains case-insensitive.
            /** @var EloquentCollection<int, User> $matchedUsers */
            $matchedUsers = User::whereRaw('LOWER(email) = ?', [strtolower($loginInput)])
                ->get();
        } else {
            // Username login is case-sensitive.
            /** @var EloquentCollection<int, User> $matchedUsers */
            $matchedUsers = $this->usernameMatchQuery($loginInput)->get();
            if ($matchedUsers->count() > 1) {
                RateLimiter::hit($accountLockKey, self::LOGIN_ACCOUNT_LOCKOUT_SECONDS);

                return back()->withErrors([
                    'login' => 'Multiple accounts share this username. Please sign in with your email address.',
                ])->onlyInput('login');
            }
        }

        $activeUser = $matchedUsers->first(function (User $user) use ($request) {
            return $user->is_active && Hash::check($request->password, $user->password);
        });

        if ($activeUser) {
            Auth::login($activeUser, $remember);
            $request->session()->regenerate();
            RateLimiter::clear($accountLockKey);
            $this->systemLogs->record(
                'auth.login',
                'User signed in.',
                [
                    'category' => 'auth',
                    'actor' => $activeUser,
                    'target_type' => User::class,
                    'target_id' => $activeUser->id,
                    'metadata' => [
                        'method' => $isEmailLogin ? 'email' : 'username',
                    ],
                    'request' => $request,
                ]
            );

            return redirect()->intended($this->dashboardPath($activeUser));
        }

        $inactiveMatch = $matchedUsers->first(function (User $user) use ($request) {
            return ! $user->is_active && Hash::check($request->password, $user->password);
        });

        if ($inactiveMatch) {
            RateLimiter::hit($accountLockKey, self::LOGIN_ACCOUNT_LOCKOUT_SECONDS);

            return back()->withErrors([
                'login' => 'Your account is inactive. Please contact an administrator.',
            ])->onlyInput('login');
        }

        RateLimiter::hit($accountLockKey, self::LOGIN_ACCOUNT_LOCKOUT_SECONDS);

        return back()->withErrors([
            'login' => 'The provided credentials do not match our records.',
        ])->onlyInput('login');
    }

    public function logout(Request $request)
    {
        $user = $request->user();
        if ($user) {
            $this->systemLogs->record(
                'auth.logout',
                'User signed out.',
                [
                    'category' => 'auth',
                    'actor' => $user,
                    'target_type' => User::class,
                    'target_id' => $user->id,
                    'request' => $request,
                ]
            );
        }

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    public function accountSettings()
    {
        $user = Auth::user();
        if ($user->isClient()) {
            return redirect()->route('client.dashboard')
                ->with('error', 'Client accounts cannot access account settings.');
        }

        $departmentOptions = User::allowedDepartments();

        return view('account.settings', compact('user', 'departmentOptions'));
    }

    public function updateAccountSettings(Request $request)
    {
        $user = Auth::user();
        if ($user->isClient()) {
            return redirect()->route('client.dashboard')
                ->with('error', 'Client accounts cannot access account settings.');
        }

        if ($user->is_profile_locked) {
            return redirect()->route('account.settings')
                ->with('error', 'Your profile editing is locked. Please contact an administrator.');
        }

        $normalizedRole = $user->normalizedRole();
        $isSuperAdmin = in_array($normalizedRole, [User::ROLE_SHADOW, User::ROLE_ADMIN], true);
        $isUsernameLocked = in_array($normalizedRole, [User::ROLE_SUPER_USER, User::ROLE_TECHNICAL], true);
        $departmentRules = $isSuperAdmin
            ? ['required', Rule::in(User::allowedDepartments())]
            : ['nullable'];
        $email = $isSuperAdmin
            ? $request->string('email')->toString()
            : (string) $user->email;
        $requiresCurrentPassword = ($isSuperAdmin && $email !== $user->email) || $request->filled('password');

        $rules = [
            'name' => 'required|string|max:255',
            'email' => $isSuperAdmin
                ? 'required|email|unique:users,email,'.$user->id
                : 'nullable',
            'phone' => 'nullable|string|max:20',
            'department' => $departmentRules,
            'password' => 'nullable|string|min:8|confirmed',
        ];

        if ($requiresCurrentPassword) {
            $rules['current_password'] = ['required', 'current_password'];
        } else {
            $rules['current_password'] = ['nullable'];
        }

        $request->validate($rules);

        $updateData = [
            'name' => $isUsernameLocked
                ? (string) $user->name
                : $request->string('name')->toString(),
            'phone' => $request->string('phone')->toString(),
        ];

        if ($isSuperAdmin) {
            $updateData['email'] = $email;
            $updateData['department'] = $request->string('department')->toString();
        }

        if ($request->filled('password')) {
            $updateData['password'] = Hash::make($request->password);
            CredentialHandoff::query()
                ->where('target_user_id', $user->id)
                ->delete();
        }

        $user->fill($updateData);

        if (! $user->isDirty()) {
            return redirect()->route('account.settings')
                ->with('success', 'No changes were detected.');
        }

        $changedFields = array_keys($user->getDirty());
        $nonSensitiveChangedFields = array_values(array_filter(
            $changedFields,
            static fn (string $field): bool => $field !== 'password'
        ));

        $user->save();
        $this->systemLogs->record(
            'account.settings.updated',
            'Updated account settings.',
            [
                'category' => 'account',
                'target_type' => User::class,
                'target_id' => $user->id,
                'metadata' => [
                    'changed_fields' => $nonSensitiveChangedFields,
                ],
                'request' => $request,
            ]
        );

        return redirect()->route('account.settings')
            ->with('success', 'Account settings updated successfully.');
    }

    private function dashboardPath(User $user): string
    {
        if ($user->canAccessAdminTickets()) {
            return '/admin/dashboard';
        }

        return '/client/dashboard';
    }

    /**
     * @return Builder<User>
     */
    private function usernameMatchQuery(string $loginInput): Builder
    {
        /** @var Connection $connection */
        $connection = User::query()->getQuery()->getConnection();
        $driver = $connection->getDriverName();

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            return User::whereRaw('BINARY name = ?', [$loginInput]);
        }

        return User::where('name', $loginInput);
    }

    private function loginAccountThrottleKey(string $loginInput): string
    {
        $normalizedLogin = mb_strtolower(trim($loginInput));

        return 'auth:login-account:'.sha1($normalizedLogin);
    }

    private function loginLockoutErrorMessage(int $secondsUntilRetry): string
    {
        $retryAfterSeconds = max(1, $secondsUntilRetry);
        $retryAfterMinutes = (int) ceil($retryAfterSeconds / 60);
        $unit = $retryAfterMinutes === 1 ? 'minute' : 'minutes';

        return "Too many sign-in attempts for this account. Try again in {$retryAfterMinutes} {$unit}.";
    }
}
