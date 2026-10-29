<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\FaviconUrl;
use Illuminate\Http\JsonResponse;

class CommunityAppManifestController extends Controller
{
    /** Warm cream splash background, matching the light app shell in app.blade.php. */
    private const BackgroundColor = '#f5f0e8';

    /** Theme color, matching the <meta name="theme-color"> in app.blade.php. */
    private const ThemeColor = '#f5f0e8';

    /**
     * Serve the Community Lounge PWA manifest.
     *
     * The manifest scope is the whole origin ("/") so the student login,
     * registration, 2FA and social sign-in screens stay inside the installed
     * app window instead of dropping into an out-of-scope browser toolbar.
     * The app identity ("id") and launch URL stay on /community, which keeps
     * it distinct from the Echo AI admin app (scoped to /admin).
     *
     * Public on purpose: browsers fetch manifests without credentials.
     */
    public function __invoke(): JsonResponse
    {
        $schoolName = Setting::get('school_name', config('app.name'));
        $schoolName = is_string($schoolName) && $schoolName !== '' ? $schoolName : 'Community';

        $manifest = [
            'id' => '/community',
            'name' => "{$schoolName} Community Lounge",
            'short_name' => 'Lounge',
            'description' => 'Your section\'s Community Lounge — posts, stories and classmates. Requires sign-in and an internet connection.',
            'start_url' => '/community',
            'scope' => '/',
            'display' => 'standalone',
            'orientation' => 'portrait',
            'background_color' => self::BackgroundColor,
            'theme_color' => self::ThemeColor,
            'categories' => ['education', 'social'],
            'icons' => [
                ['src' => FaviconUrl::url(192, false), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => FaviconUrl::url(512, false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => FaviconUrl::url(512, false), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
            'shortcuts' => [
                ['name' => 'Community Feed', 'short_name' => 'Feed', 'url' => '/community'],
                ['name' => 'My Section', 'short_name' => 'Section', 'url' => '/community?tab=section'],
                ['name' => 'Saved Posts', 'short_name' => 'Saved', 'url' => '/community?tab=saved'],
            ],
            'related_applications' => [
                [
                    'platform' => 'webapp',
                    'url' => '/community.webmanifest',
                    'id' => '/community',
                ],
            ],
        ];

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=300',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}
