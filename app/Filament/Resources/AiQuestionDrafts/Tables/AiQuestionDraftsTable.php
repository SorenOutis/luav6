<?php

namespace App\Filament\Resources\AiQuestionDrafts\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AiQuestionDraftsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->poll('5s')
            ->columns([
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
                        'running' => 'info',
                        'ready' => 'success',
                        'failed' => 'danger',
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
                EditAction::make()
                    ->label('Review'),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
