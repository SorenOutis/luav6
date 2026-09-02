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
        $data = ExamForm::syncSetCount($data);

        // The legacy `exam_date` column is kept as an alias of starts_at so the
        // calendar, ordering and AI tools that still read it keep working.
        if (! empty($data['starts_at']) && empty($data['exam_date'])) {
            $data['exam_date'] = $data['starts_at'];
        }

        return $data;
    }
}
