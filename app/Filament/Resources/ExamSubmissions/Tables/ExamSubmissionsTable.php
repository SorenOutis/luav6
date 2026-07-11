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
                SelectColumn::make('status')
                    ->label('Status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_ai' => 'Pending AI',
                        'pending_review' => 'Pending Review',
                        'graded' => 'Graded',
                    ])
                    ->sortable()
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'fi-ta-header-cell w-40']),
                TextInputColumn::make('score')
                    ->label('Score')
                    ->type('number')
                    ->sortable()
                    ->summarize(Sum::make()
                        ->label('Total Score')),
                TextColumn::make('exam.title')
                    ->label('Exam')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ExamSubmission $record): string => $record->exam?->section?->name ?? ''),
                TextColumn::make('examPart.title')
                    ->label('Part')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('exam.section.name')
                    ->label('Section')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->groups([
                Group::make('exam.title')
                    ->label('Exam')
                    ->collapsible(),
                Group::make('status')
                    ->label('Status')
                    ->collapsible(),
                Group::make('exam.section.name')
                    ->label('Section')
                    ->collapsible(),
                Group::make('user.name')
                    ->label('Student')
                    ->collapsible(),
            ])
            ->defaultGroup('user.name')
            ->filters([
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_ai' => 'Pending AI',
                        'pending_review' => 'Pending Review',
                        'graded' => 'Graded',
                    ]),
                SelectFilter::make('exam_id')
                    ->label('Exam')
                    ->relationship('exam', 'title'),
                SelectFilter::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name'),
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
