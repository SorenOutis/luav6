<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Support\ExamPartAnswerLabels;
use Filament\Resources\Pages\CreateRecord;

class CreateExamSubmission extends CreateRecord
{
    protected static string $resource = ExamSubmissionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ExamPartAnswerLabels::mergeAnswerFieldsForSave($data);
    }
}
