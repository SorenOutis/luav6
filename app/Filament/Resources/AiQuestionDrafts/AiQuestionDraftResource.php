<?php

namespace App\Filament\Resources\AiQuestionDrafts;

use App\Filament\Resources\AiQuestionDrafts\Pages\EditAiQuestionDraft;
use App\Filament\Resources\AiQuestionDrafts\Pages\ListAiQuestionDrafts;
use App\Filament\Resources\AiQuestionDrafts\Schemas\AiQuestionDraftForm;
use App\Filament\Resources\AiQuestionDrafts\Tables\AiQuestionDraftsTable;
use App\Models\AiQuestionDraft;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AiQuestionDraftResource extends Resource
{
    protected static ?string $model = AiQuestionDraft::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'AI Question Generator';

    protected static ?string $modelLabel = 'AI Question Draft';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return AiQuestionDraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiQuestionDraftsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiQuestionDrafts::route('/'),
            'edit' => EditAiQuestionDraft::route('/{record}/edit'),
        ];
    }
}
