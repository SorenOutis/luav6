<?php

namespace App\Filament\Resources\ExamSubmissions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class ExamSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('exam.title')
                    ->label('Exam')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('examPart.title')
                    ->label('Part')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextInputColumn::make('score')
                    ->label('Score')
                    ->type('number')
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()
                        ->label('Total Score')),
                \Filament\Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'graded' => 'Graded',
                    ])
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                \Filament\Tables\Grouping\Group::make('user.name')
                    ->label('Student')
                    ->collapsible(),
                \Filament\Tables\Grouping\Group::make('exam.title')
                    ->label('Exam')
                    ->collapsible(),
            ])
            ->defaultGroup('user.name')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name'),
                \Filament\Tables\Filters\SelectFilter::make('exam_id')
                    ->label('Exam')
                    ->relationship('exam', 'title'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
