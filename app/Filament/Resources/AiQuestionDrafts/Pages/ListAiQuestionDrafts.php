<?php

namespace App\Filament\Resources\AiQuestionDrafts\Pages;

use App\Filament\Resources\AiQuestionDrafts\AiQuestionDraftResource;
use App\Jobs\GenerateAiQuestions;
use App\Models\AiQuestionDraft;
use App\Services\AiQuestionGeneratorService;
use App\Support\AiQueueWorker;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\Storage;

class ListAiQuestionDrafts extends ListRecords
{
    protected static string $resource = AiQuestionDraftResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('generate')
                ->label('Generate from file or text')
                ->icon('heroicon-o-sparkles')
                ->color('primary')
                ->modalHeading('AI Question Generator')
                ->modalSubmitActionLabel('Start Generation')
                ->modalWidth('3xl')
                ->form([
                    TextInput::make('title')
                        ->label('Draft Title')
                        ->placeholder('e.g. Chapter 3 – Cell Division')
                        ->maxLength(255)
                        ->required(),
                    TextInput::make('topic')
                        ->label('Topic / Focus (optional)')
                        ->maxLength(255)
                        ->placeholder('Narrow the AI to a specific subject in the material'),
                    FileUpload::make('file')
                        ->label('Upload PDF / Word / TXT')
                        ->disk('local')
                        ->directory('ai-question-sources')
                        ->visibility('private')
                        ->acceptedFileTypes([
                            'application/pdf',
                            'application/msword',
                            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                            'text/plain',
                            'text/markdown',
                        ])
                        ->maxSize(20 * 1024)
                        ->helperText('Or paste content below instead. Max 20 MB.'),
                    Textarea::make('pasted_text')
                        ->label('…or paste the source text')
                        ->rows(6)
                        ->maxLength(100000)
                        ->helperText('Leave empty if you uploaded a file.'),
                    Grid::make(4)
                        ->schema([
                            TextInput::make('counts.multiple_choice')
                                ->label('Multiple Choice')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(30)
                                ->default(5)
                                ->required(),
                            TextInput::make('counts.true_false')
                                ->label('True/False')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(30)
                                ->default(3)
                                ->required(),
                            TextInput::make('counts.identification')
                                ->label('Identification')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(30)
                                ->default(3)
                                ->required(),
                            TextInput::make('counts.essay')
                                ->label('Essay')
                                ->numeric()
                                ->minValue(0)
                                ->maxValue(10)
                                ->default(1)
                                ->required(),
                        ]),
                    Select::make('difficulty')
                        ->options([
                            'easy' => 'Easy',
                            'medium' => 'Medium',
                            'hard' => 'Hard',
                        ])
                        ->default('medium')
                        ->required(),
                ])
                ->action(function (array $data, AiQuestionGeneratorService $service): void {
                    $counts = array_map('intval', (array) ($data['counts'] ?? []));
                    if (array_sum($counts) <= 0) {
                        Notification::make()
                            ->title('Please request at least one question.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $filename = null;
                    $sourceText = trim((string) ($data['pasted_text'] ?? ''));

                    if (! empty($data['file'])) {
                        $relative = is_array($data['file']) ? reset($data['file']) : $data['file'];
                        $filename = basename((string) $relative);
                        $abs = Storage::disk('local')->path((string) $relative);
                        $extracted = $service->extractText($abs);

                        if ($extracted === '' && $sourceText === '') {
                            Notification::make()
                                ->title('Could not extract text from the uploaded file.')
                                ->body('The PDF may be scanned/image-based. Paste the content manually or try a different file.')
                                ->danger()
                                ->send();

                            return;
                        }

                        if ($sourceText === '') {
                            $sourceText = $extracted;
                        }
                    }

                    if ($sourceText === '') {
                        Notification::make()
                            ->title('No source provided.')
                            ->body('Upload a file or paste some text.')
                            ->danger()
                            ->send();

                        return;
                    }

                    $draft = AiQuestionDraft::create([
                        'user_id' => auth()->id(),
                        'title' => $data['title'],
                        'topic' => $data['topic'] ?? null,
                        'source_filename' => $filename,
                        'source_text' => $sourceText,
                        'type_counts' => $counts,
                        'difficulty' => $data['difficulty'] ?? 'medium',
                        'status' => 'pending',
                    ]);

                    GenerateAiQuestions::dispatch($draft->id);
                    AiQueueWorker::ensureRunning();

                    Notification::make()
                        ->title('Generation queued')
                        ->body('The AI is generating questions. This page refreshes automatically.')
                        ->success()
                        ->send();
                }),
        ];
    }
}
