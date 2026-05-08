<?php

namespace App\Filament\Resources\Grades\Tables;

use App\Models\Grade;
use App\Models\Section;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GradesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.name')
                    ->label('Section')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('score')
                    ->formatStateUsing(fn ($record) => number_format((float) $record->score, 2).' / '.number_format((float) $record->max_score, 2))
                    ->sortable()
                    ->label('Score'),
                TextColumn::make('percentage')
                    ->label('%')
                    ->state(function ($record) {
                        if ((float) $record->max_score <= 0) {
                            return 0;
                        }

                        return round(((float) $record->score / (float) $record->max_score) * 100, 2);
                    })
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2).'%')
                    ->color(fn ($state) => match (true) {
                        (float) $state >= 85 => 'success',
                        (float) $state >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->badge(),
                TextColumn::make('recorder.name')
                    ->label('Recorded by')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime('M d, Y H:i')
                    ->label('Updated')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->options(fn () => Section::orderBy('name')->pluck('name', 'id'))
                    ->searchable(),
                SelectFilter::make('subject')
                    ->options(fn () => Grade::query()
                        ->select('subject')
                        ->distinct()
                        ->orderBy('subject')
                        ->pluck('subject', 'subject')
                        ->toArray()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
