<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use App\Services\ExamTemplateService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
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

            Action::make('uploadQuestions')
                ->label('Upload Questions')
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
                ->action(function (array $data) {
                    $file = Storage::disk('local')->path($data['questions_file']);
                    (new ExamTemplateService)->uploadFromCsv($this->record, $file);

                    Notification::make()
                        ->title('Questions uploaded successfully')
                        ->success()
                        ->send();

                    $this->refreshFormData(['parts']);
                }),

            DeleteAction::make(),
        ];
    }
}
