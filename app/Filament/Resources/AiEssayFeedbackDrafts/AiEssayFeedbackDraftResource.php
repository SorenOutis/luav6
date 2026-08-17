<?php

namespace App\Filament\Resources\AiEssayFeedbackDrafts;

use App\Filament\Resources\AiEssayFeedbackDrafts\Pages\EditAiEssayFeedbackDraft;
use App\Filament\Resources\AiEssayFeedbackDrafts\Pages\ListAiEssayFeedbackDrafts;
use App\Filament\Resources\AiEssayFeedbackDrafts\Schemas\AiEssayFeedbackDraftForm;
use App\Filament\Resources\AiEssayFeedbackDrafts\Tables\AiEssayFeedbackDraftsTable;
use App\Models\AiEssayFeedbackDraft;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class AiEssayFeedbackDraftResource extends Resource
{
    protected static ?string $model = AiEssayFeedbackDraft::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?int $navigationSort = 5;

    protected static ?string $navigationLabel = 'AI Feedback Review';

    protected static ?string $modelLabel = 'AI Essay Feedback Draft';

    public static function getNavigationBadge(): ?string
    {
        $count = static::getModel()::query()
            ->where('review_status', AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW)
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function form(Schema $schema): Schema
    {
        return AiEssayFeedbackDraftForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AiEssayFeedbackDraftsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAiEssayFeedbackDrafts::route('/'),
            'edit' => EditAiEssayFeedbackDraft::route('/{record}/edit'),
        ];
    }
}
