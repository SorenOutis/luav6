<?php

namespace App\Filament\Resources\AiQuestionDrafts\Tables;

use App\Filament\Support\WorkspaceTable;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AiQuestionDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('15s')
            ->columns([
                WorkspaceTable::column(),
                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->limit(40)
                    ->placeholder('(untitled)'),
                TextColumn::make('source_filename')
                    ->label('Source')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('topic')
                    ->label('Topic')
                    ->limit(30)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('difficulty')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'easy' => 'success',
                        'medium' => 'warning',
                        'hard' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'pending' => 'gray',
                        'generating_source' => 'info',
                        'running' => 'info',
                        'ready' => 'success',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('review_status')
                    ->label('Teacher review')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'awaiting_review' => 'Awaiting review',
                        default => 'Not ready',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'approved' => 'success',
                        'rejected' => 'danger',
                        'awaiting_review' => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('questions_count')
                    ->label('Questions')
                    ->state(fn ($record): int => is_array($record->questions) ? count($record->questions) : 0),
                TextColumn::make('user.name')
                    ->label('Created by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->since()
                    ->sortable(),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Review draft'),
                    Action::make('transfer')
                        ->label('Transfer')
                        ->icon('heroicon-o-arrow-right-start-on-rectangle')
                        ->color('warning')
                        ->modalHeading('Transfer Draft')
                        ->modalDescription('Transfer this AI question draft to another admin.')
                        ->modalSubmitActionLabel('Transfer')
                        ->form([
                            Select::make('target_admin_id')
                                ->label('Transfer to')
                                ->options(function () {
                                    $currentUserId = auth()->id();

                                    return User::query()
                                        ->where('is_admin', true)
                                        ->whereKeyNot($currentUserId)
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all();
                                })
                                ->searchable()
                                ->required()
                                ->placeholder('Select an admin…'),
                        ])
                        ->action(function (array $data, $record) {
                            $targetAdmin = User::find($data['target_admin_id']);
                            $record->update([
                                'admin_id' => $targetAdmin?->id,
                                'workspace_id' => $targetAdmin?->current_workspace_id,
                            ]);

                            Notification::make()
                                ->title('Draft transferred')
                                ->body("Transferred to {$targetAdmin?->name} successfully.")
                                ->success()
                                ->send();
                        }),
                    DeleteAction::make(),
                ])
                    ->label('Actions')
                    ->icon('heroicon-m-ellipsis-vertical'),
            ])
            ->filters([
                WorkspaceTable::filter(),
                SelectFilter::make('status')
                    ->options([
                        'pending' => 'Pending',
                        'generating_source' => 'Generating source',
                        'running' => 'Generating questions',
                        'ready' => 'Ready',
                        'failed' => 'Failed',
                    ]),
                SelectFilter::make('review_status')
                    ->label('Teacher review')
                    ->options([
                        'awaiting_review' => 'Awaiting review',
                        'approved' => 'Approved',
                        'rejected' => 'Rejected',
                        'not_ready' => 'Not ready',
                    ]),
                SelectFilter::make('difficulty')
                    ->options([
                        'easy' => 'Easy',
                        'medium' => 'Medium',
                        'hard' => 'Hard',
                    ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
