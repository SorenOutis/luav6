<?php

namespace App\Filament\Resources\Assignments\Pages;

use App\Filament\Resources\Assignments\AssignmentResource;
use App\Models\Assignment;
use App\Services\AssignmentRosterService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAssignment extends EditRecord
{
    protected static string $resource = AssignmentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Re-sync the roster after section targeting changes. Students who are
     * dropped only lose their row if they had not submitted anything.
     */
    protected function afterSave(): void
    {
        /** @var Assignment $record */
        $record = $this->getRecord();

        app(AssignmentRosterService::class)->syncAssignment($record);
    }
}
