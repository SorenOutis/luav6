<?php

namespace App\Filament\Resources\Assignments\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('sections')
                    ->label('Assign to sections')
                    ->relationship('sections', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required()
                    ->minItems(1)
                    ->helperText('Only students in the selected sections receive this assignment and can submit to it.')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Instructions')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                DateTimePicker::make('due_date')
                    ->label('Due date')
                    ->seconds(false)
                    ->columnSpan(1),
                TextInput::make('points_possible')
                    ->label('Points possible')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999999)
                    ->placeholder('e.g. 100')
                    ->helperText('Optional. Students see what the work is worth, and grades display as earned / possible.')
                    ->columnSpan(1),
                Select::make('group_size')
                    ->label('Group size')
                    ->options([
                        1 => '1 — Individual',
                        2 => '2 — Pair',
                        3 => '3 — Trio',
                        4 => '4 — Quartet',
                    ])
                    ->searchable()
                    ->allowCustomValue()
                    ->default(1)
                    ->required()
                    ->rules(['required', 'integer', 'between:1,20'])
                    ->helperText('How many students work together per group. Pick 1 for an individual assignment, or type any number up to 20 for a larger group.')
                    ->formatStateUsing(fn ($record) => $record?->max_group_size ?? 1)
                    ->columnSpan(1),
                Select::make('course_id')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Course')
                    ->placeholder('Select a course (optional)')
                    ->helperText('Optional label only — visibility is controlled by the sections above.')
                    ->columnSpan(1),
            ]);
    }
}
