<?php

namespace App\Filament\Resources\Exams\Pages;

use App\Filament\Resources\Exams\ExamResource;
use App\Filament\Resources\Exams\Schemas\ExamForm;
use App\Models\Exam;
use App\Models\ExamSet;
use App\Services\ExamAnswerReportService;
use App\Services\ExamBlockService;
use App\Services\ExamSetAssignmentService;
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
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class EditExam extends EditRecord
{
    protected static string $resource = ExamResource::class;

    /** @var array<string, array<int, string>> Memoised per set so one render doesn't re-query per field. */
    private array $studentOptions = [];

    /**
     * Students picked in the "Blocked Students" section.
     *
     * The block list is a pivot, not a column, so it is written in
     * afterSave(). Null means this save carried no picker — a header action or
     * a partial submit must never wipe the existing blocks.
     *
     * @var array<int, int>|null
     */
    private ?array $blockedUserIds = null;

    /**
     * Seed the "Blocked Students" picker from the pivot.
     *
     * The form is filled from the record's *attributes*, and the block list is
     * a pivot rather than a column, so without this the picker would come up
     * empty on every edit — making saved blocks look like they had vanished.
     * A component default does not help: filling with an explicit data array
     * bypasses defaults.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Exam $record */
        $record = $this->getRecord();

        $data['blocked_user_ids'] = app(ExamBlockService::class)->blockedUserIds($record);

        return $data;
    }

    /**
     * Growing or shrinking the number of sets is done here, from the repeater's
     * actual items, so the counter can never remove a set that is on screen.
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data = ExamForm::syncSetCount($data);

        $this->blockedUserIds = ExamForm::extractBlockedUserIds($data);

        // Keep the legacy alias in sync whenever the schedule changes; the
        // form intentionally exposes starts_at/ends_at instead of exam_date.
        if (! empty($data['starts_at'])) {
            $data['exam_date'] = $data['starts_at'];
        }

        return $data;
    }

    /**
     * Apply the block list, then warn about sets that hold no questions.
     *
     * Empty sets are never dealt to a student (the deck only draws from sets
     * that have questions), so an exam that looks like it ships four versions
     * would silently hand out only the ones that were actually filled in.
     */
    protected function afterSave(): void
    {
        if ($this->blockedUserIds !== null) {
            /** @var Exam $record */
            $record = $this->getRecord();

            app(ExamBlockService::class)->sync($record, $this->blockedUserIds);
        }

        $empty = $this->record
            ->sets()
            ->whereDoesntHave('parts')
            ->pluck('title');

        if ($empty->isEmpty() || $this->record->sets()->count() < 2) {
            return;
        }

        Notification::make()
            ->title('Some sets have no questions yet')
            ->body($empty->implode(', ').' will not be handed to any student until you add or import questions.')
            ->warning()
            ->persistent()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->viewAnswerAction(),

            $this->reshuffleSetsAction(),

            $this->exportQuestionsAction(),

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
     * Re-deal the sets to every student who has not answered anything yet.
     *
     * A student is handed a set the first time they *open* the exam, which is
     * often days before they answer — so a class that browsed the exam while it
     * still had one set stays on that set even after more sets are added. This
     * releases those untouched hand-outs; students with a submission, a saved
     * draft or a running timer keep their set.
     */
    private function reshuffleSetsAction(): Action
    {
        return Action::make('reshuffleSets')
            ->label('Re-shuffle Sets')
            ->icon('heroicon-o-arrows-right-left')
            ->color('gray')
            ->visible(fn (): bool => $this->record->sets()->count() > 1)
            ->requiresConfirmation()
            ->modalHeading('Re-shuffle exam sets')
            ->modalDescription('Students who have not started this exam are handed a fresh set from the shuffled deck. Anyone who already answered, saved a draft or started a timer keeps the set they are working on.')
            ->modalSubmitActionLabel('Re-shuffle')
            ->action(function (): void {
                $service = app(ExamSetAssignmentService::class);
                $moved = $service->redealUnstarted($this->record);
                $deck = $service->dealOrder($this->record->fresh());

                Notification::make()
                    ->title($moved === 0 ? 'Nothing to re-shuffle' : "{$moved} student(s) will be re-dealt")
                    ->body('Deal order: '.$deck->pluck('title')->implode(' → '))
                    ->success()
                    ->send();
            });
    }

    /**
     * Downloads the exam's questions exactly as they are right now — including
     * every edit made on this page — in the same CSV format "Upload Questions"
     * accepts, so the file can be re-imported (or backed up) as-is.
     *
     * A single-set exam downloads immediately as one CSV. A multi-set exam asks
     * which set to export; "All sets" packages one CSV per set into a ZIP.
     */
    private function exportQuestionsAction(): Action
    {
        $action = Action::make('exportQuestions')
            ->label('Export Questions')
            ->icon('heroicon-o-arrow-down-tray')
            ->color('success');

        if ($this->record->sets()->count() > 1) {
            $action
                ->form([
                    Select::make('set')
                        ->label('Export set')
                        ->options(fn (): array => $this->exportSetOptions())
                        ->default('all')
                        ->required()
                        ->helperText('“All sets” packages every set into one ZIP (one CSV per set). Pick a single set to download just that CSV.'),
                ])
                ->action(function (array $data) {
                    return $this->streamExport((string) ($data['set'] ?? 'all'));
                });
        } else {
            $action->action(fn () => $this->streamExport('all'));
        }

        return $action;
    }

    /**
     * The sets an export can target, with "All sets (ZIP)" offered first when
     * the exam has more than one.
     *
     * @return array<string, string>
     */
    private function exportSetOptions(): array
    {
        $sets = $this->record
            ->sets()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('title', 'id')
            ->all();

        return ['all' => 'All sets (ZIP)'] + $sets;
    }

    /**
     * Streams the export the admin asked for. Returns the download response, or
     * null after showing a warning when there is nothing to export.
     *
     * @return mixed A StreamedResponse (CSV), a BinaryFileResponse (ZIP), or null.
     */
    private function streamExport(string $choice)
    {
        $service = app(ExamTemplateService::class);
        $exam = $this->record;

        if ($choice === 'all' && $exam->sets()->count() > 1) {
            $zipPath = $service->exportZip($exam);

            return response()
                ->download($zipPath, Str::slug($exam->title ?: 'exam').'-questions.zip')
                ->deleteFileAfterSend(true);
        }

        $set = $exam->sets()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->when($choice !== 'all', fn ($query) => $query->whereKey((int) $choice))
            ->first();

        if ($set === null) {
            Notification::make()
                ->title('Nothing to export')
                ->body('This exam has no questions yet.')
                ->warning()
                ->send();

            return null;
        }

        $csv = $service->exportCsv($exam, $set);

        return response()->streamDownload(
            fn () => print ($csv),
            Str::slug($set->title ?: 'set-'.$set->getKey()).'.csv',
            ['Content-Type' => 'text/csv']
        );
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
                    ->live()
                    // The student lists below are per set: a student who never
                    // saw these questions would only produce an empty report.
                    ->afterStateUpdated(function (Set $set): void {
                        $set('student_id', null);
                        $set('student_ids', []);
                    })
                    ->visible(fn (): bool => $this->record->sets()->count() > 1)
                    ->helperText('Each student only ever answers one set, so a per-set report keeps the answer key and the marks aligned.'),

                Select::make('student_id')
                    ->label('Student')
                    ->options(fn (callable $get): array => $this->studentOptions($this->selectedSetId($get)))
                    ->searchable()
                    ->preload()
                    ->native(false)
                    ->required()
                    ->visible(fn (callable $get): bool => $get('scope') === 'single')
                    ->helperText(fn (callable $get): ?string => $this->studentOptions($this->selectedSetId($get)) === []
                        ? 'No student has submitted this set yet.'
                        : null),

                CheckboxList::make('student_ids')
                    ->label('Students')
                    ->options(fn (callable $get): array => $this->studentOptions($this->selectedSetId($get)))
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

                $setId = filled($data['set'] ?? null) ? (int) $data['set'] : null;

                if ($scope === 'all' && $this->studentOptions($setId) === []) {
                    Notification::make()
                        ->title('No submissions yet')
                        ->body('Nobody has submitted this set, so there is nothing to grade.')
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
                    'set' => $setId,
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
     * Students to pick from, optionally narrowed to one set.
     *
     * @return array<int, string>
     */
    private function studentOptions(?int $setId = null): array
    {
        $key = (string) ($setId ?? 0);

        return $this->studentOptions[$key] ??= app(ExamAnswerReportService::class)->studentOptions(
            $this->record,
            $setId === null ? null : $this->record->sets()->find($setId),
        );
    }

    /**
     * @param  callable(string): mixed  $get
     */
    private function selectedSetId(callable $get): ?int
    {
        $setId = (int) ($get('set') ?? 0);

        return $setId > 0 ? $setId : null;
    }
}
