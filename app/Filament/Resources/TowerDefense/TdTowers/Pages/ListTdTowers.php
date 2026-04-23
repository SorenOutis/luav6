<?php

namespace App\Filament\Resources\TowerDefense\TdTowers\Pages;

use App\Filament\Resources\TowerDefense\TdTowers\TdTowerResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTdTowers extends ListRecords
{
    protected static string $resource = TdTowerResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
