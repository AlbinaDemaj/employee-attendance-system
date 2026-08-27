<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route guard for the three fixed roles, used as `role:admin,manager`.
 */
class EnsureRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            return response()->json(['message' => 'Nuk jeni i kyçur.'], 401);
        }

        if (! in_array($user->role, $roles, true)) {
            return response()->json([
                'message' => 'Ky veprim kërkon një nga këto role: ' . implode(', ', $roles) . '.',
            ], 403);
        }

        return $next($request);
    }
}
