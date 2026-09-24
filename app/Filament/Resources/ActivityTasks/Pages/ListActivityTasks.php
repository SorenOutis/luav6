<?php

namespace App\Filament\Resources\ActivityTasks\Pages;

use App\Filament\Resources\ActivityTasks\ActivityTaskResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivityTasks extends ListRecords
{
    protected static string $resource = ActivityTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('New Task / Activity'),
        ];
    }
}
