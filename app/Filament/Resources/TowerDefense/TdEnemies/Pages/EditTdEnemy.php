<?php

namespace App\Filament\Resources\TowerDefense\TdEnemies\Pages;

use App\Filament\Resources\TowerDefense\TdEnemies\TdEnemyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTdEnemy extends EditRecord
{
    protected static string $resource = TdEnemyResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
