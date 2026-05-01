<?php

namespace App\Filament\Resources\AiQuestionDrafts\Pages;

use App\Filament\Resources\AiQuestionDrafts\AiQuestionDraftResource;
use App\Filament\Resources\Exams\ExamResource;
use App\Jobs\GenerateAiQuestions;
use App\Models\Exam;
use App\Models\ExamPart;
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
                ->visible(fn (): bool => in_array($this->record->status, ['ready', 'failed'], true))
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
                ->visible(fn (): bool => $this->record->status === 'ready' && is_array($this->record->questions) && count($this->record->questions) > 0)
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

            DeleteAction::make(),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Draft updated';
    }
}
