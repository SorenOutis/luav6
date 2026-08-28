<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Exams\Schemas\ExamForm;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    /**
     * "Number of sets" is a form-only control: the requested number of set
     * rows is materialised here, and the field itself never reaches the model.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return ExamForm::syncSetCount($data);
    }
}
