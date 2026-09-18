<?php

namespace App\Filament\Resources\TowerDefense\TdEnemies\Pages;

use App\Filament\Resources\TowerDefense\TdEnemies\TdEnemyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTdEnemies extends ListRecords
{
    protected static string $resource = TdEnemyResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
