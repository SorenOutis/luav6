<?php

namespace App\Filament\Resources\ExamSubmissions\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ExamSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled()
                    ->required(),
                Select::make('exam_id')
                    ->relationship('exam', 'title')
                    ->disabled()
                    ->required(),
                Select::make('exam_part_id')
                    ->relationship('examPart', 'title')
                    ->disabled()
                    ->required(),
                TextInput::make('score')
                    ->numeric()
                    ->required(),
                Select::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_review' => 'Pending Review',
                        'graded' => 'Graded',
                    ])
                    ->required(),
                Textarea::make('feedback')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Repeater::make('answers')
                    ->label('Student answers')
                    ->columnSpanFull()
                    ->defaultItems(0)
                    ->reorderable(false)
                    ->addActionLabel('Add answer row')
                    ->table([
                        TableColumn::make('#'),
                        TableColumn::make('Type'),
                        TableColumn::make('Question'),
                        TableColumn::make('Max pts'),
                        TableColumn::make('Answer'),
                    ])
                    ->schema([
                        TextInput::make('question_number')
                            ->numeric()
                            ->minValue(1)
                            ->required(),
                        Select::make('question_type')
                            ->options([
                                'multiple_choice' => 'Multiple choice',
                                'true_false' => 'True / false',
                                'essay' => 'Essay',
                                'identification' => 'Identification',
                            ])
                            ->required(),
                        Textarea::make('question_text')
                            ->rows(2)
                            ->required(),
                        TextInput::make('points')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Textarea::make('answer')
                            ->rows(2)
                            ->nullable(),
                    ]),
            ]);
    }
}
