<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Route;

/**
 * Builds cache-busting URLs for the dynamic favicon.
 *
 * The favicon route (/favicon.png) is stable, but browsers cache favicons very
 * aggressively and the controller response sets max-age=3600. Without a
 * changing query string, swapping the logo in the admin panel would leave
 * visitors looking at the old (often Laravel-default) favicon until the cache
 * expires — sometimes much longer, since browsers override cache headers for
 * favicons.
 *
 * Appending a short version derived from the stored logo path makes the URL
 * change the moment the admin uploads a new logo (Filament stores each upload
 * under a unique path), so the new icon is fetched immediately. When no logo is
 * set the version is constant and the controller transparently redirects to
 * the bundled static icons.
 */
final class FaviconUrl
{
    /**
     * Resolve the favicon URL, cache-busted against the current logo.
     *
     * @param  int|null  $size  Optional square pixel size (e.g. 180 for the
     *                          apple-touch-icon); the controller clamps it.
     */
    public static function url(?int $size = null): string
    {
        if (! Route::has('favicon')) {
            return '/favicon.ico';
        }

        $params = ['v' => self::version()];

        if ($size !== null) {
            $params['size'] = $size;
        }

        return route('favicon', $params);
    }

    /** Whether a school logo is currently configured. */
    public static function hasLogo(): bool
    {
        $path = Setting::get('school_logo_path');

        return is_string($path) && $path !== '';
    }

    /**
     * A short, stable version token for the current logo.
     *
     * Uses Setting::get() which is itself cached, so this adds no extra I/O on
     * cloud (R2/S3) public disks — important, since the layout resolves this on
     * every page render.
     */
    public static function version(): string
    {
        $path = Setting::get('school_logo_path');

        if (! is_string($path) || $path === '') {
            return '0';
        }

        return substr(md5($path), 0, 8);
    }
}
