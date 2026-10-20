<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class AiAppManifestController extends Controller
{
    /**
     * Echo AI logo palette, sampled from public/images/ai-app-*.png:
     * the pine-green "K" monogram on its cream field.
     */
    private const LogoBackground = '#f5e8d7';

    private const LogoForeground = '#3c4d3e';

    /**
     * Serve the Echo AI PWA manifest with the logo palette applied.
     *
     * The static public/ai-assistant.webmanifest stays the single source of
     * truth for name/scope/icons; this endpoint only overrides the theme and
     * splash colors so the installed app opens matching the logo artwork.
     * Public on purpose: browsers fetch manifests without credentials.
     */
    public function __invoke(): JsonResponse
    {
        $manifest = json_decode(
            (string) file_get_contents(public_path('ai-assistant.webmanifest')),
            true,
        );

        if (! is_array($manifest)) {
            $manifest = [];
        }

        $manifest['theme_color'] = self::LogoForeground;
        $manifest['background_color'] = self::LogoBackground;

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
