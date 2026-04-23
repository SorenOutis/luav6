<?php

namespace App\Filament\Resources\TowerDefense\TdDifficulties\Pages;

use App\Filament\Resources\TowerDefense\TdDifficulties\TdDifficultyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTdDifficulty extends EditRecord
{
    protected static string $resource = TdDifficultyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
