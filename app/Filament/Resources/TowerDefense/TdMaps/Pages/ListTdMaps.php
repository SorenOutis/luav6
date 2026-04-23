<?php

namespace App\Filament\Resources\TowerDefense\TdMaps\Pages;

use App\Filament\Resources\TowerDefense\TdMaps\TdMapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTdMaps extends ListRecords
{
    protected static string $resource = TdMapResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
