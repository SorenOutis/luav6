<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds public URLs for files stored on the "public" disk.
 *
 * The database is supposed to hold relative paths (e.g. "avatars/abc.png"),
 * but a full URL can end up stored there if some code path re-saves an
 * accessor's output. Calling Storage::disk('public')->url() on such a value
 * would double-prefix the host (https://pub-....r2.dev/https://pub-....r2.dev/...)
 * and produce a permanently broken URL. This helper returns already-absolute
 * values unchanged, so those rows self-heal instead of breaking.
 */
final class PublicFileUrl
{
    public static function resolve(?string $path): ?string
    {
        $path = trim((string) $path);

        if ($path === '') {
            return null;
        }

        if (Str::startsWith($path, ['http://', 'https://', '//'])) {
            return $path;
        }

        return Storage::disk('public')->url($path);
    }
}
