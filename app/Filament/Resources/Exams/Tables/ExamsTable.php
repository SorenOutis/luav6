<?php

namespace App\Filament\Resources\Exams\Tables;

use App\Models\Exam;
use App\Services\ExamTemplateService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
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
                SelectFilter::make('section')
                    ->relationship('section', 'name'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('uploadQuestions')
                    ->label('Import Questions')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->form([
                        FileUpload::make('questions_file')
                            ->label('Select CSV File')
                            ->required()
                            ->disk('local')
                            ->directory('temp-uploads')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain']),
                    ])
                    ->action(function (array $data, Exam $record) {
                        $file = Storage::disk('local')->path($data['questions_file']);
                        (new ExamTemplateService)->uploadFromCsv($record, $file);

                        Notification::make()
                            ->title('Questions imported successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                Action::make('downloadTemplate')
                    ->label('Download Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        $csv = (new ExamTemplateService)->getTemplateCsv();

                        return response()->streamDownload(
                            fn () => print ($csv),
                            'exam-template.csv'
                        );
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
