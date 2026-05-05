<?php

namespace App\Filament\Resources\LearningMap\MapWorlds\Pages;

use App\Filament\Resources\LearningMap\MapWorlds\MapWorldResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMapWorld extends EditRecord
{
    protected static string $resource = MapWorldResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
