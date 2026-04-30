<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Models\Exam;
use App\Models\ExamLiveSession;
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

    public function mount(Exam $exam): void
    {
        $this->exam = $exam;
        $this->totalParts = max(1, $exam->parts()->count());
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
                TextColumn::make('aggregate_progress')
                    ->label('Aggregate Progress')
                    ->state(fn (ExamLiveSession $record): string => sprintf(
                        '%d/%d parts | %d/%d questions',
                        $record->submitted_parts_count,
                        $this->totalParts,
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
