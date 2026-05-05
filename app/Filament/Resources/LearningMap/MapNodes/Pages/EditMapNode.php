<?php

namespace App\Filament\Resources\LearningMap\MapNodes\Pages;

use App\Filament\Resources\LearningMap\MapNodes\MapNodeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditMapNode extends EditRecord
{
    protected static string $resource = MapNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
