<?php

namespace App\Filament\Resources\Merches\Pages;

use App\Filament\Resources\Merches\MerchResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMerches extends ListRecords
{
    protected static string $resource = MerchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
