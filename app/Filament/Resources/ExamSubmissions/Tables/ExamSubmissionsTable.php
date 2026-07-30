<?php

namespace App\Filament\Resources\ExamSubmissions\Tables;

use App\Models\ExamSubmission;
use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Support\Facades\DB;

class ExamSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('exam.title')
                    ->label('Exam')
                    ->searchable()
                    ->sortable()
                    ->weight('medium'),
                TextColumn::make('exam.section.name')
                    ->label('Section')
                    ->placeholder('No section')
                    ->sortable(),
                TextColumn::make('examPart.title')
                    ->label('Part')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('score')
                    ->label('Score')
                    ->numeric()
                    ->sortable()
                    ->suffix(' pts')
                    ->summarize(Sum::make()->label('Total')),
                TextColumn::make('status')
                    ->label('Grading status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'graded' => 'success',
                        'pending_ai', 'pending_review' => 'warning',
                        'grading_failed' => 'danger',
                        'submitted' => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                Group::make('user.name')
                    ->label('Student'),
                Group::make('exam.title')
                    ->label('Exam'),
            ])
            ->defaultGroup('user.name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_ai' => 'Pending AI',
                        'pending_review' => 'Pending Review',
                        'grading_failed' => 'Grading failed',
                        'graded' => 'Graded',
                    ]),
                SelectFilter::make('exam_id')
                    ->label('Exam')
                    ->relationship('exam', 'title'),
                SelectFilter::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name'),
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->options(fn (): array => Section::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(function ($query, array $data) {
                        return $query->when(
                            $data['value'] ?? null,
                            fn ($submissionQuery, $sectionId) => $submissionQuery->whereHas(
                                'exam',
                                fn ($examQuery) => $examQuery->where('section_id', $sectionId),
                            ),
                        );
                    }),
            ])
            ->filtersFormColumns(3)
            ->persistFiltersInSession()
            ->striped()
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->emptyStateHeading('No exam submissions found')
            ->emptyStateDescription('Adjust the filters or wait for a student submission to arrive.')
            ->actions([
                EditAction::make()->label('Review submission'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Action::make('exportTotalScores')
                    ->label('Export Scores')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->action(function ($livewire) {
                        // Get the current filtered query from the table
                        $query = $livewire->getFilteredTableQuery();
                        $submissionIds = $query->pluck('id')->toArray();

                        $filename = 'exam_total_scores_'.now()->format('Y-m-d_H-i').'.csv';

                        return response()->streamDownload(function () use ($submissionIds) {
                            $handle = fopen('php://memory', 'w');
                            fputcsv($handle, ['Student Name', 'Exam', 'Total Score']);

                            // Fetch aggregated data based on the filtered submission IDs
                            $data = ExamSubmission::query()
                                ->whereIn('id', $submissionIds)
                                ->select('user_id', 'exam_id', DB::raw('SUM(score) as total_score'))
                                ->groupBy('user_id', 'exam_id')
                                ->with(['user', 'exam'])
                                ->get();

                            foreach ($data as $row) {
                                fputcsv($handle, [
                                    $row->user?->name ?? 'Unknown',
                                    $row->exam?->title ?? 'Unknown',
                                    $row->total_score,
                                ]);
                            }

                            rewind($handle);
                            fpassthru($handle);
                            fclose($handle);
                        }, $filename, ['Content-Type' => 'text/csv']);
                    }),
            ]);
    }
}
