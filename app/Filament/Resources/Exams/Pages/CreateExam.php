<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Exams\Schemas\ExamForm;
use App\Models\Exam;
use App\Services\ExamBlockService;
use Filament\Resources\Pages\CreateRecord;

class CreateExam extends CreateRecord
{
    protected static string $resource = ExamResource::class;

    /**
     * Students picked in the "Blocked Students" section.
     *
     * The block list lives in a pivot, so it cannot be written while the form
     * data is still being mutated — the exam has no id yet. Null means the
     * picker was not part of this save, in which case nothing is written.
     *
     * @var array<int, int>|null
     */
    private ?array $blockedUserIds = null;

    /**
     * "Number of sets" is a form-only control: the requested number of set
     * rows is materialised here, and the field itself never reaches the model.
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = ExamForm::syncSetCount($data);

        $this->blockedUserIds = ExamForm::extractBlockedUserIds($data);

        // The legacy `exam_date` column is kept as an alias of starts_at so the
        // calendar, ordering and AI tools that still read it keep working.
        if (! empty($data['starts_at']) && empty($data['exam_date'])) {
            $data['exam_date'] = $data['starts_at'];
        }

        return $data;
    }

    /**
     * Bar the chosen students now that the exam exists.
     */
    protected function afterCreate(): void
    {
        if ($this->blockedUserIds === null) {
            return;
        }

        /** @var Exam $record */
        $record = $this->getRecord();

        app(ExamBlockService::class)->sync($record, $this->blockedUserIds);
    }
}
