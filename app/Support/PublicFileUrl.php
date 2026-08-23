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

        // The curated avatar gallery is shipped with the application rather
        // than uploaded to the configured public disk. Keep these assets
        // available when production uses S3/R2 for user uploads.
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

        // Curated avatars are shipped in storage/app/public and therefore are
        // not present in an external bucket. Keep them on the local public
        // link even when uploaded avatars use S3/R2.
        if (
            preg_match('#^avatars/avatar-\d+\.svg$#i', $path) === 1
            && is_file(public_path($path))
        ) {
            return url('/'.ltrim($path, '/'));
        }

        return $disk->url($path);
    }
}
