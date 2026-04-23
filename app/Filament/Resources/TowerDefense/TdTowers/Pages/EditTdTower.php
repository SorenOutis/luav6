<?php

namespace App\Filament\Resources\TowerDefense\TdTowers\Pages;

use App\Filament\Resources\TowerDefense\TdTowers\TdTowerResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTdTower extends EditRecord
{
    protected static string $resource = TdTowerResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
