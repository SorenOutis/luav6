<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\PlatformMaintenance;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlatformMaintenance
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! PlatformMaintenance::isEnabled()) {
            return $next($request);
        }

        if ($request->user()?->isSuperAdmin()) {
            return $next($request);
        }

        // Filament panel, health checks, auth session endpoints and compiled
        // assets must stay reachable so a super admin can turn maintenance
        // back off and normal admins keep access to /admin. NOTE: the
        // Livewire endpoint is versioned (livewire-{hash}/update), so match
        // the livewire* prefix rather than the literal livewire/* path —
        // otherwise the Filament login submit is 503'd during maintenance.
        if ($request->is('admin*', 'livewire*', 'filament/*', 'up', 'logout', 'impersonation/leave', 'api/maintenance-status', 'storage/*', 'build/*', 'favicon.png', 'robots.txt', 'sitemap.xml', 'csp/report')) {
            return $next($request);
        }

        // Block student logins with a field error (same UX as the
        // login_enabled toggle) instead of a bare 503 page. Super-admin
        // emails can still sign in via /login or /admin.
        if ($request->is('login') && $request->isMethod('POST')) {
            $user = User::where(Fortify::username(), $request->{Fortify::username()})->first();

            if (! $user?->isSuperAdmin()) {
                throw ValidationException::withMessages([
                    Fortify::username() => [PlatformMaintenance::message()],
                ]);
            }

            return $next($request);
        }

        // Registration writes a new student account, so it stays closed
        // while maintenance is on (mirrors CreateNewUser's toggle guard).
        if ($request->is('register') && $request->isMethod('POST')) {
            throw ValidationException::withMessages([
                'registration' => [PlatformMaintenance::message()],
            ]);
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json([
                'message' => PlatformMaintenance::message(),
            ], 503);
        }

        if (! $request->isMethod('get')) {
            abort(503, PlatformMaintenance::message());
        }

        return Inertia::render('Maintenance', PlatformMaintenance::payload($request->user() !== null))
            ->toResponse($request)
            ->setStatusCode(503);
    }
}
