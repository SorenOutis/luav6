<?php

namespace App\Filament\Resources\AnonymousMessages\Pages;

use App\Filament\Resources\AnonymousMessages\AnonymousMessageResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAnonymousMessage extends EditRecord
{
    protected static string $resource = AnonymousMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
