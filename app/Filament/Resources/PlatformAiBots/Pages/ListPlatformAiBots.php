<?php

namespace App\Filament\Resources\PlatformAiBots\Pages;

use App\Filament\Resources\PlatformAiBots\PlatformAiBotResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlatformAiBots extends ListRecords
{
    protected static string $resource = PlatformAiBotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
