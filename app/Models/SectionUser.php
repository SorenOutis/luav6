<?php

namespace App\Models;

use App\Services\AssignmentRosterService;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

class SectionUser extends Pivot
{
    protected $table = 'section_user';

    public $incrementing = true;

    protected static function booted(): void
    {
        static::created(function (SectionUser $membership): void {
            $workspaceId = Section::withoutGlobalScope('workspace')
                ->whereKey($membership->section_id)
                ->value('workspace_id');

            if (! $workspaceId) {
                return;
            }

            DB::table('workspace_user')->insertOrIgnore([
                'workspace_id' => $workspaceId,
                'user_id' => $membership->user_id,
                'role' => Workspace::ROLE_STUDENT,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('users')
                ->where('id', $membership->user_id)
                ->whereNull('current_workspace_id')
                ->update(['current_workspace_id' => $workspaceId]);
        });

        // A student joining a section inherits the assignments already
        // targeted at it; leaving drops the ones they never worked on.
        static::created(function (SectionUser $membership): void {
            app(AssignmentRosterService::class)
                ->syncNewMembership((int) $membership->user_id, (int) $membership->section_id);
        });

        static::deleted(function (SectionUser $membership): void {
            app(AssignmentRosterService::class)
                ->syncRemovedMembership((int) $membership->user_id, (int) $membership->section_id);
        });
    }
}
