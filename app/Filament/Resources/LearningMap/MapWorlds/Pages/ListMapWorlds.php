<?php

namespace App\Filament\Resources\LearningMap\MapWorlds\Pages;

use App\Filament\Resources\LearningMap\MapWorlds\MapWorldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMapWorlds extends ListRecords
{
    protected static string $resource = MapWorldResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
