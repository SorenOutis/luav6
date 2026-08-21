<?php

namespace App\Http\Controllers;

use App\Services\CalendarEventService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CalendarController extends Controller
{
    /**
     * How many months the calendar payload reaches back / ahead from today.
     *
     * The month grid navigates client-side over one payload, so the window
     * needs to cover a full school year: two months of history for late
     * look-backs, twelve forward for deadlines scheduled far out.
     */
    public const MONTHS_BACK = 2;

    public const MONTHS_FORWARD = 12;

    /**
     * Show the student calendar page (Inertia SSR).
     */
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $from = now()->startOfMonth()->subMonths(self::MONTHS_BACK);
        $to = now()->endOfMonth()->addMonths(self::MONTHS_FORWARD);

        return Inertia::render('Calendar', array_merge(
            ['todayKey' => now()->toDateString()],
            app(CalendarEventService::class)->forUser($user, $from, $to),
        ));
    }
}
