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
     * The form exposes a single "Group size" value (1–4 quick pick, or a
     * custom number up to 20); store it as both the advisory minimum and the
     * enforced maximum so groups are capped at the chosen size.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $size = isset($data['group_size']) && is_numeric($data['group_size'])
            ? (int) $data['group_size']
            : null;

        $size = $size !== null ? max(1, min(20, $size)) : null;

        $data['min_group_size'] = $size;
        $data['max_group_size'] = $size;
        unset($data['group_size']);

        return $data;
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
