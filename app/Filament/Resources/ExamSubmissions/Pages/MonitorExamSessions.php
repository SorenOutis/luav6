<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Models\Exam;
use App\Models\ExamLiveSession;
use App\Models\ExamPart;
use App\Models\ExamSetAssignment;
use Filament\Resources\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class MonitorExamSessions extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = ExamSubmissionResource::class;

    protected string $view = 'filament.resources.exam-submissions.pages.monitor-exam-sessions';

    public Exam $exam;

    public int $totalParts = 0;

    /** @var array<int, int> Parts each student has to finish, keyed by user id. */
    public array $partCountsByUser = [];

    /** @var array<int, string> Set each student was handed, keyed by user id. */
    public array $setTitlesByUser = [];

    public function mount(Exam $exam): void
    {
        $this->exam = $exam->loadMissing('sets');
        $this->totalParts = max(1, $exam->parts()->count());

        // With several sets, a student only works through the parts of the set
        // they were handed — counting every set would under-report progress.
        $sets = $exam->sets;

        if ($sets->isEmpty()) {
            return;
        }

        $counts = ExamPart::query()
            ->where('exam_id', $exam->getKey())
            ->selectRaw('exam_set_id, COUNT(*) as total')
            ->groupBy('exam_set_id')
            ->pluck('total', 'exam_set_id');

        $defaultCount = max(1, (int) ($counts[(int) $sets->first()->id] ?? $this->totalParts));

        foreach (ExamSetAssignment::query()->where('exam_id', $exam->getKey())->get() as $assignment) {
            $setId = (int) $assignment->exam_set_id;

            $this->partCountsByUser[$assignment->user_id] = max(1, (int) ($counts[$setId] ?? $defaultCount));
            $this->setTitlesByUser[$assignment->user_id] = (string) ($sets->firstWhere('id', $setId)?->title ?? '');
        }

        $this->totalParts = $defaultCount;
    }

    public function table(Table $table): Table
    {
        // Drop stale sessions so exited students disappear from live monitor.
        ExamLiveSession::query()
            ->where('exam_id', $this->exam->id)
            ->where('last_seen_at', '<', now()->subSeconds(20))
            ->delete();

        return $table
            ->query(
                ExamLiveSession::query()
                    ->where('exam_id', $this->exam->id)
                    ->where('status', '!=', 'finished')
                    ->where('last_seen_at', '>=', now()->subSeconds(20))
                    ->with(['user', 'examPart'])
                    ->orderByDesc('last_seen_at')
            )
            ->poll('3s')
            ->columns([
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable(),
                TextColumn::make('examPart.title')
                    ->label('Current Part')
                    ->default('Waiting to start'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'starting' => 'warning',
                        'in_progress' => 'success',
                        'submitting' => 'info',
                        'finished' => 'gray',
                        default => 'gray',
                    }),
                TextColumn::make('exam_set')
                    ->label('Set')
                    ->state(fn (ExamLiveSession $record): string => $this->setTitlesByUser[$record->user_id] ?? '—')
                    ->placeholder('—'),
                TextColumn::make('aggregate_progress')
                    ->label('Aggregate Progress')
                    ->state(fn (ExamLiveSession $record): string => sprintf(
                        '%d/%d parts | %d/%d questions',
                        $record->submitted_parts_count,
                        $this->partCountsByUser[$record->user_id] ?? $this->totalParts,
                        $record->current_part_answered_count,
                        $record->current_part_total_questions
                    )),
                TextColumn::make('last_seen_at')
                    ->label('Last Seen')
                    ->since()
                    ->sortable(),
                TextColumn::make('connection')
                    ->label('Connection')
                    ->state(function (ExamLiveSession $record): string {
                        if (! $record->last_seen_at) {
                            return 'Unknown';
                        }

                        return now()->diffInSeconds($record->last_seen_at) <= 15 ? 'Live' : 'Stale';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Live' ? 'success' : 'warning'),
            ])
            ->defaultSort('last_seen_at', 'desc');
    }

    public function getTitle(): string
    {
        return 'Monitor Exam: '.$this->exam->title;
    }
}
