<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectBannedUsers
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (
            $user?->is_banned &&
            ! $request->routeIs('dashboard') &&
            ! $request->routeIs('logout')
        ) {
            return redirect()->route('dashboard');
        }

        return $next($request);
    }
}

