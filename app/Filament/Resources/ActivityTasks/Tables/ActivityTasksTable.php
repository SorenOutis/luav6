<?php

namespace App\Filament\Resources\ActivityTasks\Tables;

use App\Filament\Support\WorkspaceTable;
use App\Models\ActivityTask;
use App\Models\ActivityTaskScore;
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
