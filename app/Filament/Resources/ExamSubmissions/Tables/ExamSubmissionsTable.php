<?php

namespace App\Filament\Resources\ExamSubmissions\Tables;

use App\Models\ExamSubmission;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
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
                    ->sortable(),
                TextColumn::make('exam.section.name')
                    ->label('Section')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('exam.title')
                    ->label('Exam')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('examPart.title')
                    ->label('Part')
                    ->searchable()
                    ->sortable(),
                TextInputColumn::make('score')
                    ->label('Score')
                    ->type('number')
                    ->sortable()
                    ->summarize(Sum::make()
                        ->label('Total Score')),
                SelectColumn::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_review' => 'Pending Review',
                        'graded' => 'Graded',
                    ])
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                Group::make('exam.section.name')
                    ->label('Section')
                    ->collapsible(),
                Group::make('status')
                    ->label('Status')
                    ->collapsible(),
                Group::make('user.name')
                    ->label('Student')
                    ->collapsible(),
                Group::make('exam.title')
                    ->label('Exam')
                    ->collapsible(),
            ])
            ->filters([
                SelectFilter::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name'),
                SelectFilter::make('exam_id')
                    ->label('Exam')
                    ->relationship('exam', 'title'),
            ])
            ->actions([
                EditAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->headerActions([
                Action::make('exportTotalScores')
                    ->label('Export Total Scores')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        // Get the current filtered query from the table
                        $query = $livewire->getFilteredTableQuery();

                        // We need to re-query with aggregation
                        // Note: toBase() is safer for aggregates in some cases,
                        // but we want the models for relationships.
                        $data = ExamSubmission::query()
                            ->whereIn('id', $query->pluck('id'))
                            ->select('user_id', 'exam_id', DB::raw('SUM(score) as total_score'))
                            ->groupBy('user_id', 'exam_id')
                            ->with(['user', 'exam'])
                            ->get();

                        $filename = 'exam_total_scores_'.now()->format('Y-m-d_H-i').'.csv';

                        return response()->streamDownload(function () {
                            $handle = fopen('php://memory', 'w');
                            fputcsv($handle, ['Student Name', 'Exam', 'Total Score']);

                            // Re-fetch data inside the stream for memory efficiency if it was larger,
                            // but here we already have it.
                            $data = ExamSubmission::query()
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
