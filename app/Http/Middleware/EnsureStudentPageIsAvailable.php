<?php

namespace App\Http\Middleware;

use App\Support\StudentPageRegistry;
use Closure;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

class EnsureStudentPageIsAvailable
{
    public function handle(Request $request, Closure $next, string $pageKey): Response
    {
        $control = StudentPageRegistry::controlFor($pageKey);

        if ($request->user()?->is_admin || $control['mode'] !== StudentPageRegistry::MODE_DISABLED) {
            return $next($request);
        }

        if (! $request->isMethod('get')) {
            abort(403, $control['message'] ?: 'This student page is currently unavailable.');
        }

        return Inertia::render('StudentPageUnavailable', [
            'pageTitle' => StudentPageRegistry::pages()[$pageKey]['label'] ?? 'Student Page',
            'message' => $control['message'] ?: 'This page is temporarily unavailable. Please check back later.',
        ])->toResponse($request)->setStatusCode(423);
    }
}
