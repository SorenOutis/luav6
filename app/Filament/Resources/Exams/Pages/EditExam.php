<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Exams\Schemas\ExamForm;
use App\Models\ExamSet;
use App\Services\ExamAnswerReportService;
use App\Services\ExamTemplateService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    /** @var array<int, string>|null Memoised so one render doesn't re-query per field. */
    private ?array $studentOptions = null;

    /**
     * Growing or shrinking the number of sets is done here, from the repeater's
     * actual items, so the counter can never remove a set that is on screen.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return ExamForm::syncSetCount($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->viewAnswerAction(),

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
                    // Importing replaces the questions of ONE set, so a
                    // multi-set exam can be filled set by set.
                    Select::make('exam_set_id')
                        ->label('Import into set')
                        ->options(fn (): array => $this->record
                            ->sets()
                            ->orderBy('sort_order')
                            ->pluck('title', 'id')
                            ->all())
                        ->default(fn (): ?int => $this->record->sets()->first()?->id)
                        ->visible(fn (): bool => $this->record->sets()->count() > 1)
                        ->required(fn (): bool => $this->record->sets()->count() > 1)
                        ->helperText('The CSV replaces every question in the chosen set.'),
                    FileUpload::make('questions_file')
                        ->label('Select CSV File')
                        ->required()
                        ->disk('local')
                        ->directory('temp-uploads')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain']),
                ])
                ->action(function (array $data) {
                    $file = Storage::disk('local')->path($data['questions_file']);
                    $set = $this->resolveTargetSet($data['exam_set_id'] ?? null);
                    (new ExamTemplateService)->uploadFromCsv($this->record, $file, $set);

                    Notification::make()
                        ->title('Questions uploaded successfully')
                        ->body("Imported into {$set->title}.")
                        ->success()
                        ->send();

                    $this->refreshFormData(['sets']);
                }),

            DeleteAction::make(),
        ];
    }

    /**
     * Opens a printable report (questions, answers, correct/wrong marks and the
     * overall score) that the browser saves as a PDF.
     */
    private function viewAnswerAction(): Action
    {
        return Action::make('viewAnswer')
            ->label('View Answer')
            ->icon('heroicon-o-document-check')
            ->color('success')
            ->modalHeading('View answers as PDF')
            ->modalDescription('Choose what the report should contain. It opens as a print-ready page — pick “Save as PDF” in the print dialog.')
            ->modalSubmitActionLabel('Open report')
            ->form([
                Radio::make('scope')
                    ->label('Report contents')
                    ->options([
                        'answer_key' => 'Answer key only (questions + correct answers)',
                        'single' => 'One student (answers, correct/wrong, score)',
                        'selected' => 'Selected students',
                        'all' => 'All students who submitted',
                    ])
                    ->default('answer_key')
                    ->required()
                    ->live(),

                Select::make('set')
                    ->label('Exam set')
                    ->options(fn (): array => $this->record
                        ->sets()
                        ->orderBy('sort_order')
                        ->pluck('title', 'id')
                        ->all())
                    ->placeholder('All sets')
                    ->visible(fn (): bool => $this->record->sets()->count() > 1)
                    ->helperText('Each student only ever answers one set, so a per-set report keeps the answer key and the marks aligned.'),

                Select::make('student_id')
                    ->label('Student')
                    ->options(fn (): array => $this->studentOptions())
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->visible(fn (callable $get): bool => $get('scope') === 'single')
                    ->helperText(fn (): ?string => $this->studentOptions() === []
                        ? 'No student has submitted this exam yet.'
                        : null),

                CheckboxList::make('student_ids')
                    ->label('Students')
                    ->options(fn (): array => $this->studentOptions())
                    ->columns(2)
                    ->searchable()
                    ->bulkToggleable()
                    ->required()
                    ->visible(fn (callable $get): bool => $get('scope') === 'selected'),

                Toggle::make('include_key')
                    ->label('Include the answer key section')
                    ->helperText('Adds the full question list with correct answers before the student reports.')
                    ->default(true)
                    ->visible(fn (callable $get): bool => $get('scope') !== 'answer_key'),
            ])
            ->action(function (array $data) {
                $scope = $data['scope'] ?? 'answer_key';

                $students = match ($scope) {
                    'single' => array_filter([(int) ($data['student_id'] ?? 0)]),
                    'selected' => array_map('intval', $data['student_ids'] ?? []),
                    default => [],
                };

                if (in_array($scope, ['single', 'selected'], true) && $students === []) {
                    Notification::make()
                        ->title('Select at least one student')
                        ->danger()
                        ->send();

                    return null;
                }

                if ($scope === 'all' && $this->studentOptions() === []) {
                    Notification::make()
                        ->title('No submissions yet')
                        ->body('Nobody has submitted this exam, so there is nothing to grade.')
                        ->warning()
                        ->send();

                    return null;
                }

                $url = route('admin.exams.answer-report', array_filter([
                    'exam' => $this->record->getKey(),
                    'mode' => $scope === 'answer_key'
                        ? ExamAnswerReportService::MODE_KEY
                        : ExamAnswerReportService::MODE_STUDENTS,
                    'students' => $students === [] ? null : $students,
                    'include_key' => $scope === 'answer_key' || ($data['include_key'] ?? true) ? 1 : 0,
                    'set' => filled($data['set'] ?? null) ? (int) $data['set'] : null,
                ], fn ($value): bool => $value !== null));

                // Open in a new tab so the admin keeps this page. If the browser
                // blocks the popup, window.open() returns null and we navigate
                // the current tab instead — the report is never lost. Written as
                // a single expression because Livewire evaluates js() via Alpine.
                $encodedUrl = json_encode($url);
                $this->js("window.open({$encodedUrl}, '_blank') || (window.location.href = {$encodedUrl})");

                return null;
            });
    }

    /**
     * The set an upload should land in: the one the admin picked, else the
     * exam's first set (created on demand for exams predating sets).
     */
    private function resolveTargetSet(mixed $setId): ExamSet
    {
        $setId = (int) ($setId ?? 0);

        if ($setId > 0) {
            $set = $this->record->sets()->whereKey($setId)->first();
        }

        return $set ?? ExamSet::ensureDefaultForExam($this->record->getKey());
    }

    /**
     * @return array<int, string>
     */
    private function studentOptions(): array
    {
        return $this->studentOptions ??= app(ExamAnswerReportService::class)->studentOptions($this->record);
    }
}
