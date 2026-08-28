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
                TextColumn::make('exam_date')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('duration_minutes')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('sets_count')
                    ->label('Sets')
                    ->counts('sets')
                    ->tooltip('Students are rotated through the sets: Set A, Set B, …'),
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
