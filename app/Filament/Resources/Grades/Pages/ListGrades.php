<?php

namespace App\Filament\Resources\Grades\Pages;

use App\Filament\Resources\Grades\GradeResource;
use App\Models\Grade;
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

class ListGrades extends ListRecords
{
    protected static string $resource = GradeResource::class;

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
}
