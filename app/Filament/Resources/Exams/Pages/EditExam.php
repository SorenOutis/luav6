<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

            \Filament\Actions\Action::make('uploadQuestions')
                ->label('Upload Questions')
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
                ->action(function (array $data) {
                    $file = \Illuminate\Support\Facades\Storage::disk('local')->path($data['questions_file']);
                    (new \App\Services\ExamTemplateService())->uploadFromCsv($this->record, $file);
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Questions uploaded successfully')
                        ->success()
                        ->send();

                    $this->refreshFormData(['parts']);
                }),

            DeleteAction::make(),
        ];
    }
}
