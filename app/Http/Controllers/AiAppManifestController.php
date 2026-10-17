<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;

class AiAppManifestController extends Controller
{
    /**
     * Serve the Echo AI PWA manifest with the school accent color applied.
     *
     * The static public/ai-assistant.webmanifest stays the single source of
     * truth for name/scope/icons; this endpoint only overrides the theme and
     * splash colors so the installed app matches the school branding.
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

        $accent = (string) Setting::get('school_accent_color', '#09090b');

        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $accent) !== 1) {
            $accent = '#09090b';
        }

        $manifest['theme_color'] = $accent;
        $manifest['background_color'] = $accent;

        return response()->json($manifest, 200, [
            'Content-Type' => 'application/manifest+json',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
