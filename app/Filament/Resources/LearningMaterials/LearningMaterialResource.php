<?php

namespace App\Filament\Resources\LearningMaterials;

use App\Filament\Resources\LearningMaterials\Pages\CreateLearningMaterial;
use App\Filament\Resources\LearningMaterials\Pages\EditLearningMaterial;
use App\Filament\Resources\LearningMaterials\Pages\ListLearningMaterials;
use App\Filament\Resources\LearningMaterials\Schemas\LearningMaterialForm;
use App\Filament\Resources\LearningMaterials\Tables\LearningMaterialsTable;
use App\Models\LearningMaterial;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class LearningMaterialResource extends Resource
{
    protected static ?string $model = LearningMaterial::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-book-open';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Library Hub';

    public static function form(Schema $schema): Schema
    {
        return LearningMaterialForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LearningMaterialsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLearningMaterials::route('/'),
            'create' => CreateLearningMaterial::route('/create'),
            'edit' => EditLearningMaterial::route('/{record}/edit'),
        ];
    }
}
