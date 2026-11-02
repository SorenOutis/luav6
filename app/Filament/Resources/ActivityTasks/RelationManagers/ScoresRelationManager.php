<?php

namespace App\Filament\Resources\ActivityTasks\RelationManagers;

use App\Models\ActivityTask;
use App\Models\ActivityTaskScore;
use App\Models\Section;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class ScoresRelationManager extends RelationManager
{
    protected static string $relationship = 'scores';

    protected static ?string $recordTitleAttribute = 'student.name';

    protected static ?string $title = 'Student Scores';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Student')
                    ->options(function () {
                        /** @var ActivityTask $task */
                        $task = $this->getOwnerRecord();

                        return User::query()
                            ->whereHas('sections', fn ($q) => $q->where('sections.id', $task->section_id))
                            ->where('is_admin', false)
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('score')
                    ->label('Score')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->helperText(fn () => "Maximum points: {$this->getOwnerRecord()->max_points}"),

                Toggle::make('is_missed')
                    ->label('Missed Activity')
                    ->helperText('Check if student was absent or failed to submit this performance task.')
                    ->default(false),

                Textarea::make('remarks')
                    ->label('Remarks / Feedback')
                    ->rows(2)
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        /** @var ActivityTask $task */
        $task = $this->getOwnerRecord();

        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('student.email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('score')
                    ->label('Score')
                    ->formatStateUsing(function ($state, ActivityTaskScore $record) use ($task) {
                        if ($record->is_missed) {
                            return "0 / {$task->max_points} (Missed)";
                        }

                        return $state !== null ? "{$state} / {$task->max_points}" : '—';
                    })
                    ->badge()
                    ->color(fn (ActivityTaskScore $record) => $record->is_missed ? 'danger' : ($record->score !== null ? 'success' : 'gray')),

                TextColumn::make('percentage')
                    ->label('Rating')
                    ->state(fn (ActivityTaskScore $record) => $record->percentage())
                    ->formatStateUsing(fn ($state) => $state !== null ? "{$state}%" : '—')
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === null => 'gray',
                        $state >= 90 => 'success',
                        $state >= 75 => 'primary',
                        default => 'danger',
                    }),

                IconColumn::make('is_missed')
                    ->label('Missed')
                    ->boolean(),

                TextColumn::make('remarks')
                    ->label('Remarks')
                    ->placeholder('—')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->headerActions([
                Action::make('exportScores')
                    ->label('Export Excel')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('success')
                    ->action(function () use ($task) {
                        $section = $task->section;
                        $schoolLevel = $section?->school_level ? (Section::schoolLevelOptions()[$section->school_level] ?? $section->school_level) : 'College';
                        $students = $section
                            ? User::query()
                                ->whereHas('sections', fn ($q) => $q->where('sections.id', $section->id))
                                ->where('is_admin', false)
                                ->orderBy('name')
                                ->get()
                            : collect();

                        $scores = ActivityTaskScore::where('activity_task_id', $task->id)
                            ->get()
                            ->keyBy('user_id');

                        $slug = Str::slug($task->title) ?: 'task';
                        $filename = "grade_sheet_{$slug}_".now()->format('Y-m-d').'.csv';

                        return response()->streamDownload(function () use ($task, $section, $schoolLevel, $students, $scores) {
                            $handle = fopen('php://memory', 'w');
                            fwrite($handle, "\xEF\xBB\xBF");

                            fputcsv($handle, ['GRADE SHEET / ACTIVITY TASK RECORD']);
                            fputcsv($handle, ['Task Title', $task->title]);
                            fputcsv($handle, ['Section', $section?->name ?? 'Unassigned']);
                            fputcsv($handle, ['School Level', $schoolLevel]);
                            fputcsv($handle, ['Period / Term', $task->term ?? '—']);
                            fputcsv($handle, ['Activity Type', $task->task_type ?? '—']);
                            fputcsv($handle, ['Max Points', $task->max_points]);
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
                                        if ((float) $task->max_points > 0) {
                                            $rating = number_format(($score / (float) $task->max_points) * 100, 1).'%';
                                        }
                                    }
                                }

                                fputcsv($handle, [
                                    $index++,
                                    $student->name,
                                    $student->email,
                                    $score,
                                    $task->max_points,
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

                Action::make('populateSectionStudents')
                    ->label('Add All Section Students')
                    ->icon('heroicon-o-user-group')
                    ->color('primary')
                    ->requiresConfirmation()
                    ->modalHeading('Populate Section Roster?')
                    ->modalDescription('This will create empty score records for all enrolled students in this section who are not yet on the score sheet.')
                    ->action(function () use ($task) {
                        $students = User::query()
                            ->whereHas('sections', fn ($q) => $q->where('sections.id', $task->section_id))
                            ->where('is_admin', false)
                            ->get();

                        $added = 0;
                        foreach ($students as $student) {
                            $exists = ActivityTaskScore::where('activity_task_id', $task->id)
                                ->where('user_id', $student->id)
                                ->exists();

                            if (! $exists) {
                                ActivityTaskScore::create([
                                    'activity_task_id' => $task->id,
                                    'user_id' => $student->id,
                                    'score' => null,
                                    'is_missed' => false,
                                    'graded_by' => auth()->id(),
                                ]);
                                $added++;
                            }
                        }

                        Notification::make()
                            ->title("Added {$added} students to score sheet.")
                            ->success()
                            ->send();
                    }),

                CreateAction::make()
                    ->label('Add Student Score'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
