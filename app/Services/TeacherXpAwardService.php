<?php

namespace App\Services;

use App\Models\SectionProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TeacherXpAwardService
{
    public function award(User $student, int $sectionId, int $amount, string $reason): void
    {
        if ($amount < 1 || $amount > 100) {
            throw ValidationException::withMessages(['amount' => 'Award between 1 and 100 XP.']);
        }

        $sectionQuery = $student->sections()->where('sections.id', $sectionId);
        $teacher = auth()->user();

        if ($teacher?->is_admin && ! $teacher->isSuperAdmin()) {
            $sectionQuery->where('sections.admin_id', $teacher->id);
        }

        if (! $sectionQuery->exists()) {
            throw ValidationException::withMessages(['section_id' => 'The student is not enrolled in one of your sections.']);
        }

        DB::transaction(function () use ($student, $sectionId, $amount, $reason, $teacher): void {
            $progress = $student->activeSectionProgress($sectionId);
            $wasSyncing = SectionProgress::$isSyncing;
            SectionProgress::$isSyncing = true;
            $progress->increment('exp', $amount);
            $progress->save();
            SectionProgress::$isSyncing = $wasSyncing;

            $student->recordGamificationHistory(
                $amount,
                0,
                'Teacher Award',
                trim($reason),
                $sectionId,
                null,
                $teacher?->id,
            );
        });
    }
}
