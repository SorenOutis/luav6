<?php

namespace App\Models\Concerns;

use App\Models\User;
use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Scope tenant-owned records by workspace_id, not by the creating admin. */
trait BelongsToWorkspace
{
    protected static function bootBelongsToWorkspace(): void
    {
        static::addGlobalScope('workspace', function (Builder $builder): void {
            $model = $builder->getModel();
            $user = auth()->user();

            // Legacy game tables intentionally remain on their existing
            // admin_id ownership model until that feature is removed.
            if (str_starts_with($model->getTable(), 'td_')) {
                if ($user && $user->is_admin && ! $user->isSuperAdmin()) {
                    $builder->where($builder->qualifyColumn('admin_id'), $user->id);
                }

                return;
            }

            $context = app(WorkspaceContext::class);
            $workspaceId = $context->id();

            if ($user?->isSuperAdmin() && ! $context->isInspecting()) {
                return;
            }
            if ($workspaceId) {
                $builder->where($builder->qualifyColumn('workspace_id'), $workspaceId);
            } else {
                // Guests and authenticated users without a tenant may only see
                // explicitly global records, never an arbitrary tenant's rows.
                $builder->whereNull($builder->qualifyColumn('workspace_id'));
            }
        });

        static::creating(function (Model $model): void {
            $user = auth()->user();

            if (str_starts_with($model->getTable(), 'td_')) {
                if ($user && $user->is_admin && ! $user->isSuperAdmin() && is_null($model->admin_id)) {
                    $model->admin_id = $user->id;
                }

                return;
            }

            $workspaceId = app(WorkspaceContext::class)->id();
            $hasLegacyAdmin = ! in_array($model->getTable(), [
                'grades',
                'ai_usage_logs',
                'ai_budget_periods',
                'ai_budget_reservations',
                'ai_budget_events',
                'ai_essay_feedback_drafts',
                'ai_review_events',
            ], true);

            // Importers and tests may provide only the legacy creator. Resolve
            // that creator's tenant so old write paths stay safe during rollout.
            if (! $workspaceId && $hasLegacyAdmin && $model->admin_id) {
                $admin = User::query()->find($model->admin_id);
                $workspaceId = $admin?->current_workspace_id
                    ?: $admin?->workspaces()->value('workspaces.id');
            }

            if ($workspaceId && is_null($model->workspace_id)) {
                $model->workspace_id = $workspaceId;
            }

            // admin_id remains creator/audit metadata, not the tenant boundary.
            if (
                $hasLegacyAdmin
                && $user
                && $user->is_admin
                && is_null($model->admin_id)
            ) {
                $model->admin_id = $user->id;
            }
        });
    }

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }

    /** The administrator who originally created the record. */
    public function admin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'admin_id');
    }
}
