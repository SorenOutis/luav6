<?php

use App\Http\Middleware\EnsureLoginIsEnabled;
use App\Http\Middleware\EnsureStudentPageIsAvailable;
use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectBannedUsers;
use App\Http\Middleware\SanitizeInput;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Render terminates TLS at its load balancer and forwards plain HTTP to
        // the container, so trust its X-Forwarded-* headers. Without this,
        // Laravel generates http:// asset URLs (Vite, Filament, asset()) and the
        // browser blocks them as mixed content on the HTTPS site.
        $middleware->trustProxies(
            at: '*',
            headers: Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO,
        );

        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);
        $middleware->alias([
            'banned.redirect' => RedirectBannedUsers::class,
            'student.page' => EnsureStudentPageIsAvailable::class,
        ]);

        $middleware->web(append: [
            EnsureLoginIsEnabled::class,
            SanitizeInput::class,
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->respond(function ($response, $e, $request) {
            if ($response->getStatusCode() === 429) {
                if ($request->header('X-Inertia')) {
                    return back()->withErrors([
                        'email' => 'Too many requests. Please try again later.',
                    ]);
                }

                if ($request->expectsJson() || $request->is('api/*')) {
                    return response()->json([
                        'response' => 'You are sending messages too quickly. Please wait a moment and try again.',
                        'error' => [
                            'id' => (string) Str::uuid(),
                            'type' => $e::class,
                            'message' => 'Too many requests. Please try again later.',
                        ],
                    ], 429);
                }
            }

            return $response;
        });
    })->create();
