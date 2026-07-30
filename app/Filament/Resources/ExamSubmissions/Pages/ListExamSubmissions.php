<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Models\Exam;
use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Actions as ActionsComponent;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\RenderHook;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\View\PanelsRenderHook;
use Illuminate\Database\Eloquent\Builder;

class ListExamSubmissions extends ListRecords
{
    protected static string $resource = ExamSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }

    /**
     * @return array<int|string, Action|ActionGroup>
     */
    protected function getToolsActions(): array
    {
        return [
            Action::make('monitorExam')
                ->label('Monitor Exam')
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
                ActionsComponent::make($this->getToolsActions())
                    ->label('Tools')
                    ->columnSpanFull(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_BEFORE),
                EmbeddedTable::make(),
                RenderHook::make(PanelsRenderHook::RESOURCE_PAGES_LIST_RECORDS_TABLE_AFTER),
            ]);
    }
}
