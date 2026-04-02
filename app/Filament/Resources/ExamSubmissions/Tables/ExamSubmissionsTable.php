<?php

namespace App\Filament\Resources\ExamSubmissions\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class ExamSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('exam.title')
                    ->label('Exam')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('examPart.title')
                    ->label('Part')
                    ->searchable()
                    ->sortable(),
                \Filament\Tables\Columns\TextInputColumn::make('score')
                    ->label('Score')
                    ->type('number')
                    ->sortable()
                    ->summarize(\Filament\Tables\Columns\Summarizers\Sum::make()
                        ->label('Total Score')),
                \Filament\Tables\Columns\SelectColumn::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_review' => 'Pending Review',
                        'graded' => 'Graded',
                    ])
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->groups([
                \Filament\Tables\Grouping\Group::make('user.name')
                    ->label('Student')
                    ->collapsible(),
                \Filament\Tables\Grouping\Group::make('exam.title')
                    ->label('Exam')
                    ->collapsible(),
            ])
            ->defaultGroup('user.name')
            ->filters([
                \Filament\Tables\Filters\SelectFilter::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name'),
                \Filament\Tables\Filters\SelectFilter::make('exam_id')
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
                \Filament\Actions\Action::make('exportTotalScores')
                    ->label('Export Total Scores')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function ($livewire) {
                        // Get the current filtered query from the table
                        $query = $livewire->getFilteredTableQuery();
                        
                        // We need to re-query with aggregation
                        // Note: toBase() is safer for aggregates in some cases, 
                        // but we want the models for relationships.
                        $data = \App\Models\ExamSubmission::query()
                            ->whereIn('id', $query->pluck('id'))
                            ->select('user_id', 'exam_id', \Illuminate\Support\Facades\DB::raw('SUM(score) as total_score'))
                            ->groupBy('user_id', 'exam_id')
                            ->with(['user', 'exam'])
                            ->get();

                        $filename = 'exam_total_scores_' . now()->format('Y-m-d_H-i') . '.csv';
                        
                        return response()->streamDownload(function () {
                            $handle = fopen('php://memory', 'w');
                            fputcsv($handle, ['Student Name', 'Exam', 'Total Score']);
                            
                            // Re-fetch data inside the stream for memory efficiency if it was larger, 
                            // but here we already have it.
                            $data = \App\Models\ExamSubmission::query()
                                ->select('user_id', 'exam_id', \Illuminate\Support\Facades\DB::raw('SUM(score) as total_score'))
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
