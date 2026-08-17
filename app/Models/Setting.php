<?php

namespace App\Models;

use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'admin_id', 'workspace_id'];

    private const GLOBAL_CACHE_KEY = 'settings:global';

    protected static function booted(): void
    {
        static::saved(fn (Setting $setting) => static::flushCache($setting));
        static::deleted(fn (Setting $setting) => static::flushCache($setting));
    }

    /** Workspace value first, then platform-global fallback. */
    public static function get(string $key, $default = null): mixed
    {
        $workspaceId = app(WorkspaceContext::class)->id();

        if ($workspaceId) {
            $workspace = static::workspaceMap($workspaceId);
            if (array_key_exists($key, $workspace)) {
                return $workspace[$key];
            }
        }

        $global = static::globalMap();

        return array_key_exists($key, $global) ? $global[$key] : $default;
    }

    public static function set(string $key, $value): self
    {
        $workspaceId = app(WorkspaceContext::class)->id();
        $setting = static::query()->firstOrNew([
            'key' => $key,
            'workspace_id' => $workspaceId,
        ]);

        if (! $setting->exists) {
            $user = auth()->user();
            $setting->admin_id = $user && $user->is_admin ? $user->id : null;
        }

        $setting->value = $value;
        $setting->save();

        return $setting;
    }

    /**
     * Persist a platform-wide setting (workspace_id = null).
     *
     * Platform Settings toggles (registration/login, daily & bonus XP claim,
     * AI chat, provider credentials, school branding, student page controls)
     * are consumed from contexts that never see an admin's workspace scope:
     *
     *  - Public registration/login pages run unauthenticated, so
     *    WorkspaceContext::id() is null and only the global map is read.
     *  - Students read settings inside their own workspace scope, then fall
     *    back to the global map. The tenant migration gave every admin their
     *    own workspace, so values saved with Setting::set() from Platform
     *    Settings landed in the admin's workspace and silently did nothing
     *    for every other consumer.
     *
     * Only genuinely tenant-scoped keys (the Workspace AI Budget section)
     * should go through Setting::set(); everything else on the Platform
     * Settings page must be written globally with this method.
     */
    public static function setGlobal(string $key, $value): self
    {
        $setting = static::query()->firstOrNew([
            'key' => $key,
            'workspace_id' => null,
        ]);

        if (! $setting->exists) {
            $user = auth()->user();
            $setting->admin_id = $user && $user->is_admin ? $user->id : null;
        }

        $setting->value = $value;
        $setting->save();

        return $setting;
    }

    /** @return array<string, mixed> */
    private static function globalMap(): array
    {
        return Cache::rememberForever(
            self::GLOBAL_CACHE_KEY,
            fn () => static::query()->whereNull('workspace_id')->pluck('value', 'key')->all(),
        );
    }

    /** @return array<string, mixed> */
    private static function workspaceMap(int $workspaceId): array
    {
        return Cache::rememberForever(
            self::workspaceCacheKey($workspaceId),
            fn () => static::query()->where('workspace_id', $workspaceId)->pluck('value', 'key')->all(),
        );
    }

    private static function workspaceCacheKey(int $workspaceId): string
    {
        return "settings:workspace:{$workspaceId}";
    }

    private static function flushCache(Setting $setting): void
    {
        $workspaceIds = array_unique(array_filter([
            $setting->workspace_id,
            $setting->getOriginal('workspace_id'),
        ]));

        foreach ($workspaceIds as $workspaceId) {
            Cache::forget(self::workspaceCacheKey((int) $workspaceId));
        }

        if ($setting->workspace_id === null || $setting->getOriginal('workspace_id') === null) {
            Cache::forget(self::GLOBAL_CACHE_KEY);
        }
    }

    public static function flushAllCaches(): void
    {
        Cache::forget(self::GLOBAL_CACHE_KEY);

        static::query()
            ->whereNotNull('workspace_id')
            ->distinct()
            ->pluck('workspace_id')
            ->each(fn ($workspaceId) => Cache::forget(self::workspaceCacheKey((int) $workspaceId)));
    }

    public function workspace()
    {
        return $this->belongsTo(Workspace::class);
    }
}
