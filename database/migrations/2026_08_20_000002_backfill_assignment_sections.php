<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Before this change every student in a workspace could see every
     * assignment. Now visibility is driven by `assignment_section`, and an
     * assignment with no sections is visible to nobody.
     *
     * To avoid silently hiding existing coursework, attach every existing
     * assignment to every section in its own workspace, then materialise the
     * per-student `assignment_user` rows so "assigned but not submitted" is
     * countable.
     */
    public function up(): void
    {
        $now = now();

        DB::table('assignments')->orderBy('id')->chunkById(200, function ($assignments) use ($now) {
            foreach ($assignments as $assignment) {
                $sectionIds = DB::table('sections')
                    ->when(
                        $assignment->workspace_id === null,
                        fn ($query) => $query->whereNull('workspace_id'),
                        fn ($query) => $query->where('workspace_id', $assignment->workspace_id),
                    )
                    ->pluck('id');

                if ($sectionIds->isEmpty()) {
                    continue;
                }

                DB::table('assignment_section')->insertOrIgnore(
                    $sectionIds->map(fn ($sectionId) => [
                        'assignment_id' => $assignment->id,
                        'section_id' => $sectionId,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ])->all()
                );

                $userIds = DB::table('section_user')
                    ->whereIn('section_id', $sectionIds)
                    ->distinct()
                    ->pluck('user_id');

                $existing = DB::table('assignment_user')
                    ->where('assignment_id', $assignment->id)
                    ->pluck('user_id')
                    ->all();

                $missing = $userIds->diff($existing);

                foreach ($missing->chunk(500) as $chunk) {
                    DB::table('assignment_user')->insertOrIgnore(
                        $chunk->map(fn ($userId) => [
                            'assignment_id' => $assignment->id,
                            'user_id' => $userId,
                            'submitted' => false,
                            'status' => 'Pending',
                            'created_at' => $now,
                            'updated_at' => $now,
                        ])->values()->all()
                    );
                }
            }
        });
    }

    public function down(): void
    {
        // Targeting rows are dropped with the table itself; the placeholder
        // `assignment_user` rows are intentionally left in place because we
        // cannot tell them apart from genuine submissions after the fact.
        DB::table('assignment_section')->truncate();
    }
};
