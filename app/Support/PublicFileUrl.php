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
 *
 * storedPath() is the inverse, for the places that need the disk path back —
 * Filament's file upload fields check values against the disk and dehydrate
 * whatever is left, so an accessor's output has to be reduced to the path that
 * is actually stored before it reaches them.
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

    /**
     * Reduce a persisted value back to the path it is stored as on the "public"
     * disk — the inverse of resolve().
     *
     * A column can legitimately hold either form: resolve() passes absolute
     * URLs through unchanged (see the note above), so clients receive a URL
     * while the database usually keeps the relative path. Filament's file
     * upload fields are the opposite — they expect the disk path, check it
     * against the disk and dehydrate what is left, so handing them an accessor's
     * output silently drops the file (and clears the column on save). Strip the
     * origin — plus the "/storage" prefix the local disk serves files from — to
     * get the path back. Values that cannot be recognised are returned
     * unchanged: this helper must never destroy data it does not understand.
     */
    public static function storedPath(?string $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        if (! Str::startsWith($value, ['http://', 'https://', '//'])) {
            return self::stripDiskPrefix($value);
        }

        // Avatars can also be absolute URLs pointing somewhere else entirely
        // (a social provider, a bucket from another environment). Only URLs
        // served by this application or its public disk carry a disk path.
        if (! self::isOwnUrl($value)) {
            return $value;
        }

        $path = (string) parse_url($value, PHP_URL_PATH);

        return self::stripDiskPrefix($path);
    }

    /**
     * Whether an absolute URL is served by this application or its public disk.
     */
    private static function isOwnUrl(string $url): bool
    {
        $host = parse_url($url, PHP_URL_HOST);

        if (blank($host)) {
            return false;
        }

        $ownHosts = array_filter([
            parse_url((string) config('app.url'), PHP_URL_HOST),
            parse_url((string) config('filesystems.disks.public.url'), PHP_URL_HOST),
        ]);

        return in_array($host, $ownHosts, strict: true);
    }

    private static function stripDiskPrefix(string $path): ?string
    {
        // The local disk serves "<APP_URL>/storage/<path>", bundled assets are
        // served straight from the web root, and the database usually holds the
        // bare path already.
        $path = (string) preg_replace('#^/?storage/#i', '', ltrim($path, '/'));

        return $path === '' ? null : $path;
    }
}
