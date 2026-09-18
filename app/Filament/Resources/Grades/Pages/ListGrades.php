<?php

namespace App\Filament\Resources\Grades\Pages;

use App\Filament\Resources\Grades\GradeResource;
use App\Models\Grade;
use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ListGrades extends ListRecords
{
    protected static string $resource = GradeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadCollegeTemplate')
                ->label('College CSV Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Select::make('section_id')
                        ->label('Section')
                        ->options(fn () => Section::query()
                            ->where('school_level', Section::SCHOOL_LEVEL_COLLEGE)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(fn (array $data) => $this->downloadGradeTemplate(
                    Section::SCHOOL_LEVEL_COLLEGE,
                    (int) $data['section_id'],
                )),
            Action::make('downloadSeniorHighTemplate')
                ->label('Senior High CSV Template')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->form([
                    Select::make('section_id')
                        ->label('Section')
                        ->options(fn () => Section::query()
                            ->where('school_level', Section::SCHOOL_LEVEL_SENIOR_HIGH)
                            ->orderBy('name')
                            ->pluck('name', 'id'))
                        ->searchable()
                        ->required(),
                ])
                ->action(fn (array $data) => $this->downloadGradeTemplate(
                    Section::SCHOOL_LEVEL_SENIOR_HIGH,
                    (int) $data['section_id'],
                )),
            Action::make('importGrades')
                ->label('Upload Grades CSV')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->form([
                    Select::make('school_level')
                        ->label('School level')
                        ->options(Section::schoolLevelOptions())
                        ->required()
                        ->live()
                        ->afterStateUpdated(fn (Set $set) => $set('section_id', null)),
                    Select::make('section_id')
                        ->label('Section')
                        ->options(fn (Get $get) => filled($get('school_level'))
                            ? Section::query()
                                ->where('school_level', $get('school_level'))
                                ->orderBy('name')
                                ->pluck('name', 'id')
                            : [])
                        ->searchable()
                        ->required()
                        ->disabled(fn (Get $get): bool => blank($get('school_level')))
                        ->helperText('Only sections from the selected school level are shown.'),
                    FileUpload::make('grades_file')
                        ->label('Grades CSV file')
                        ->required()
                        ->disk('local')
                        ->directory('temp-uploads')
                        ->acceptedFileTypes(['text/csv', 'application/vnd.ms-excel', 'text/plain'])
                        ->helperText('Use the matching College or Senior High School CSV template.'),
                ])
                ->action(function (array $data): void {
                    $result = $this->importGradesCsv(
                        Storage::disk('local')->path($data['grades_file']),
                        (string) $data['school_level'],
                        (int) $data['section_id'],
                    );

                    Notification::make()
                        ->title('Grades imported')
                        ->body("{$result['created']} created, {$result['updated']} updated, {$result['skipped']} skipped.")
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All sections'),
        ];

        foreach (Section::query()->orderBy('name')->get() as $section) {
            $sectionId = $section->id;
            $tabs['section_'.$sectionId] = Tab::make($section->name)
                ->modifyQueryUsing(fn (Builder $query) => $query->where('section_id', $sectionId));
        }

        return $tabs;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                View::make('filament.resources.grades.grade-summary-cards'),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function getVisibleGradeSummaries(): Collection
    {
        $gradeIds = $this->getVisibleGradeIds();

        if ($gradeIds->isEmpty()) {
            return collect();
        }

        return Grade::query()
            ->whereIn('id', $gradeIds)
            ->select([
                'section_id',
                'subject',
                DB::raw('COUNT(*) as grade_count'),
                DB::raw('COUNT(DISTINCT user_id) as student_count'),
                DB::raw('SUM(score) as total_score'),
                DB::raw('SUM(max_score) as total_max_score'),
            ])
            ->groupBy('section_id', 'subject')
            ->with('section')
            ->get()
            ->sortBy(fn (Grade $summary): string => ($summary->section?->name ?? '').' '.$summary->subject)
            ->values();
    }

    private function getVisibleGradeIds(): Collection
    {
        $query = $this->getFilteredTableQuery();

        if (! $query) {
            return collect();
        }

        return (clone $query)
            ->reorder()
            ->pluck((new Grade)->qualifyColumn('id'));
    }

    private function downloadGradeTemplate(string $schoolLevel, ?int $sectionId = null)
    {
        $section = $sectionId
            ? Section::query()
                ->where('school_level', $schoolLevel)
                ->find($sectionId)
            : null;

        $filenamePrefix = $schoolLevel === Section::SCHOOL_LEVEL_SENIOR_HIGH
            ? 'senior-high-grades-template'
            : 'college-grades-template';

        $filename = $section
            ? str($section->name)->slug()->prepend($filenamePrefix.'-')->append('.csv')->toString()
            : $filenamePrefix.'.csv';

        return response()->streamDownload(function () use ($schoolLevel, $sectionId): void {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $this->gradeTemplateHeaders($schoolLevel));

            Section::query()
                ->where('school_level', $schoolLevel)
                ->when($sectionId, fn (Builder $query) => $query->whereKey($sectionId))
                ->with(['users' => fn ($query) => $query
                    ->where('is_admin', false)
                    ->orderBy('name')])
                ->orderBy('name')
                ->get()
                ->each(function (Section $section) use ($handle, $schoolLevel): void {
                    foreach ($section->users as $student) {
                        fputcsv($handle, array_merge([
                            $section->id,
                            $section->name,
                            $student->id,
                            $student->name,
                            $section->name,
                            100,
                            '',
                        ], array_fill(0, count($this->gradePeriodColumns($schoolLevel)), '')));
                    }
                });

            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function importGradesCsv(string $path, string $schoolLevel, int $selectedSectionId): array
    {
        $handle = fopen($path, 'r');

        if (! $handle) {
            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $headers = fgetcsv($handle);

        if (! is_array($headers)) {
            fclose($handle);

            return ['created' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $headers[0]);
        $periodColumns = $this->gradePeriodColumns($schoolLevel);
        $created = 0;
        $updated = 0;
        $skipped = 0;

        DB::transaction(function () use ($handle, $headers, $periodColumns, $schoolLevel, $selectedSectionId, &$created, &$updated, &$skipped): void {
            while (($row = fgetcsv($handle)) !== false) {
                $row = array_pad($row, count($headers), null);
                $data = array_combine($headers, array_slice($row, 0, count($headers)));

                if (! is_array($data)) {
                    $skipped++;

                    continue;
                }

                $sectionId = (int) ($data['section_id'] ?? 0);
                $studentId = (int) ($data['student_id'] ?? 0);
                $section = Section::query()
                    ->where('school_level', $schoolLevel)
                    ->whereKey($selectedSectionId)
                    ->find($sectionId);

                if (! $section || ! $studentId || ! $section->users()->where('users.id', $studentId)->exists()) {
                    $skipped++;

                    continue;
                }

                $subject = trim((string) ($data['subject'] ?? '')) ?: $section->name;
                $maxScore = is_numeric($data['max_score'] ?? null) ? (float) $data['max_score'] : 100.0;
                $remarks = trim((string) ($data['remarks'] ?? '')) ?: null;
                $rowHadScore = false;

                foreach ($periodColumns as $period) {
                    $score = $data[$period] ?? null;

                    if ($score === null || trim((string) $score) === '') {
                        continue;
                    }

                    if (! is_numeric($score)) {
                        $skipped++;

                        continue;
                    }

                    $rowHadScore = true;
                    $grade = Grade::query()->updateOrCreate(
                        [
                            'user_id' => $studentId,
                            'section_id' => $section->id,
                            'subject' => $subject,
                            'period' => $period,
                        ],
                        [
                            'score' => (float) $score,
                            'max_score' => $maxScore,
                            'remarks' => $remarks,
                            'recorded_by' => auth()->id(),
                        ],
                    );

                    $grade->wasRecentlyCreated ? $created++ : $updated++;
                }

                if (! $rowHadScore) {
                    $skipped++;
                }
            }
        });

        fclose($handle);

        return compact('created', 'updated', 'skipped');
    }

    private function gradeTemplateHeaders(string $schoolLevel): array
    {
        return array_merge([
            'section_id',
            'section_name',
            'student_id',
            'student_name',
            'subject',
            'max_score',
            'remarks',
        ], $this->gradePeriodColumns($schoolLevel));
    }

    private function gradePeriodColumns(string $schoolLevel): array
    {
        return array_keys($schoolLevel === Section::SCHOOL_LEVEL_SENIOR_HIGH
            ? Section::seniorHighGradePeriods()
            : Section::collegeGradePeriods());
    }
}
