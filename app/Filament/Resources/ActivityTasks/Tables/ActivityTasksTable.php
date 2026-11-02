<?php

namespace App\Filament\Resources\ActivityTasks\Tables;

use App\Filament\Support\WorkspaceTable;
use App\Models\ActivityTask;
use App\Models\ActivityTaskScore;
use App\Models\Section;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ActivityTasksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                WorkspaceTable::column(),

                TextColumn::make('title')
                    ->label('Title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('task_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Performance Task' => 'primary',
                        default => 'warning',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('section.name')
                    ->label('Section')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('term')
                    ->label('Period / Term')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('max_points')
                    ->label('Max Pts')
                    ->numeric(2)
                    ->sortable(),

                TextColumn::make('scores_count')
                    ->label('Graded Students')
                    ->counts('scores')
                    ->badge()
                    ->color('success'),

                TextColumn::make('due_date')
                    ->label('Due Date')
                    ->date()
                    ->placeholder('—')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                WorkspaceTable::filter(),

                SelectFilter::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->searchable()
                    ->preload(),

                SelectFilter::make('term')
                    ->label('Period / Term')
                    ->options(fn () => ActivityTask::query()->whereNotNull('term')->distinct()->pluck('term', 'term')->all()),
            ])
            ->recordActions([
                Action::make('gradeStudents')
                    ->label('Grade Sheet')
                    ->icon('heroicon-o-clipboard-document-check')
                    ->color('success')
                    ->modalHeading(fn (ActivityTask $record) => "Grade Sheet: {$record->title} (Max {$record->max_points} pts)")
                    ->modalDescription(fn (ActivityTask $record) => "Section: {$record->section?->name} · {$record->term}")
                    ->modalSubmitActionLabel('Save All Scores')
                    ->mountUsing(function ($form, ActivityTask $record) {
                        $students = User::query()
                            ->whereHas('sections', fn ($q) => $q->where('sections.id', $record->section_id))
                            ->where('is_admin', false)
                            ->orderBy('name')
                            ->get();

                        $existingScores = ActivityTaskScore::where('activity_task_id', $record->id)
                            ->get()
                            ->keyBy('user_id');

                        $items = [];
                        foreach ($students as $student) {
                            $scoreRecord = $existingScores->get($student->id);
                            $items[] = [
                                'user_id' => $student->id,
                                'student_name' => $student->name,
                                'score' => $scoreRecord?->score,
                                'is_missed' => (bool) ($scoreRecord?->is_missed ?? false),
                                'remarks' => $scoreRecord?->remarks ?? '',
                            ];
                        }

                        $form->fill(['student_scores' => $items]);
                    })
                    ->form([
                        Repeater::make('student_scores')
                            ->label('Students')
                            ->addable(false)
                            ->deletable(false)
                            ->reorderable(false)
                            ->schema([
                                TextInput::make('student_name')
                                    ->label('Student')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->columnSpan(2),

                                TextInput::make('score')
                                    ->label('Score')
                                    ->numeric()
                                    ->minValue(0)
                                    ->columnSpan(1),

                                Toggle::make('is_missed')
                                    ->label('Missed')
                                    ->columnSpan(1),

                                TextInput::make('remarks')
                                    ->label('Remarks')
                                    ->placeholder('Optional notes')
                                    ->columnSpan(2),
                            ])
                            ->columns(6),
                    ])
                    ->action(function (array $data, ActivityTask $record) {
                        $scores = $data['student_scores'] ?? [];

                        foreach ($scores as $row) {
                            $userId = $row['user_id'] ?? null;
                            if (! $userId) {
                                continue;
                            }

                            $scoreVal = filled($row['score']) ? (float) $row['score'] : null;
                            $isMissed = (bool) ($row['is_missed'] ?? false);
                            $remarks = filled($row['remarks']) ? $row['remarks'] : null;

                            ActivityTaskScore::updateOrCreate(
                                [
                                    'activity_task_id' => $record->id,
                                    'user_id' => $userId,
                                ],
                                [
                                    'score' => $scoreVal,
                                    'is_missed' => $isMissed,
                                    'remarks' => $remarks,
                                    'graded_by' => auth()->id(),
                                ]
                            );
                        }

                        Notification::make()
                            ->title('Grades updated successfully.')
                            ->success()
                            ->send();
                    }),

                Action::make('exportGradeSheet')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function (ActivityTask $record) {
                        $section = $record->section;
                        $schoolLevel = $section?->school_level ? (Section::schoolLevelOptions()[$section->school_level] ?? $section->school_level) : 'College';
                        $students = $section
                            ? User::query()
                                ->whereHas('sections', fn ($q) => $q->where('sections.id', $section->id))
                                ->where('is_admin', false)
                                ->orderBy('name')
                                ->get()
                            : collect();

                        $scores = ActivityTaskScore::where('activity_task_id', $record->id)
                            ->get()
                            ->keyBy('user_id');

                        $slug = Str::slug($record->title) ?: 'task';
                        $filename = "grade_sheet_{$slug}_".now()->format('Y-m-d').'.csv';

                        return response()->streamDownload(function () use ($record, $section, $schoolLevel, $students, $scores) {
                            $handle = fopen('php://memory', 'w');
                            fwrite($handle, "\xEF\xBB\xBF");

                            fputcsv($handle, ['GRADE SHEET / ACTIVITY TASK RECORD']);
                            fputcsv($handle, ['Task Title', $record->title]);
                            fputcsv($handle, ['Section', $section?->name ?? 'Unassigned']);
                            fputcsv($handle, ['School Level', $schoolLevel]);
                            fputcsv($handle, ['Period / Term', $record->term ?? '—']);
                            fputcsv($handle, ['Activity Type', $record->task_type ?? '—']);
                            fputcsv($handle, ['Max Points', $record->max_points]);
                            fputcsv($handle, ['Date Exported', now()->toDateTimeString()]);
                            fputcsv($handle, []);

                            fputcsv($handle, [
                                '#',
                                'Student Name',
                                'Student Email',
                                'Score',
                                'Max Points',
                                'Rating (%)',
                                'Status',
                                'Remarks',
                            ]);

                            $index = 1;
                            foreach ($students as $student) {
                                $scoreRecord = $scores->get($student->id);
                                $score = '—';
                                $rating = '—';
                                $status = 'Unrecorded';
                                $remarks = '';

                                if ($scoreRecord) {
                                    $remarks = $scoreRecord->remarks ?? '';
                                    if ($scoreRecord->is_missed) {
                                        $score = 0;
                                        $rating = '0.0%';
                                        $status = 'Missed';
                                    } elseif ($scoreRecord->score !== null) {
                                        $score = (float) $scoreRecord->score;
                                        $status = 'Graded';
                                        if ((float) $record->max_points > 0) {
                                            $rating = number_format(($score / (float) $record->max_points) * 100, 1).'%';
                                        }
                                    }
                                }

                                fputcsv($handle, [
                                    $index++,
                                    $student->name,
                                    $student->email,
                                    $score,
                                    $record->max_points,
                                    $rating,
                                    $status,
                                    $remarks,
                                ]);
                            }

                            rewind($handle);
                            fpassthru($handle);
                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),

                EditAction::make(),
                DeleteAction::make(),
            ])
            ->headerActions([
                Action::make('exportScores')
                    ->label('Export Scores')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function ($livewire) {
                        $query = $livewire->getFilteredTableQuery();
                        $tasks = (clone $query)->with([
                            'section.users' => fn ($q) => $q->where('is_admin', false)->orderBy('name'),
                            'scores',
                        ])->get();

                        if ($tasks->isEmpty()) {
                            Notification::make()
                                ->title('Nothing to export')
                                ->warning()
                                ->send();

                            return null;
                        }

                        $filename = 'activity_tasks_scores_'.now()->format('Y-m-d_H-i').'.csv';

                        return response()->streamDownload(function () use ($tasks) {
                            $handle = fopen('php://memory', 'w');
                            fwrite($handle, "\xEF\xBB\xBF");

                            fputcsv($handle, [
                                'Section',
                                'School Level',
                                'Period / Term',
                                'Activity Type',
                                'Task Title',
                                'Max Points',
                                'Student Name',
                                'Student Email',
                                'Score',
                                'Rating (%)',
                                'Status',
                                'Remarks',
                            ]);

                            foreach ($tasks as $task) {
                                $section = $task->section;
                                $schoolLevel = $section?->school_level ? (Section::schoolLevelOptions()[$section->school_level] ?? $section->school_level) : 'College';
                                $students = $section ? $section->users : collect();
                                $scores = $task->scores->keyBy('user_id');

                                if ($students->isEmpty()) {
                                    fputcsv($handle, [
                                        $section?->name ?? 'Unassigned',
                                        $schoolLevel,
                                        $task->term ?? '—',
                                        $task->task_type ?? '—',
                                        $task->title,
                                        $task->max_points,
                                        '—',
                                        '—',
                                        '—',
                                        '—',
                                        'No enrolled students',
                                        '',
                                    ]);

                                    continue;
                                }

                                foreach ($students as $student) {
                                    $scoreRecord = $scores->get($student->id);
                                    $score = '—';
                                    $rating = '—';
                                    $status = 'Unrecorded';
                                    $remarks = '';

                                    if ($scoreRecord) {
                                        $remarks = $scoreRecord->remarks ?? '';
                                        if ($scoreRecord->is_missed) {
                                            $score = 0;
                                            $rating = '0.0%';
                                            $status = 'Missed';
                                        } elseif ($scoreRecord->score !== null) {
                                            $score = (float) $scoreRecord->score;
                                            $status = 'Graded';
                                            if ((float) $task->max_points > 0) {
                                                $rating = number_format(($score / (float) $task->max_points) * 100, 1).'%';
                                            }
                                        }
                                    }

                                    fputcsv($handle, [
                                        $section?->name ?? 'Unassigned',
                                        $schoolLevel,
                                        $task->term ?? '—',
                                        $task->task_type ?? '—',
                                        $task->title,
                                        $task->max_points,
                                        $student->name,
                                        $student->email,
                                        $score,
                                        $rating,
                                        $status,
                                        $remarks,
                                    ]);
                                }
                            }

                            rewind($handle);
                            fpassthru($handle);
                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
