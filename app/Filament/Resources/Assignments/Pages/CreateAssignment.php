<?php

namespace App\Filament\Resources\Assignments\Pages;

use App\Filament\Resources\Assignments\AssignmentResource;
use App\Models\Assignment;
use App\Services\AssignmentRosterService;
use Filament\Resources\Pages\CreateRecord;

class CreateAssignment extends CreateRecord
{
    protected static string $resource = AssignmentResource::class;

    /**
     * The form exposes a single "Group size" value (1–4 quick pick, or a
     * custom number up to 20); store it as both the advisory minimum and the
     * enforced maximum so groups are capped at the chosen size.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
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
     * Give the assignment to every student in the targeted sections so the
     * roster (and every "assigned vs submitted" stat) is accurate right away.
     */
    protected function afterCreate(): void
    {
        /** @var Assignment $record */
        $record = $this->getRecord();

        app(AssignmentRosterService::class)->syncAssignment($record);
    }
}
