<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Models\ExamSubmission;
use App\Models\Section;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListExamSubmissions extends ListRecords
{
    protected static string $resource = ExamSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
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
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'exam',
                    fn (Builder $q) => $q->where('section_id', $sectionId),
                ));
        }

        $tabs['no_section'] = Tab::make('No section')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                'exam',
                fn (Builder $q) => $q->whereNull('section_id'),
            ));

        return $tabs;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                View::make('filament.resources.exam-submissions.exam-score-containers'),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }

    public function getVisibleExamScoreExportSummaries(): Collection
    {
        $submissionIds = $this->getVisibleSubmissionIds();

        if ($submissionIds->isEmpty()) {
            return collect();
        }

        return ExamSubmission::query()
            ->whereIn('id', $submissionIds)
            ->whereNotNull('exam_id')
            ->select([
                'exam_id',
                DB::raw('COUNT(*) as submission_count'),
                DB::raw('COUNT(DISTINCT user_id) as student_count'),
                DB::raw('SUM(score) as total_score'),
            ])
            ->groupBy('exam_id')
            ->with(['exam.section'])
            ->get()
            ->sortBy(fn (ExamSubmission $summary): string => $summary->exam?->title ?? 'Unknown exam')
            ->values();
    }

    public function exportExamTotalScores(int $examId): StreamedResponse
    {
        $submissionIds = $this->getVisibleSubmissionIds();
        $exam = $this->getVisibleExamScoreExportSummaries()
            ->first(fn (ExamSubmission $summary): bool => (int) $summary->exam_id === $examId)
            ?->exam;

        $filename = str($exam?->title ?? 'exam')
            ->slug()
            ->append('_total_scores_', now()->format('Y-m-d_H-i'), '.csv')
            ->toString();

        return response()->streamDownload(function () use ($submissionIds, $examId) {
            $handle = fopen('php://memory', 'w');
            fputcsv($handle, ['Student Name', 'Exam', 'Total Score']);

            $data = ExamSubmission::query()
                ->whereIn('id', $submissionIds)
                ->where('exam_id', $examId)
                ->select('user_id', 'exam_id', DB::raw('SUM(score) as total_score'))
                ->groupBy('user_id', 'exam_id')
                ->with(['user', 'exam'])
                ->get();

            foreach ($data as $row) {
                fputcsv($handle, [
                    $row->user?->name ?? 'Unknown',
                    $row->exam?->title ?? 'Unknown',
                    $row->total_score,
                ]);
            }

            rewind($handle);
            fpassthru($handle);
            fclose($handle);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    private function getVisibleSubmissionIds(): Collection
    {
        $query = $this->getFilteredTableQuery();

        if (! $query) {
            return collect();
        }

        return (clone $query)
            ->reorder()
            ->pluck((new ExamSubmission)->qualifyColumn('id'));
    }
}
