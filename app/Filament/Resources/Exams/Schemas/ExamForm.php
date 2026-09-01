<?php

namespace App\Filament\Resources\Exams\Schemas;

use App\Enums\EssayGradingMethod;
use App\Enums\QuestionType;
use App\Models\Exam;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ExamForm
{
    /**
     * Grow or shrink the "sets" repeater so it holds exactly the requested
     * number of sets.
     *
     * Both the live field and the save hooks recompute the delta from the
     * repeater's current items, so a stale counter can never delete a set that
     * is on screen. Removing a set removes its questions — the admin does that
     * deliberately, either with the repeater's delete button or by lowering the
     * number.
     */
    public static function syncSetCount(array $data): array
    {
        $desired = max(1, min(26, (int) ($data['sets_count'] ?? 1)));

        // The counter is a form-only control: it must never reach the model.
        unset($data['sets_count']);

        // Without the repeater in the payload there is nothing to size — a
        // partial save must not conjure a set out of thin air.
        if (! array_key_exists('sets', $data)) {
            return $data;
        }

        $items = array_values((array) ($data['sets'] ?? []));

        while (count($items) > $desired) {
            array_pop($items);
        }

        while (count($items) < $desired) {
            $items[] = [];
        }

        $data['sets'] = $items;

        return $data;
    }

    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('section_id')
                    ->relationship('section', 'name')
                    ->label('Section')
                    ->placeholder('Select a section (Optional)')
                    ->helperText('If selected, only students in this section can see and take this exam.')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                DateTimePicker::make('exam_date')
                    ->required()
                    ->columnSpan(1),
                TextInput::make('duration_minutes')
                    ->required()
                    ->numeric()
                    ->default(60)
                    ->columnSpan(1),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'closed' => 'Closed',
                    ])
                    ->required()
                    ->default('draft')
                    ->columnSpan(1),
                TextInput::make('url')
                    ->url()
                    ->maxLength(255)
                    ->columnSpan(1),
                Section::make('XP Rewards')
                    ->description('Academic points remain the exam score. These separate XP rewards are granted once, after the final part is submitted.')
                    ->columnSpanFull()
                    ->columns(3)
                    ->schema([
                        Checkbox::make('xp_rewards_enabled')
                            ->label('Enable XP rewards')
                            ->helperText('Enabled by default for new exams. Turn this on manually for older exams.')
                            ->default(true)
                            ->columnSpanFull(),
                        TextInput::make('completion_xp')
                            ->label('Completion XP')
                            ->helperText('Granted for completing every part.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(10)
                            ->required(),
                        TextInput::make('on_time_xp')
                            ->label('On-time XP')
                            ->helperText('Granted when no part was submitted late.')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100)
                            ->default(5)
                            ->required(),
                        Checkbox::make('accuracy_xp_enabled')
                            ->label('Enable accuracy XP')
                            ->helperText('70% = 5 XP, 85% = 10 XP, 95% = 15 XP (highest tier only).')
                            ->default(true),
                    ]),
                Section::make('Exam Sets')
                    ->description('Create one set per version of the exam. Students are dealt a shuffled set the first time they open the exam — every set is handed out before the deck repeats, so a section is split evenly across the versions. Build or import the questions for each set separately — students only ever see the set they were given.')
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('sets_count')
                            ->label('Number of sets')
                            ->helperText('How many versions of this exam to hand out. Sets are added or removed when you save.')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(26)
                            ->default(fn (?Exam $record): int => max(1, $record?->sets()->count() ?: 1))
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set, mixed $state): void {
                                $desired = max(1, min(26, (int) $state ?: 1));
                                $items = array_values((array) ($get('sets') ?? []));

                                while (count($items) > $desired) {
                                    array_pop($items);
                                }

                                while (count($items) < $desired) {
                                    $items[] = [];
                                }

                                $set('sets', $items);
                            })
                            ->columnSpanFull(),
                        Repeater::make('sets')
                            ->relationship('sets')
                            ->orderColumn('sort_order')
                            ->columns(1)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Set title')
                                    ->placeholder('Set A')
                                    ->helperText('Students see this title on the exam card and while taking the exam. Leave it blank to name sets automatically (Set A, Set B, …).')
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Hidden::make('sort_order')
                                    ->default(0),
                                Repeater::make('parts')
                                    ->relationship('parts')
                                    ->columns(1)
                                    ->schema([
                                        TextInput::make('title')
                                            ->label('Part Title')
                                            ->placeholder('e.g., Part I - Multiple Choice')
                                            ->required()
                                            ->maxLength(255)
                                            ->columnSpanFull(),
                                        Textarea::make('instructions')
                                            ->placeholder('Instructions for this part...')
                                            ->maxLength(65535)
                                            ->columnSpanFull(),
                                        TextInput::make('points')
                                            ->label('Default Points Per Question')
                                            ->numeric()
                                            ->default(1)
                                            ->required()
                                            ->columnSpan(1),
                                        Repeater::make('questions')
                                            ->label('Questions')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('text')
                                                    ->label('Question')
                                                    ->required()
                                                    ->placeholder('Enter the question text')
                                                    ->columnSpanFull(),
                                                Select::make('type')
                                                    ->label('Type')
                                                    ->options(QuestionType::options())
                                                    ->required()
                                                    ->live()
                                                    ->columnSpan(1),
                                                TextInput::make('points')
                                                    ->label('Points')
                                                    ->numeric()
                                                    ->default(fn ($get) => $get('../../points') ?? 1)
                                                    ->required(fn ($get): bool => ! in_array($get('type'), [QuestionType::Enumeration->value, QuestionType::Matching->value], true))
                                                    ->visible(fn ($get): bool => ! in_array($get('type'), [QuestionType::Enumeration->value, QuestionType::Matching->value], true))
                                                    ->columnSpan(1),
                                                Radio::make('grading_method')
                                                    ->label('Essay Grading')
                                                    ->options(EssayGradingMethod::options())
                                                    ->default(EssayGradingMethod::Ai->value)
                                                    ->formatStateUsing(fn (?string $state): string => EssayGradingMethod::tryFrom($state)?->value ?? EssayGradingMethod::Ai->value)
                                                    ->helperText('Automatic grades are applied as soon as the AI finishes. Manual essays stay pending until a teacher enters the final score.')
                                                    ->required(fn ($get): bool => $get('type') === 'essay')
                                                    ->visible(fn ($get): bool => $get('type') === 'essay')
                                                    ->dehydrated(fn ($get): bool => $get('type') === 'essay')
                                                    ->inline()
                                                    ->columnSpanFull(),
                                                Repeater::make('options')
                                                    ->label('Choices')
                                                    ->schema([
                                                        TextInput::make('text')
                                                            ->required()
                                                            ->placeholder('Choice text'),
                                                        Checkbox::make('is_correct')
                                                            ->label('Correct?'),
                                                    ])
                                                    ->visible(fn ($get) => $get('type') === 'multiple_choice' || $get('type') === 'true_false')
                                                    ->grid(2)
                                                    ->itemLabel(fn (array $state): ?string => $state['text'] ?? null)
                                                    ->collapsible(),
                                                TextInput::make('correct_answer')
                                                    ->label('Correct Answer')
                                                    ->visible(fn ($get) => $get('type') === 'identification')
                                                    ->maxLength(255)
                                                    ->columnSpan(1),
                                                Repeater::make('accepted_answers')
                                                    ->label('Other Accepted Answers')
                                                    ->helperText('Optional alternatives that should also receive full credit, such as Virus or Malware.')
                                                    ->schema([
                                                        TextInput::make('answer')
                                                            ->label('Accepted Answer')
                                                            ->required()
                                                            ->maxLength(255),
                                                    ])
                                                    ->visible(fn ($get): bool => $get('type') === QuestionType::Identification->value)
                                                    ->dehydrated(fn ($get): bool => $get('type') === QuestionType::Identification->value)
                                                    ->itemLabel(fn (array $state): ?string => $state['answer'] ?? null)
                                                    ->addActionLabel('Add accepted answer')
                                                    ->collapsible()
                                                    ->columnSpanFull(),
                                                Repeater::make('matching_items')
                                                    ->label('Matching Pairs')
                                                    ->helperText('Add each prompt, its correct match, and the points for that pair. Students will see the right-side choices in a different order.')
                                                    ->schema([
                                                        TextInput::make('prompt')
                                                            ->label('Left Item')
                                                            ->required()
                                                            ->maxLength(255),
                                                        TextInput::make('answer')
                                                            ->label('Correct Match')
                                                            ->required()
                                                            ->maxLength(255),
                                                        TextInput::make('points')
                                                            ->label('Points')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->required()
                                                            ->default(1),
                                                    ])
                                                    ->minItems(1)
                                                    ->columns(3)
                                                    ->visible(fn ($get): bool => $get('type') === QuestionType::Matching->value)
                                                    ->dehydrated(fn ($get): bool => $get('type') === QuestionType::Matching->value)
                                                    ->itemLabel(fn (array $state): ?string => $state['prompt'] ?? null)
                                                    ->addActionLabel('Add matching pair')
                                                    ->collapsible()
                                                    ->columnSpanFull(),
                                                Repeater::make('enumeration_items')
                                                    ->label('Enumeration Answers')
                                                    ->helperText('Add each expected item and its individual points. Students may answer these in any order.')
                                                    ->schema([
                                                        TextInput::make('answer')
                                                            ->label('Expected Answer')
                                                            ->required()
                                                            ->maxLength(255),
                                                        TextInput::make('points')
                                                            ->label('Points')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->required()
                                                            ->default(1),
                                                    ])
                                                    ->minItems(1)
                                                    ->columns(2)
                                                    ->visible(fn ($get): bool => $get('type') === QuestionType::Enumeration->value)
                                                    ->dehydrated(fn ($get): bool => $get('type') === QuestionType::Enumeration->value)
                                                    ->columnSpanFull(),
                                            ])
                                            ->itemLabel(fn (array $state): ?string => $state['text'] ?? 'New Question')
                                            ->collapsible()
                                            ->addActionLabel('Add Question'),
                                        Hidden::make('sort_order')
                                            ->default(0),
                                        Hidden::make('type')
                                            ->default('section'),
                                    ])
                                    ->orderColumn('sort_order')
                                    ->collapsible()
                                    ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'New Part')
                                    ->addActionLabel('Add Part'),
                            ])
                            ->minItems(1)
                            ->collapsed()
                            ->itemLabel(fn (array $state): ?string => ($state['title'] ?? null) ?: 'New Set')
                            ->addActionLabel('Add Set')
                            ->defaultItems(1),
                    ]),
            ]);
    }
}
