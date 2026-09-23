<?php

namespace App\Filament\Resources\ActivityTasks\Pages;

use App\Filament\Resources\ActivityTasks\ActivityTaskResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityTask extends EditRecord
{
    protected static string $resource = ActivityTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
