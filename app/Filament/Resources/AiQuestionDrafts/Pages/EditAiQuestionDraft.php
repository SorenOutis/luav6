<?php

namespace App\Filament\Resources\AiQuestionDrafts\Pages;

use App\Filament\Resources\AiQuestionDrafts\AiQuestionDraftResource;
use App\Filament\Resources\Exams\ExamResource;
use App\Jobs\GenerateAiQuestions;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\User;
use App\Support\AiQueueWorker;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditAiQuestionDraft extends EditRecord
{
    protected static string $resource = AiQuestionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerate')
                ->label('Regenerate')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalDescription('Re-run the AI over the same source material. This replaces the current questions.')
                ->visible(fn ($record): bool => $record && in_array($record->status, ['ready', 'failed'], true))
                ->action(function () {
                    $this->record->forceFill([
                        'status' => 'pending',
                        'last_error' => null,
                        'questions' => null,
                    ])->save();

                    GenerateAiQuestions::dispatch($this->record->id);
                    AiQueueWorker::ensureRunning();

                    Notification::make()
                        ->title('Regeneration queued')
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('edit', ['record' => $this->record->id]));
                }),

            Action::make('attachToExam')
                ->label('Attach to Exam')
                ->icon('heroicon-o-paper-airplane')
                ->color('success')
                ->visible(fn ($record): bool => $record?->status === 'ready' && is_array($record->questions) && count($record->questions) > 0)
                ->form([
                    Select::make('exam_id')
                        ->label('Target Exam')
                        ->options(fn () => Exam::query()->orderByDesc('exam_date')->pluck('title', 'id')->all())
                        ->searchable()
                        ->required(),
                    Textarea::make('instructions')
                        ->label('Instructions (applied to each new part)')
                        ->rows(2)
                        ->placeholder('Optional. Leave blank to use default per-type instructions.'),
                    TextInput::make('points')
                        ->label('Default Points Per Question')
                        ->numeric()
                        ->default(1)
                        ->required(),
                ])
                ->action(function (array $data) {
                    $exam = Exam::query()->findOrFail((int) $data['exam_id']);
                    $default = (int) ($data['points'] ?? 1) ?: 1;
                    $customInstructions = trim((string) ($data['instructions'] ?? ''));

                    // Group questions by type in the required order.
                    $order = ['multiple_choice', 'true_false', 'identification', 'essay'];
                    $grouped = array_fill_keys($order, []);

                    foreach ((array) ($this->record->questions ?? []) as $q) {
                        if (! is_array($q)) {
                            continue;
                        }
                        $type = (string) ($q['type'] ?? '');
                        if (! isset($grouped[$type])) {
                            continue;
                        }
                        $q['points'] = (int) ($q['points'] ?? $default) ?: $default;
                        $grouped[$type][] = $q;
                    }

                    $labels = [
                        'multiple_choice' => 'Multiple Choice',
                        'true_false' => 'True or False',
                        'identification' => 'Identification',
                        'essay' => 'Essay',
                    ];
                    $defaultInstructions = [
                        'multiple_choice' => 'Choose the best answer for each item.',
                        'true_false' => 'Write TRUE if the statement is correct, otherwise write FALSE.',
                        'identification' => 'Write the term or phrase being described.',
                        'essay' => 'Answer the following in complete sentences.',
                    ];

                    $nextOrder = (int) ($exam->parts()->max('sort_order') ?? 0);
                    $partIndex = $exam->parts()->count();
                    $romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
                    $created = 0;

                    foreach ($order as $type) {
                        $questions = $grouped[$type];
                        if (empty($questions)) {
                            continue;
                        }

                        $nextOrder++;
                        $partIndex++;
                        $roman = $romans[$partIndex - 1] ?? (string) $partIndex;

                        ExamPart::create([
                            'exam_id' => $exam->id,
                            'title' => "Part {$roman} - {$labels[$type]}",
                            'instructions' => $customInstructions !== '' ? $customInstructions : $defaultInstructions[$type],
                            'type' => 'section',
                            'sort_order' => $nextOrder,
                            'points' => $default,
                            'questions' => $questions,
                        ]);
                        $created++;
                    }

                    if ($created === 0) {
                        Notification::make()
                            ->title('Nothing to attach')
                            ->body('No questions matched the supported types.')
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Attached to exam')
                        ->body("Added {$created} part(s) to \"{$exam->title}\".")
                        ->success()
                        ->send();

                    $this->redirect(ExamResource::getUrl('edit', ['record' => $exam->id]));
                }),

            Action::make('transfer')
                ->label('Transfer')
                ->icon('heroicon-o-arrow-right-start-on-rectangle')
                ->color('warning')
                ->modalHeading('Transfer Draft')
                ->modalDescription('Transfer this AI question draft to another admin.')
                ->modalSubmitActionLabel('Transfer')
                ->form([
                    Select::make('target_admin_id')
                        ->label('Transfer to')
                        ->options(function () {
                            $currentUserId = auth()->id();

                            return User::query()
                                ->where('is_admin', true)
                                ->whereKeyNot($currentUserId)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->required()
                        ->placeholder('Select an admin…'),
                ])
                ->action(function (array $data) {
                    $this->record->update(['admin_id' => $data['target_admin_id']]);

                    $targetAdmin = User::find($data['target_admin_id']);

                    Notification::make()
                        ->title('Draft transferred')
                        ->body("Transferred to {$targetAdmin?->name} successfully.")
                        ->success()
                        ->send();

                    $this->redirect(static::getResource()::getUrl('index'));
                }),

            DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Draft updated';
    }

    /**
     * Called by Alpine polling every 3s while the AI is generating.
     * Refreshes the record from the database so the form schema
     * callbacks (status, questions, etc.) reflect the latest state.
     */
    public function pollGenerationStatus(): void
    {
        $status = $this->record->status ?? '';

        if (! in_array($status, ['pending', 'running', 'generating_source'], true)) {
            return;
        }

        $this->record->refresh();
        $newStatus = $this->record->status ?? '';

        // Refreshing the Eloquent record updates the schema callbacks (which read
        // $record for their ->visible()), but the form FIELD state (the questions
        // Repeater, last_error, ai_response, etc.) was filled once when the page
        // mounted — while status was still pending and questions was null. Without
        // re-filling, the "Generated Questions" section turns visible as empty once
        // the job finishes, which makes it look like the AI never responded.
        if (in_array($newStatus, ['ready', 'failed'], true)) {
            $this->refreshFormData([
                'status',
                'questions',
                'last_error',
                'ai_response',
                'generated_at',
            ]);
        }
    }
}
