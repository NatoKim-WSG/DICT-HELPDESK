<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, ...$roles): Response
    {
        if (!auth()->check()) {
            return redirect()->route('login');
        }

        $user = auth()->user();

        if (empty($roles)) {
            return $next($request);
        }

        $normalizedCurrentRole = User::normalizeRole($user->role);
        $normalizedAllowedRoles = array_map(static fn (string $role): string => User::normalizeRole($role), $roles);

        if (in_array($normalizedCurrentRole, $normalizedAllowedRoles, true)) {
            return $next($request);
        }

        abort(403, 'Access denied. Insufficient permissions.');
    }
}
