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
                // Generation Status banner — shown only while AI is working, with auto-polling
                Section::make('Generation in Progress')
                    ->columnSpanFull()
                    ->extraAlpineAttributes(fn ($record) => in_array($record?->status ?? '', ['pending', 'running', 'generating_source']) ? [
                        'x-data' => '{ pollInterval: null }',
                        'x-init' => 'pollInterval = setInterval(() => $wire.pollGenerationStatus(), 3000)',
                        'x-on:destroy' => 'if (pollInterval) clearInterval(pollInterval)',
                    ] : [])
                    ->visible(fn ($record) => in_array($record?->status ?? '', ['pending', 'running', 'generating_source']))
                    ->schema([
                        Placeholder::make('gen_status')
                            ->label('')
                            ->content(fn ($record) => match ($record?->status) {
                                'pending' => '⏳ Queued — waiting for AI worker...',
                                'running', 'generating_source' => '🔄 AI is generating your questions...',
                                default => strtoupper((string) ($record?->status ?? '—')),
                            })
                            ->columnSpanFull(),
                        Placeholder::make('gen_counts')
                            ->label('Requested counts')
                            ->content(fn ($record) => collect($record?->type_counts ?? [])
                                ->filter(fn ($count) => $count > 0)
                                ->map(fn ($count, $type) => "{$count} ".str_replace('_', ' ', $type))
                                ->implode(', '))
                            ->columnSpanFull(),
                    ]),

                // Generation Failed banner
                Section::make('Generation Failed')
                    ->columnSpanFull()
                    ->visible(fn ($record) => $record?->status === 'failed')
                    ->schema([
                        Placeholder::make('last_error')
                            ->label('Error details')
                            ->content(fn ($record) => $record?->last_error ?: 'Unknown error')
                            ->columnSpanFull(),
                    ]),

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
                    ->visible(fn ($record) => $record?->status === 'ready' && ! empty($record?->questions))
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

                // Pending generation message
                Placeholder::make('pending_questions_note')
                    ->label('')
                    ->content('Questions will appear here once the AI finishes generating.')
                    ->columnSpanFull()
                    ->visible(fn ($record) => in_array($record?->status ?? '', ['pending', 'running', 'generating_source'])),

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
