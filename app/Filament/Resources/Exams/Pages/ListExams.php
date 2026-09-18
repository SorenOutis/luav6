<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use App\Services\ExamTemplateService;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListExams extends ListRecords
{
    protected static string $resource = ExamResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            ActionGroup::make([
                Action::make('downloadTemplate')
                    ->label('Download CSV template')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->action(function () {
                        $csv = (new ExamTemplateService)->getTemplateCsv();

                        return response()->streamDownload(
                            fn () => print ($csv),
                            'exam-template.csv'
                        );
                    }),
            ])
                ->label('More actions')
                ->icon('heroicon-o-ellipsis-horizontal'),
        ];
    }

    public function getSubheading(): ?string
    {
        return 'Create assessments, import questions, and manage publishing status.';
    }
}
