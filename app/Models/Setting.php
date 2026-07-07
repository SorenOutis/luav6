<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $fillable = ['key', 'value', 'admin_id'];

    /**
     * Get a setting value.
     *
     * Resolution order:
     * 1. Per-workspace setting for the current admin (if an admin is logged in)
     * 2. Global setting (admin_id = null)
     * 3. Default value
     */
    public static function get(string $key, $default = null): mixed
    {
        $user = auth()->user();

        // If an admin (non-super) is logged in, check their workspace setting first
        if ($user && $user->is_admin && ! $user->is_super_admin) {
            $workspaceSetting = static::where('key', $key)
                ->where('admin_id', $user->id)
                ->first();

            if ($workspaceSetting) {
                return $workspaceSetting->value;
            }
        }

        // Fall back to global setting
        $globalSetting = static::where('key', $key)
            ->whereNull('admin_id')
            ->first();

        return $globalSetting?->value ?? $default;
    }

    /**
     * Set a setting value.
     *
     * - Admins (non-super) create/update per-workspace settings
     * - Super admins and unauthenticated create/update global settings
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
}
