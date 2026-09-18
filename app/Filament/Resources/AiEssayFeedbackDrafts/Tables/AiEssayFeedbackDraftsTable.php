<?php

namespace App\Filament\Resources\AiEssayFeedbackDrafts\Tables;

use App\Filament\Support\WorkspaceTable;
use App\Models\AiEssayFeedbackDraft;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AiEssayFeedbackDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->columns([
                WorkspaceTable::column(),
                TextColumn::make('submission.user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('submission.exam.title')
                    ->label('Exam')
                    ->searchable()
                    ->limit(35),
                TextColumn::make('submission.examPart.title')
                    ->label('Part')
                    ->limit(25),
                TextColumn::make('question_number')
                    ->label('Question #')
                    ->sortable(),
                TextColumn::make('proposed_score')
                    ->label('AI proposal')
                    ->formatStateUsing(fn ($state, $record): string => number_format((float) $state, 2).' / '.number_format((float) $record->max_points, 2)),
                TextColumn::make('review_status')
                    ->label('Review status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => str($state)->replace('_', ' ')->title()->toString())
                    ->color(fn (string $state): string => match ($state) {
                        AiEssayFeedbackDraft::STATUS_APPROVED => 'success',
                        AiEssayFeedbackDraft::STATUS_REJECTED, AiEssayFeedbackDraft::STATUS_SUPERSEDED => 'danger',
                        AiEssayFeedbackDraft::STATUS_GENERATING => 'info',
                        default => 'warning',
                    }),
                TextColumn::make('generated_at')
                    ->label('Generated')
                    ->since()
                    ->sortable(),
            ])
            ->filters([
                WorkspaceTable::filter(),
                SelectFilter::make('review_status')
                    ->label('Review status')
                    ->default(AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW)
                    ->options([
                        AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW => 'Awaiting review',
                        AiEssayFeedbackDraft::STATUS_APPROVED => 'Approved',
                        AiEssayFeedbackDraft::STATUS_REJECTED => 'Rejected',
                        AiEssayFeedbackDraft::STATUS_GENERATING => 'Generating',
                        AiEssayFeedbackDraft::STATUS_SUPERSEDED => 'Superseded',
                    ]),
            ])
            ->actions([
                EditAction::make()->label('Review feedback'),
            ])
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No AI feedback awaiting review')
            ->emptyStateDescription('AI-generated essay feedback appears here and remains private until a teacher approves it.');
    }
}
