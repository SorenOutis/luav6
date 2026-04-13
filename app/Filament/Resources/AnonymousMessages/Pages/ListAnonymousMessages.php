<?php

namespace App\Filament\Resources\AnonymousMessages\Pages;

use App\Filament\Resources\AnonymousMessages\AnonymousMessageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAnonymousMessages extends ListRecords
{
    protected static string $resource = AnonymousMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
