<?php

namespace App\Filament\Resources\Users\Concerns;

use App\Services\AdminUserGamificationService;

trait SyncsSectionProgress
{
    /** @var list<array<string, mixed>> */
    protected array $sectionProgressRows = [];

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function extractSectionProgressRows(array $data): array
    {
        $this->sectionProgressRows = array_values($data['section_progress_rows'] ?? []);
        unset($data['section_progress_rows']);

        // Global totals are derived from section_progress observers.
        // Writing them here double-counts XP/points on save.
        unset($data['level'], $data['exp'], $data['points']);

        return $data;
    }

    protected function persistSectionProgress(): void
    {
        $user = $this->record?->fresh(['sections', 'sectionProgress']);

        if (! $user) {
            return;
        }

        app(AdminUserGamificationService::class)->apply($user, $this->sectionProgressRows);

        $this->record = $user->fresh(['sections', 'sectionProgress', 'currentSeasonProgress']);
    }
}
