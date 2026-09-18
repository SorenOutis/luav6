<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Facades\DB;

class CourseUser extends Pivot
{
    protected $table = 'course_user';

    public $incrementing = true;

    protected static function booted(): void
    {
        static::created(function (CourseUser $membership): void {
            $workspaceId = Course::withoutGlobalScope('workspace')
                ->whereKey($membership->course_id)
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
    }
}
