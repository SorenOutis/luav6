<?php

namespace App\Filament\Resources\ExamSubmissions\Schemas;

use App\Enums\EssayGradingMethod;
use App\Support\ExamPartAnswerLabels;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
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
                    ->helperText('For manually graded essays, add the awarded essay points to the current score, then set the status to Graded.')
                    ->required(),
                Select::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_ai' => 'Pending Automatic AI Grading',
                        'pending_review' => 'Pending Teacher Grading',
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
                        TableColumn::make('Grading'),
                        TableColumn::make('Question'),
                        TableColumn::make('Max pts'),
                        TableColumn::make('Answer'),
                    ])
                    ->schema([
                        TextInput::make('question_number')
                            ->numeric()
                            ->minValue(1)
                            ->required()
                            ->live(onBlur: true),
                        Select::make('question_type')
                            ->options([
                                'multiple_choice' => 'Multiple choice',
                                'true_false' => 'True / false',
                                'essay' => 'Essay',
                                'identification' => 'Identification',
                            ])
                            ->required()
                            ->live(),
                        Select::make('grading_method')
                            ->label('Grading')
                            ->options(EssayGradingMethod::options())
                            ->visible(fn (callable $get): bool => $get('question_type') === 'essay')
                            ->disabled()
                            ->dehydrated()
                            ->required(fn (callable $get): bool => $get('question_type') === 'essay'),
                        Textarea::make('question_text')
                            ->rows(2)
                            ->required(),
                        TextInput::make('points')
                            ->numeric()
                            ->minValue(0)
                            ->required(),
                        Group::make([
                            Select::make('response_choice')
                                ->label('Answer')
                                ->options(fn (callable $get): array => ExamPartAnswerLabels::choiceOptionsForRowWithCurrent($get))
                                ->visible(fn (callable $get): bool => in_array($get('question_type'), ['multiple_choice', 'true_false'], true))
                                ->dehydrated(fn (callable $get): bool => in_array($get('question_type'), ['multiple_choice', 'true_false'], true))
                                ->native(false)
                                ->searchable()
                                ->placeholder("Select the student's answer")
                                ->helperText(fn (callable $get): ?string => ExamPartAnswerLabels::correctChoiceHint($get)),
                            Textarea::make('response_text')
                                ->label('Answer')
                                ->rows(4)
                                ->visible(fn (callable $get): bool => ! in_array($get('question_type'), ['multiple_choice', 'true_false'], true))
                                ->dehydrated(fn (callable $get): bool => ! in_array($get('question_type'), ['multiple_choice', 'true_false'], true))
                                ->placeholder(fn (callable $get): string => match ($get('question_type')) {
                                    'essay' => 'Student essay response',
                                    default => 'Student answer',
                                }),
                            TextInput::make('ai_score')
                                ->label('Automatic AI score')
                                ->numeric()
                                ->disabled()
                                ->dehydrated()
                                ->visible(fn (callable $get): bool => $get('question_type') === 'essay'
                                    && $get('grading_method') === EssayGradingMethod::Ai->value
                                    && $get('ai_score') !== null),
                            Textarea::make('ai_feedback')
                                ->label('Automatic AI feedback')
                                ->rows(3)
                                ->disabled()
                                ->dehydrated()
                                ->visible(fn (callable $get): bool => $get('question_type') === 'essay'
                                    && $get('grading_method') === EssayGradingMethod::Ai->value
                                    && filled($get('ai_feedback'))),
                        ]),
                        Hidden::make('ai_feedback_source'),
                        Hidden::make('ai_feedback_review_id'),
                    ]),
            ]);
    }
}
