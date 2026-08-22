<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Persists onboarding tour completion on the user account.
 *
 * The client also writes localStorage for an instant, offline-safe result;
 * this endpoint is what makes "done or skipped, never again" hold across
 * devices, browsers and cleared site data.
 */
class OnboardingController extends Controller
{
    /** Tour ids the client is allowed to record, keeping junk out of the column. */
    private const ALLOWED_TOURS = [
        'dashboard',
        'assignments',
        'exams',
        'exam',
        'activities',
        'grades',
        'chats',
        'calendar',
        'appearance',
    ];

    public function store(Request $request, string $tour): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['done', 'skipped'])],
        ]);

        abort_unless(in_array($tour, self::ALLOWED_TOURS, true), 404);

        $request->user()->markOnboardingTour($tour, $validated['status']);

        return back();
    }

    /** Replay a tour ("Show me around again"). */
    public function destroy(Request $request, string $tour): RedirectResponse
    {
        abort_unless(in_array($tour, self::ALLOWED_TOURS, true), 404);

        $request->user()->resetOnboardingTour($tour);

        return back();
    }
}
