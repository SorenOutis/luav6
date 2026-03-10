<?php

namespace App\Filament\Resources\Exams\RelationManagers;

use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Exam Submissions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required()
                    ->label('Student'),
                Forms\Components\Select::make('exam_part_id')
                    ->relationship('examPart', 'title')
                    ->required()
                    ->label('Exam Part'),
                Forms\Components\Textarea::make('answers')
                    ->label('Answers')
                    ->columnSpanFull(),
                Forms\Components\Select::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'graded' => 'Graded',
                        'pending' => 'Pending',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('score')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->label('Score'),
                Forms\Components\Textarea::make('feedback')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('examPart.title')
                    ->label('Part')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'submitted' => 'info',
                        'graded' => 'success',
                        'pending' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->numeric(
                        decimalPlaces: 2,
                    )
                    ->label('Score')
                    ->placeholder('N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'graded' => 'Graded',
                        'pending' => 'Pending',
                    ]),
            ])
            ->recordActions([
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
