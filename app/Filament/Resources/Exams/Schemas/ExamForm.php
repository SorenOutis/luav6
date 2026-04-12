<?php

namespace App\Filament\Resources\Exams\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExamForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                \Filament\Forms\Components\TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                \Filament\Forms\Components\Select::make('section_id')
                    ->relationship('section', 'name')
                    ->label('Section')
                    ->placeholder('Select a section (Optional)')
                    ->helperText('If selected, only students in this section can see and take this exam.')
                    ->columnSpanFull(),
                \Filament\Forms\Components\Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                \Filament\Forms\Components\DateTimePicker::make('exam_date')
                    ->required()
                    ->columnSpan(1),
                \Filament\Forms\Components\TextInput::make('duration_minutes')
                    ->required()
                    ->numeric()
                    ->default(60)
                    ->columnSpan(1),
                \Filament\Forms\Components\Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'closed' => 'Closed',
                    ])
                    ->required()
                    ->default('draft')
                    ->columnSpan(1),
                \Filament\Forms\Components\TextInput::make('url')
                    ->url()
                    ->maxLength(255)
                    ->columnSpan(1),
                \Filament\Schemas\Components\Section::make('Exam Parts')
                    ->description('Add parts/sections to this exam. Each part can contain multiple questions.')
                    ->columnSpanFull()
                    ->schema([
                        \Filament\Forms\Components\Repeater::make('parts')
                            ->relationship('parts')
                            ->columns(1)
                            ->schema([
                                \Filament\Forms\Components\TextInput::make('title')
                                    ->label('Part Title')
                                    ->placeholder('e.g., Part I - Multiple Choice')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                \Filament\Forms\Components\Textarea::make('instructions')
                                    ->placeholder('Instructions for this part...')
                                    ->maxLength(65535)
                                    ->columnSpanFull(),
                                \Filament\Forms\Components\TextInput::make('points')
                                    ->label('Default Points Per Question')
                                    ->numeric()
                                    ->default(1)
                                    ->required()
                                    ->columnSpan(1),
                                \Filament\Forms\Components\Repeater::make('questions')
                                     ->label('Questions')
                                     ->columns(2)
                                     ->schema([
                                         \Filament\Forms\Components\TextInput::make('text')
                                             ->label('Question')
                                             ->required()
                                             ->placeholder('Enter the question text')
                                             ->columnSpanFull(),
                                         \Filament\Forms\Components\Select::make('type')
                                             ->label('Type')
                                             ->options([
                                                 'multiple_choice' => 'Multiple Choice',
                                                 'identification' => 'Identification',
                                                 'essay' => 'Essay',
                                                 'true_false' => 'True/False',
                                             ])
                                             ->required()
                                             ->live()
                                             ->columnSpan(1),
                                         \Filament\Forms\Components\TextInput::make('points')
                                             ->label('Points')
                                             ->numeric()
                                             ->default(fn($get) => $get('../../points') ?? 1)
                                             ->required()
                                             ->columnSpan(1),
                                        \Filament\Forms\Components\Repeater::make('options')
                                            ->label('Choices')
                                            ->schema([
                                                \Filament\Forms\Components\TextInput::make('text')
                                                    ->required()
                                                    ->placeholder('Choice text'),
                                                \Filament\Forms\Components\Checkbox::make('is_correct')
                                                    ->label('Correct?'),
                                            ])
                                            ->visible(fn($get) => $get('type') === 'multiple_choice' || $get('type') === 'true_false')
                                            ->grid(2)
                                            ->itemLabel(fn(array $state): ?string => $state['text'] ?? null)
                                            ->collapsible(),
                                        \Filament\Forms\Components\TextInput::make('correct_answer')
                                             ->label('Correct Answer')
                                             ->visible(fn($get) => $get('type') === 'identification')
                                             ->maxLength(255)
                                             ->columnSpan(1),
                                    ])
                                    ->itemLabel(fn(array $state): ?string => $state['text'] ?? 'New Question')
                                    ->collapsible()
                                    ->addActionLabel('Add Question'),
                                \Filament\Forms\Components\Hidden::make('sort_order')
                                    ->default(0),
                                \Filament\Forms\Components\Hidden::make('type')
                                    ->default('section'),
                            ])
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->itemLabel(fn(array $state): ?string => $state['title'] ?? 'New Part')
                            ->addActionLabel('Add Part'),
                    ]),
            ]);
    }
}
