<?php

namespace App\Filament\Resources\AiEssayFeedbackDrafts\Schemas;

use App\Models\AiEssayFeedbackDraft;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AiEssayFeedbackDraftForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Section::make('Submission')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Placeholder::make('student')
                            ->content(fn ($record): string => $record?->submission?->user?->name ?? 'Unknown student'),
                        Placeholder::make('exam')
                            ->content(fn ($record): string => $record?->submission?->exam?->title ?? 'Unknown exam'),
                        Placeholder::make('part')
                            ->content(fn ($record): string => $record?->submission?->examPart?->title ?? 'Unknown part'),
                        Placeholder::make('question_number_display')
                            ->label('Question')
                            ->content(fn ($record): string => '#'.number_format((int) ($record?->question_number ?? 0))),
                        Textarea::make('question_text')
                            ->label('Essay question')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Textarea::make('answer_text')
                            ->label('Student answer')
                            ->rows(8)
                            ->disabled()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ]),

                Section::make('AI Proposal — Teacher Review Required')
                    ->description('Edit the proposal if needed. Saving does not publish it; only Approve Feedback applies the score and feedback to the student submission.')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        TextInput::make('proposed_score')
                            ->label('Proposed score')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(fn ($record): float => (float) ($record?->max_points ?? 0))
                            ->required()
                            ->disabled(fn ($record): bool => $record?->review_status !== AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW),
                        Placeholder::make('max_points_display')
                            ->label('Maximum points')
                            ->content(fn ($record): string => number_format((float) ($record?->max_points ?? 0), 2)),
                        Textarea::make('proposed_feedback')
                            ->label('Proposed feedback')
                            ->rows(6)
                            ->required()
                            ->maxLength(10000)
                            ->disabled(fn ($record): bool => $record?->review_status !== AiEssayFeedbackDraft::STATUS_AWAITING_REVIEW)
                            ->columnSpanFull(),
                    ]),

                Section::make('Review history')
                    ->columnSpanFull()
                    ->columns(2)
                    ->schema([
                        Placeholder::make('review_status')
                            ->label('Status')
                            ->content(fn ($record): string => str($record?->review_status ?? 'unknown')->replace('_', ' ')->title()->toString()),
                        Placeholder::make('generation_version_display')
                            ->label('AI revision')
                            ->content(fn ($record): string => '#'.number_format((int) ($record?->generation_version ?? 0))),
                        Placeholder::make('provider_display')
                            ->label('Provider / model')
                            ->content(fn ($record): string => trim(($record?->provider ?? 'Unknown').' · '.($record?->model ?? 'Default model'))),
                        Placeholder::make('reviewed_by')
                            ->label('Reviewed by')
                            ->content(fn ($record): string => $record?->reviewer?->name ?? '—'),
                        Placeholder::make('rejection_reason')
                            ->label('Rejection reason')
                            ->content(fn ($record): string => $record?->rejection_reason ?: '—')
                            ->visible(fn ($record): bool => filled($record?->rejection_reason))
                            ->columnSpanFull(),
                        Placeholder::make('last_error')
                            ->label('Last generation error')
                            ->content(fn ($record): string => $record?->last_error ?: '—')
                            ->visible(fn ($record): bool => filled($record?->last_error))
                            ->columnSpanFull(),
                        Placeholder::make('review_activity')
                            ->label('Recent review activity')
                            ->content(fn ($record): string => $record
                                ? ($record->reviewEvents()->with('actor:id,name')->limit(8)->get()
                                    ->map(fn ($event): string => ($event->actor?->name ?? 'System').' · '.str($event->event)->replace('_', ' ')->title().' · '.$event->created_at?->diffForHumans())
                                    ->implode("\n") ?: 'No review events yet.')
                                : 'No review events yet.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
