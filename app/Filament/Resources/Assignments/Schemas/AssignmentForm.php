<?php

namespace App\Filament\Resources\Assignments\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('due_date'),
                Select::make('course_id')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->required()
                    ->label('Course'),
            ]);
    }
}
