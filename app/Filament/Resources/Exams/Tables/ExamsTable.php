<?php

namespace App\Filament\Resources\Exams\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class ExamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                \Filament\Tables\Columns\TextColumn::make('title')
                    ->searchable(),
                \Filament\Tables\Columns\TextColumn::make('exam_date')
                    ->dateTime()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('duration_minutes')
                    ->numeric()
                    ->sortable(),
                \Filament\Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'closed' => 'danger',
                        default => 'secondary',
                    }),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                \Filament\Actions\Action::make('uploadQuestions')
                    ->label('Import Questions')
                    ->icon('heroicon-o-arrow-up-tray')
                    ->color('warning')
                    ->form([
                        \Filament\Forms\Components\FileUpload::make('questions_file')
                            ->label('Select CSV File')
                            ->required()
                            ->disk('local')
                            ->directory('temp-uploads')
                            ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain']),
                    ])
                    ->action(function (array $data, \App\Models\Exam $record) {
                        $file = \Illuminate\Support\Facades\Storage::disk('local')->path($data['questions_file']);
                        (new \App\Services\ExamTemplateService())->uploadFromCsv($record, $file);
                        
                        \Filament\Notifications\Notification::make()
                            ->title('Questions imported successfully')
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                \Filament\Actions\Action::make('downloadTemplate')
                    ->label('Download Template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->action(function () {
                        $csv = (new \App\Services\ExamTemplateService())->getTemplateCsv();
                        return response()->streamDownload(
                            fn () => print($csv),
                            'exam-template.csv'
                        );
                    }),
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
