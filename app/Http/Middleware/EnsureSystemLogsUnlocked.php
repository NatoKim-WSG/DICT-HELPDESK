<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemLogsUnlocked
{
    public const SESSION_KEY = 'system_logs_unlocked_until';

    public function handle(Request $request, Closure $next): Response
    {
        $unlockedUntil = $request->session()->get(self::SESSION_KEY);

        try {
            $unlockedUntilAt = is_string($unlockedUntil) ? Carbon::parse($unlockedUntil) : null;
        } catch (\Throwable) {
            $unlockedUntilAt = null;
        }

        if (! $unlockedUntilAt || now()->greaterThanOrEqualTo($unlockedUntilAt)) {
            $request->session()->forget(self::SESSION_KEY);

            return redirect()->route('admin.system-logs.unlock.show')
                ->with('error', 'Enter your shadow account password to access system logs.');
        }

        return $next($request);
    }
}
