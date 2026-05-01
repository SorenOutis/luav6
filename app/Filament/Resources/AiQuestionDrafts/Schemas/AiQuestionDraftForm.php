<?php

namespace App\Filament\Resources\AiQuestionDrafts\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AiQuestionDraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Draft details')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('title')
                            ->maxLength(255)
                            ->columnSpan(1),
                        TextInput::make('topic')
                            ->label('Topic / focus')
                            ->maxLength(255)
                            ->columnSpan(1),
                        Placeholder::make('source_filename')
                            ->label('Source file')
                            ->content(fn ($record) => $record?->source_filename ?: '—'),
                        Placeholder::make('status')
                            ->content(fn ($record) => strtoupper((string) ($record?->status ?? '—'))),
                        Placeholder::make('last_error')
                            ->label('Last error')
                            ->content(fn ($record) => $record?->last_error ?: '—')
                            ->visible(fn ($record) => (bool) $record?->last_error)
                            ->columnSpanFull(),
                    ]),

                Section::make('Generated Questions')
                    ->description('Review and edit the AI-generated questions. These will be attached to the exam as a new part when you click "Attach to Exam".')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('questions')
                            ->hiddenLabel()
                            ->columns(2)
                            ->schema([
                                TextInput::make('text')
                                    ->label('Question')
                                    ->required()
                                    ->columnSpanFull(),
                                Select::make('type')
                                    ->label('Type')
                                    ->options([
                                        'multiple_choice' => 'Multiple Choice',
                                        'true_false' => 'True/False',
                                        'identification' => 'Identification',
                                        'essay' => 'Essay',
                                    ])
                                    ->required()
                                    ->live()
                                    ->columnSpan(1),
                                TextInput::make('points')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1),
                                Repeater::make('options')
                                    ->label('Choices')
                                    ->schema([
                                        TextInput::make('text')
                                            ->required()
                                            ->placeholder('Choice text'),
                                        Checkbox::make('is_correct')
                                            ->label('Correct?'),
                                    ])
                                    ->visible(fn ($get) => in_array($get('type'), ['multiple_choice', 'true_false'], true))
                                    ->grid(2)
                                    ->itemLabel(fn (array $state): ?string => $state['text'] ?? null)
                                    ->collapsible()
                                    ->columnSpanFull(),
                                TextInput::make('correct_answer')
                                    ->label('Correct Answer')
                                    ->visible(fn ($get) => $get('type') === 'identification')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                            ])
                            ->itemLabel(fn (array $state): ?string => ($state['text'] ?? 'New Question'))
                            ->collapsible()
                            ->cloneable()
                            ->addActionLabel('Add Question'),
                    ]),

                Section::make('Source Text (read-only)')
                    ->collapsed()
                    ->columnSpanFull()
                    ->schema([
                        Textarea::make('source_text')
                            ->rows(10)
                            ->disabled()
                            ->hiddenLabel(),
                    ]),
            ]);
    }
}
