<?php

namespace App\Filament\Resources\TowerDefense\TdDifficulties\Pages;

use App\Filament\Resources\TowerDefense\TdDifficulties\TdDifficultyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTdDifficulties extends ListRecords
{
    protected static string $resource = TdDifficultyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
