<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\SyncsSectionProgress;
use App\Filament\Resources\Users\UserResource;
use App\Services\AdminUserGamificationService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use SyncsSectionProgress;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load(['sections', 'sectionProgress', 'currentSeasonProgress']);
        $data['section_progress_rows'] = app(AdminUserGamificationService::class)->rowsFor($this->record);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractSectionProgressRows($data);
    }

    protected function afterSave(): void
    {
        $this->persistSectionProgress();
    }
}
