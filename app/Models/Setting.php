<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'admin_id'];

    /** Cache key for the global (admin_id = null) settings map. */
    private const GLOBAL_CACHE_KEY = 'settings:global';

    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => static::flushCache($setting));
        static::deleted(fn (Setting $setting) => static::flushCache($setting));
    }

    /**
     * Get a setting value.
     *
     * Resolution order:
     * 1. Per-workspace setting for the current admin (if an admin is logged in)
     * 2. Global setting (admin_id = null)
     * 3. Default value
     *
     * ⚠️ Values are returned exactly as stored (strings). Callers rely on this —
     * e.g. ChatController compares `Setting::get('ollama_enabled') === '1'` — so
     * the cache must never coerce types.
     */
    public static function get(string $key, $default = null): mixed
    {
        $user = auth()->user();

        // If an admin (non-super) is logged in, check their workspace setting first
        if ($user && $user->is_admin && ! $user->is_super_admin) {
            $workspace = static::workspaceMap((int) $user->id);

            if (array_key_exists($key, $workspace)) {
                return $workspace[$key];
            }
        }

        $global = static::globalMap();

        return array_key_exists($key, $global) ? $global[$key] : $default;
    }

    /**
     * Set a setting value.
     *
     * - Admins (non-super) create/update per-workspace settings
     * - Super admins and unauthenticated create/update global settings
     *
     * The saved() hook busts the relevant cache entry.
     */
    public static function set(string $key, $value): self
    {
        $user = auth()->user();
        $adminId = ($user && $user->is_admin && ! $user->is_super_admin) ? $user->id : null;

        return static::updateOrCreate(
            ['key' => $key, 'admin_id' => $adminId],
            ['value' => $value]
        );
    }

    /**
     * All global settings, keyed by name.
     *
     * One query instead of one per lookup. HandleInertiaRequests alone read 6
     * settings per request and AIService another 9 in its constructor, so a
     * single page load could issue 15–30 uncached queries — against SQLite,
     * which serializes writers.
     *
     * @return array<string, mixed>
     */
    private static function globalMap(): array
    {
        return Cache::rememberForever(
            self::GLOBAL_CACHE_KEY,
            fn () => static::query()->whereNull('admin_id')->pluck('value', 'key')->all()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private static function workspaceMap(int $adminId): array
    {
        return Cache::rememberForever(
            self::workspaceCacheKey($adminId),
            fn () => static::query()->where('admin_id', $adminId)->pluck('value', 'key')->all()
        );
    }

    private static function workspaceCacheKey(int $adminId): string
    {
        return "settings:admin:{$adminId}";
    }

    /**
     * Invalidate the map this row belongs to.
     *
     * ⚠️ No static memoization anywhere in this class: under Octane a static
     * property survives between requests, so one admin's workspace values would
     * leak to the next user served by the same worker. The Cache facade is
     * request-safe.
     */
    private static function flushCache(Setting $setting): void
    {
        // On update the row may have moved between scopes; clear both the old
        // and new owner to avoid a stale entry.
        $ids = array_unique(array_filter([
            $setting->admin_id,
            $setting->getOriginal('admin_id'),
        ]));

        foreach ($ids as $adminId) {
            Cache::forget(self::workspaceCacheKey((int) $adminId));
        }

        if ($setting->admin_id === null || $setting->getOriginal('admin_id') === null) {
            Cache::forget(self::GLOBAL_CACHE_KEY);
        }
    }

    /**
     * Clear every cached settings map. Intended for tests and maintenance.
     */
    public static function flushAllCaches(): void
    {
        Cache::forget(self::GLOBAL_CACHE_KEY);

        static::query()
            ->whereNotNull('admin_id')
            ->distinct()
            ->pluck('admin_id')
            ->each(fn ($adminId) => Cache::forget(self::workspaceCacheKey((int) $adminId)));
    }
}
