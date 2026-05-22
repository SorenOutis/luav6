<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

class EnsureLoginIsEnabled
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Only check if it's a login POST request
        if ($request->is('login') && $request->isMethod('POST')) {
            if (! (bool) Setting::get('login_enabled', true)) {
                // Check if user is an admin
                $user = User::where(Fortify::username(), $request->{Fortify::username()})->first();

                if (! $user || ! $user->is_admin) {
                    throw ValidationException::withMessages([
                        Fortify::username() => [Setting::get('login_disabled_message', 'Login is currently disabled.')],
                    ]);
                }
            }
        }

        return $next($request);
    }
}
