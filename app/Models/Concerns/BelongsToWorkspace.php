<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Scope a model to the current admin's workspace.
 *
 * - Non-super-admin users: All queries are scoped to `admin_id = auth()->id()`
 * - Super admins: No scope — they see everything
 * - Unauthenticated / students: No scope
 * - On create: `admin_id` is auto-set to the current admin's ID
 */
trait BelongsToWorkspace
{
    /**
     * Boot the trait.
     */
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder) {
            $user = auth()->user();

            // Only scope for regular admins (not super admins, not students)
            if (! $user || ! $user->is_admin || $user->is_super_admin) {
                return;
            }

            $builder->where($builder->qualifyColumn('admin_id'), $user->id);
        });

        static::creating(function (Model $model) {
            $user = auth()->user();

            // Auto-set admin_id for regular admins creating records
            if ($user && $user->is_admin && ! $user->is_super_admin && is_null($model->admin_id)) {
                $model->admin_id = $user->id;
            }
        });
    }

    /**
     * The admin who owns this workspace record.
     */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
