<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class RobotsController extends Controller
{
    public function __invoke(Request $request)
    {
        $appUrl = rtrim((string) config('app.url'), '/');

        if (app()->environment(['local', 'testing'])) {
            $lines = ['User-agent: *', 'Disallow:'];
        } else {
            $disallows = [
                '/dashboard',
                '/login',
                '/register',
                '/forgot-password',
                '/two-factor-challenge',
                '/settings',
                '/admin',
                '/ngl',
                '/courses',
                '/exams',
                '/grades',
                '/leaderboard',
                '/games',
                '/assignments',
                '/library',
                '/library/',
                '/chats',
                '/activities',
                '/calendar',
                '/notifications',
                '/email/verify',
                '/api',
            ];

            $lines = ['User-agent: *'];
            foreach ($disallows as $path) {
                $lines[] = "Disallow: $path";
            }
        }

        $lines[] = '';
        $lines[] = "Sitemap: $appUrl/sitemap.xml";

        return response(implode("\n", $lines)."\n", 200)
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }
}
