<?php

namespace App\Actions\Fortify;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class CheckLoginEnabled
{
    /**
     * Handle the incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  callable  $next
     * @return mixed
     */
    public function handle(Request $request, $next)
    {
        if (! (bool) Setting::get('login_enabled', true)) {
            // Allow admins to login even if login is disabled for students
            // We check this by seeing if the user exists and is an admin
            $user = \App\Models\User::where(Fortify::username(), $request->{Fortify::username()})->first();

            if (! $user || ! $user->is_admin) {
                throw ValidationException::withMessages([
                    Fortify::username() => [Setting::get('login_disabled_message', 'Login is currently disabled.')],
                ]);
            }
        }

        return $next($request);
    }
}
