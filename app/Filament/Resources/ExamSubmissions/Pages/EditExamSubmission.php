<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Services\ExamXpAwardService;
use App\Support\ExamPartAnswerLabels;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditExamSubmission extends EditRecord
{
    protected static string $resource = ExamSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ExamPartAnswerLabels::splitAnswerFieldsForForm($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ExamPartAnswerLabels::mergeAnswerFieldsForSave($data);
    }

    protected function afterSave(): void
    {
        if ($this->record->status !== 'graded') {
            return;
        }

        $this->record->loadMissing(['user', 'exam']);
        app(ExamXpAwardService::class)->awardIfEligible($this->record->user, $this->record->exam);
    }
}
