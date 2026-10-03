<?php

namespace App\Filament\Resources\Merches\Pages;

use App\Filament\Resources\Merches\MerchResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMerch extends EditRecord
{
    protected static string $resource = MerchResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
