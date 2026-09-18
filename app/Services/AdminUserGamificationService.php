<?php

namespace App\Services;

use App\Models\SectionProgress;
use App\Models\User;

class AdminUserGamificationService
{
    /**
     * Build one editable row per enrolled section for the admin user form.
     *
     * @return list<array{section_id: int, section_name: string, level: int, exp: float, points: float}>
     */
    public function rowsFor(User $user): array
    {
        $user->loadMissing(['sections', 'sectionProgress']);

        return $user->sections
            ->sortBy(fn ($section) => mb_strtolower((string) $section->name))
            ->values()
            ->map(function ($section) use ($user): array {
                $progress = $user->sectionProgress
                    ->first(fn (SectionProgress $row): bool => (int) $row->section_id === (int) $section->id);

                return [
                    'section_id' => (int) $section->id,
                    'section_name' => (string) $section->name,
                    'level' => (int) ($progress?->level ?? 1),
                    'exp' => (float) ($progress?->exp ?? 0),
                    'points' => (float) ($progress?->points ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Persist admin-edited per-section stats and create missing progress rows
     * for every enrolled section.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    public function apply(User $user, array $rows): void
    {
        $user->loadMissing(['sections', 'sectionProgress']);

        $enrolledIds = $user->sections
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($rows as $row) {
            $sectionId = (int) ($row['section_id'] ?? 0);

            if ($sectionId <= 0 || ! in_array($sectionId, $enrolledIds, true)) {
                continue;
            }

            $progress = $user->sectionProgress()->firstOrCreate(
                ['section_id' => $sectionId],
                ['exp' => 0, 'points' => 0, 'level' => 1],
            );

            $submittedExp = max(0, (float) ($row['exp'] ?? $progress->exp));
            $submittedPoints = max(0, (float) ($row['points'] ?? $progress->points));
            $submittedLevel = max(1, (int) ($row['level'] ?? $progress->level));

            $originalExp = (float) $progress->exp;
            $originalLevel = (int) $progress->level;

            $expChanged = abs($submittedExp - $originalExp) > 0.001;
            $levelChanged = $submittedLevel !== $originalLevel;

            // XP is the source of truth when both change (matches the model
            // hook). A level-only edit sets XP to that level's floor.
            if ($expChanged) {
                $exp = $submittedExp;
            } elseif ($levelChanged) {
                $exp = SectionProgress::expFloorForLevel($submittedLevel);
            } else {
                $exp = $originalExp;
            }

            if (
                abs((float) $progress->exp - $exp) < 0.001
                && abs((float) $progress->points - $submittedPoints) < 0.001
            ) {
                continue;
            }

            $progress->exp = $exp;
            $progress->points = $submittedPoints;
            $progress->save();
        }

        foreach ($enrolledIds as $sectionId) {
            $user->sectionProgress()->firstOrCreate(
                ['section_id' => $sectionId],
                ['exp' => 0, 'points' => 0, 'level' => 1],
            );
        }
    }
}
