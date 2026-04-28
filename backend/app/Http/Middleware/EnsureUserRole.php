<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserRole
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED, 'Unauthenticated.');
        }

        if (! in_array($user->role, $roles, true)) {
            abort(Response::HTTP_FORBIDDEN, 'Forbidden: insufficient role privileges.');
        }

        return $next($request);
    }
}
