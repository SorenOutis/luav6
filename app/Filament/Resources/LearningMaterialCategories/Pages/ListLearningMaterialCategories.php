<?php

namespace App\Filament\Resources\LearningMaterialCategories\Pages;

use App\Filament\Resources\LearningMaterialCategories\LearningMaterialCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListLearningMaterialCategories extends ListRecords
{
    protected static string $resource = LearningMaterialCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
