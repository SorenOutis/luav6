<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Fortify;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fortify logout response that keeps students inside the installed
 * Community Lounge PWA.
 *
 * The browser posts app=community when logging out from the standalone
 * Lounge window (LogoutConfirmationModal). Instead of dropping the student on
 * the public welcome page, they land back on the Lounge-branded login screen,
 * which re-arms the post-login redirect to /community.
 */
class LogoutResponse implements LogoutResponseContract
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  Request  $request
     */
    public function toResponse($request): Response
    {
        if ($request->wantsJson()) {
            return new JsonResponse('', 204);
        }

        if ($request->input('app') === 'community') {
            return redirect()->route('login', ['app' => 'community']);
        }

        return redirect(Fortify::redirects('logout', '/'));
    }
}
