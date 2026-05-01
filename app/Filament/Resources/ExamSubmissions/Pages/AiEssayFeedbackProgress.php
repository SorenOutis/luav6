<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Jobs\GenerateExamEssayFeedback;
use App\Models\Exam;
use App\Models\ExamAiFeedbackRun;
use App\Models\ExamSubmission;
use App\Support\AiQueueWorker;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class AiEssayFeedbackProgress extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ExamSubmissionResource::class;

    protected string $view = 'filament.resources.exam-submissions.pages.ai-essay-feedback-progress';

    public Exam $exam;

    public int $totalParts = 0;

    public function mount(Exam $exam): void
    {
        $this->exam = $exam->loadMissing('section');
        $this->totalParts = $exam->parts()->count();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('rerunMissingFeedback')
                ->label('Re-run (fill missing feedback)')
                ->icon('heroicon-o-arrow-path')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Re-run AI essay feedback?')
                ->modalDescription('This will run AI evaluation again only for essay answers that are missing AI feedback. Existing scores will not be double-counted.')
                ->action(function () {
                    $activeRun = ExamAiFeedbackRun::query()
                        ->where('exam_id', $this->exam->id)
                        ->where('status', 'running')
                        ->where('started_at', '>=', now()->subMinutes(30))
                        ->latest('id')
                        ->first();

                    if ($activeRun) {
                        Notification::make()
                            ->title('AI feedback already running')
                            ->body('A run is currently in progress for this exam.')
                            ->warning()
                            ->send();

                        return redirect()->to(ExamSubmissionResource::getUrl('ai-feedback-progress', ['exam' => $this->exam->id]));
                    }

                    GenerateExamEssayFeedback::dispatch($this->exam->id);
                    AiQueueWorker::ensureRunning();

                    Notification::make()
                        ->title('AI feedback run started')
                        ->body('Progress will update automatically on this page.')
                        ->success()
                        ->send();

                    return redirect()->to(ExamSubmissionResource::getUrl('ai-feedback-progress', ['exam' => $this->exam->id]));
                }),
            Action::make('cancelRun')
                ->label('Cancel Run')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Cancel AI feedback run?')
                ->modalDescription('This stops the currently running AI evaluation for this exam.')
                ->visible(fn (): bool => ExamAiFeedbackRun::query()
                    ->where('exam_id', $this->exam->id)
                    ->where('status', 'running')
                    ->where('started_at', '>=', now()->subMinutes(30))
                    ->exists())
                ->action(function () {
                    $updated = ExamAiFeedbackRun::query()
                        ->where('exam_id', $this->exam->id)
                        ->where('status', 'running')
                        ->where('started_at', '>=', now()->subMinutes(30))
                        ->update([
                            'status' => 'cancelled',
                            'finished_at' => now(),
                            'last_error' => 'Cancelled by admin.',
                        ]);

                    Notification::make()
                        ->title($updated > 0 ? 'Run cancelled' : 'No active run found')
                        ->body($updated > 0 ? 'The AI feedback run has been cancelled.' : 'There is no running AI feedback job to cancel.')
                        ->warning()
                        ->send();

                    return redirect()->to(ExamSubmissionResource::getUrl('ai-feedback-progress', ['exam' => $this->exam->id]));
                }),
        ];
    }

    public function getRunProperty(): ?ExamAiFeedbackRun
    {
        return ExamAiFeedbackRun::query()
            ->where('exam_id', $this->exam->id)
            ->latest('id')
            ->first();
    }

    public function getPartBreakdownProperty(): array
    {
        // Approx breakdown based on current submission states (fast, no JSON parsing).
        // Uses the latest known status per part submission.
        return ExamSubmission::query()
            ->where('exam_id', $this->exam->id)
            ->select([
                'exam_part_id',
                DB::raw("SUM(CASE WHEN status = 'pending_ai' THEN 1 ELSE 0 END) as pending_ai"),
                DB::raw("SUM(CASE WHEN status = 'pending_review' THEN 1 ELSE 0 END) as pending_review"),
                DB::raw("SUM(CASE WHEN status = 'submitted' THEN 1 ELSE 0 END) as submitted"),
            ])
            ->groupBy('exam_part_id')
            ->with('examPart:id,title')
            ->get()
            ->map(function (ExamSubmission $row): array {
                return [
                    'part_title' => $row->examPart?->title ?? 'Unknown Part',
                    'pending_ai' => (int) ($row->pending_ai ?? 0),
                    'pending_review' => (int) ($row->pending_review ?? 0),
                    'submitted' => (int) ($row->submitted ?? 0),
                ];
            })
            ->values()
            ->all();
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                ExamAiFeedbackRun::query()
                    ->where('exam_id', $this->exam->id)
                    ->latest('id')
            )
            ->poll('2s')
            ->columns([
                TextColumn::make('id')->label('Run')->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'queued' => 'gray',
                        'running' => 'info',
                        'finished' => 'success',
                        'cancelled' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),
                TextColumn::make('progress')
                    ->label('Progress')
                    ->state(function (ExamAiFeedbackRun $record): string {
                        $total = max(0, (int) $record->total_essays);
                        $done = max(0, (int) $record->processed_essays) + max(0, (int) ($record->skipped_essays ?? 0));
                        $pct = $total > 0 ? (int) floor(($done / $total) * 100) : 0;

                        return "{$done}/{$total} ({$pct}%)";
                    }),
                TextColumn::make('skipped_essays')
                    ->label('Skipped')
                    ->badge()
                    ->color('warning')
                    ->default(0),
                TextColumn::make('current_part_title')
                    ->label('Current Part')
                    ->default('—'),
                TextColumn::make('current_user_name')
                    ->label('Current Student')
                    ->default('—'),
                TextColumn::make('started_at')
                    ->label('Started')
                    ->since()
                    ->sortable(),
                TextColumn::make('finished_at')
                    ->label('Finished')
                    ->since()
                    ->sortable(),
            ]);
    }

    public function getTitle(): string
    {
        return 'AI Essay Feedback Progress: '.$this->exam->title;
    }
}

