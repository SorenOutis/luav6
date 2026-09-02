<?php

namespace App\Filament\Resources\Exams\Tables;

use App\Filament\Support\WorkspaceTable;
use App\Models\Exam;
use App\Models\ExamSet;
use App\Services\ExamTemplateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                WorkspaceTable::column(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('section.name')
                    ->label('Section')
                    ->placeholder('All Sections')
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Starts')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ends')
                    ->dateTime()
                    ->placeholder('Open-ended')
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sets_count')
                    ->label('Sets')
                    ->counts('sets')
                    ->tooltip('Students are dealt a shuffled set on their first open'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'closed' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                WorkspaceTable::filter(),
                SelectFilter::make('section')
                    ->relationship('section', 'name'),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Edit exam'),
                    Action::make('exportQuestions')
                        ->label('Export Questions')
                        ->icon('heroicon-o-arrow-down-tray')
                        ->color('success')
                        ->action(function (Exam $record) {
                            $service = new ExamTemplateService;

                            if ($record->sets()->count() > 1) {
                                $zipPath = $service->exportZip($record);

                                return response()
                                    ->download($zipPath, Str::slug($record->title ?: 'exam').'-questions.zip')
                                    ->deleteFileAfterSend(true);
                            }

                            $set = $record->sets()->first();

                            if ($set === null) {
                                Notification::make()
                                    ->title('Nothing to export')
                                    ->body('This exam has no questions yet.')
                                    ->warning()
                                    ->send();

                                return null;
                            }

                            $csv = $service->exportCsv($record, $set);

                            return response()->streamDownload(
                                fn () => print ($csv),
                                Str::slug($set->title ?: 'set-'.$set->getKey()).'.csv',
                                ['Content-Type' => 'text/csv']
                            );
                        }),
                    Action::make('uploadQuestions')
                        ->label('Import Questions')
                        ->icon('heroicon-o-arrow-up-tray')
                        ->color('warning')
                        ->form([
                            // The CSV replaces the questions of one set only,
                            // so each set can be imported separately.
                            Select::make('exam_set_id')
                                ->label('Import into set')
                                ->options(fn (Exam $record): array => $record
                                    ->sets()
                                    ->orderBy('sort_order')
                                    ->pluck('title', 'id')
                                    ->all())
                                ->default(fn (Exam $record): ?int => $record->sets()->first()?->id)
                                ->visible(fn (Exam $record): bool => $record->sets()->count() > 1)
                                ->required(fn (Exam $record): bool => $record->sets()->count() > 1)
                                ->helperText('The CSV replaces every question in the chosen set.'),
                            FileUpload::make('questions_file')
                                ->label('Select CSV File')
                                ->required()
                                ->disk('local')
                                ->directory('temp-uploads')
                                ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain']),
                        ])
                        ->action(function (array $data, Exam $record) {
                            $file = Storage::disk('local')->path($data['questions_file']);
                            $set = ExamSet::query()
                                ->where('exam_id', $record->getKey())
                                ->whereKey((int) ($data['exam_set_id'] ?? 0))
                                ->first()
                                ?? ExamSet::ensureDefaultForExam($record->getKey());

                            (new ExamTemplateService)->uploadFromCsv($record, $file, $set);

                            Notification::make()
                                ->title('Questions imported successfully')
                                ->body("Imported into {$set->title}.")
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
