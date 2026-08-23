<?php

namespace App\Support;

/**
 * Provides the bundled avatars that students are allowed to select.
 *
 * The paths returned here are deliberately constrained to the curated files
 * shipped with the application. They are used both to build the picker and to
 * validate the value submitted by a profile owner.
 */
final class AvatarGallery
{
    /**
     * @return list<string>
     */
    public static function paths(): array
    {
        return collect(glob(storage_path('app/public/avatars/avatar-*.svg')) ?: [])
            ->filter(fn (string $file): bool => preg_match('/avatar-\d+\.svg$/i', basename($file)) === 1)
            ->sortBy(fn (string $file): int => (int) preg_replace('/\D+/', '', basename($file)))
            ->map(fn (string $file): string => 'avatars/'.basename($file))
            ->values()
            ->all();
    }

    /**
     * @return list<array{path: string, name: string, url: string}>
     */
    public static function items(): array
    {
        return array_map(
            fn (string $path): array => [
                'path' => $path,
                'name' => str_replace('-', ' ', ucfirst(pathinfo($path, PATHINFO_FILENAME))),
                'url' => (string) PublicFileUrl::resolve($path),
            ],
            self::paths(),
        );
    }

    public static function isCurated(?string $path): bool
    {
        return $path !== null && in_array($path, self::paths(), true);
    }
}
