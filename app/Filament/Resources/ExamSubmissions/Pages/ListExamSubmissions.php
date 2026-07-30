<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Models\Exam;
use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ListExamSubmissions extends ListRecords
{
    protected static string $resource = ExamSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('monitorExam')
                ->label('Monitor live exam')
                ->icon('heroicon-o-signal')
                ->color('gray')
                ->form([
                    Select::make('exam_id')
                        ->label('Exam')
                        ->options(
                            Exam::query()
                                ->where('status', '!=', 'draft')
                                ->orderByDesc('exam_date')
                                ->pluck('title', 'id')
                                ->all()
                        )
                        ->searchable()
                        ->required(),
                ])
                ->action(fn (array $data) => redirect(ExamSubmissionResource::getUrl('monitor', ['exam' => $data['exam_id']]))),
        ];
    }

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All Exams'),
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
                EmbeddedTable::make(),
            ]);
    }

    public function getSubheading(): ?string
    {
        return 'Review grading progress, scores, and live exam activity from one workspace.';
    }
}
