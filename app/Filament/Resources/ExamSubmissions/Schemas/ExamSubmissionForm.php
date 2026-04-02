<?php

namespace App\Filament\Resources\ExamSubmissions\Schemas;

use Filament\Schemas\Schema;

class ExamSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled()
                    ->required(),
                \Filament\Forms\Components\Select::make('exam_id')
                    ->relationship('exam', 'title')
                    ->disabled()
                    ->required(),
                \Filament\Forms\Components\Select::make('exam_part_id')
                    ->relationship('examPart', 'title')
                    ->disabled()
                    ->required(),
                \Filament\Forms\Components\TextInput::make('score')
                    ->numeric()
                    ->required(),
                \Filament\Forms\Components\Select::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'graded' => 'Graded',
                    ])
                    ->required(),
                \Filament\Forms\Components\Textarea::make('feedback')
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }
}
