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
