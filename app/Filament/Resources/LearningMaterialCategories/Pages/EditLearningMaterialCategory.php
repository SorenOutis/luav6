<?php

namespace App\Filament\Resources\LearningMaterialCategories\Pages;

use App\Filament\Resources\LearningMaterialCategories\LearningMaterialCategoryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditLearningMaterialCategory extends EditRecord
{
    protected static string $resource = LearningMaterialCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
