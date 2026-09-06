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

        $path = ltrim($path, '/');

        if ($path === '' || str_starts_with($path, '..')) {
            return null;
        }

        // Bundled assets ship with the app under public/ (avatar gallery and
        // level badge gallery). Keep them available even when production
        // configures S3/R2 for user uploads, because they are never copied
        // into an external bucket.
        if (is_file(public_path($path))) {
            return url('/'.ltrim($path, '/'));
        }

        // Local storage is common on Dokploy when PUBLIC_DISK is omitted. Do
        // not use the disk's configured APP_URL here: a stale/placeholder
        // APP_URL makes the browser request localhost (or the old domain),
        // even though the file exists and public/storage is linked. `url()`
        // uses the current request host, which is the host the user actually
        // loaded the app from.
        $disk = Storage::disk('public');

        if (config('filesystems.disks.public.driver') === 'local') {
            return url('/storage/'.ltrim($path, '/'));
        }

        return $disk->url($path);
    }
}
