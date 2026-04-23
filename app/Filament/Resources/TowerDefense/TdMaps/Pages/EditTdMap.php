<?php

namespace App\Filament\Resources\TowerDefense\TdMaps\Pages;

use App\Filament\Resources\TowerDefense\TdMaps\TdMapResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTdMap extends EditRecord
{
    protected static string $resource = TdMapResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
