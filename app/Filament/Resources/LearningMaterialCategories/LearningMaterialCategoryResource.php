<?php

namespace App\Filament\Resources\LearningMaterialCategories;

use App\Filament\Resources\LearningMaterialCategories\Pages\CreateLearningMaterialCategory;
use App\Filament\Resources\LearningMaterialCategories\Pages\EditLearningMaterialCategory;
use App\Filament\Resources\LearningMaterialCategories\Pages\ListLearningMaterialCategories;
use App\Filament\Resources\LearningMaterialCategories\Schemas\LearningMaterialCategoryForm;
use App\Filament\Resources\LearningMaterialCategories\Tables\LearningMaterialCategoriesTable;
use App\Models\LearningMaterialCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class LearningMaterialCategoryResource extends Resource
{
    protected static ?string $model = LearningMaterialCategory::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-tag';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Material Categories';

    public static function form(Schema $schema): Schema
    {
        return LearningMaterialCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LearningMaterialCategoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLearningMaterialCategories::route('/'),
            'create' => CreateLearningMaterialCategory::route('/create'),
            'edit' => EditLearningMaterialCategory::route('/{record}/edit'),
        ];
    }
}
