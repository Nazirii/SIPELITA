<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
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
        if (!$request->user()) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $userRole = $request->user()->role;
        Log::info('RoleMiddleware check', [
            'user_id' => $request->user()->id,
            'user_role' => $userRole,
            'allowed_roles' => $roles,
            'match' => in_array($userRole, $roles)
        ]);

        if (!in_array($userRole, $roles)) {
            return response()->json([
                'message' => 'Forbidden: Role not allowed',
                'your_role' => $userRole,
                'allowed_roles' => $roles
            ], 403);
        }
        return $next($request);
    }
}
