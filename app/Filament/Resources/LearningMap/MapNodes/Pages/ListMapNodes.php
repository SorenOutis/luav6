<?php

namespace App\Filament\Resources\LearningMap\MapNodes\Pages;

use App\Filament\Resources\LearningMap\MapNodes\MapNodeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMapNodes extends ListRecords
{
    protected static string $resource = MapNodeResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
