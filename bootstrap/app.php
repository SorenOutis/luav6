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

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
                        'response' => 'Sorry, something went wrong. Please try again in a moment.',
                    ], 429);
                }
            }

            return $response;
        });
    })->create();
