<?php

namespace App\Filament\Resources\TowerDefense\TdLevels\Pages;

use App\Filament\Resources\TowerDefense\TdLevels\TdLevelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTdLevels extends ListRecords
{
    protected static string $resource = TdLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
