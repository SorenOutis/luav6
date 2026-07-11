<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Jobs\GenerateExamEssayFeedback;
use App\Models\Exam;
use App\Models\ExamAiFeedbackRun;
use App\Models\Section;
use App\Support\AiQueueWorker;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
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
     * @return array<int|string, Action|\Filament\Actions\ActionGroup>
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
            Action::make('toggleAiEssayFeedback')
                ->label('AI Essay Feedback')
                ->icon('heroicon-o-sparkles')
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
                    Toggle::make('enabled')
                        ->label('Enable and generate feedback')
                        ->helperText('When enabled, AI feedback will be generated for essay answers that do not have it yet.')
                        ->default(true),
                ])
                ->action(function (array $data) {
                    $exam = Exam::query()->findOrFail((int) $data['exam_id']);
                    $enable = (bool) ($data['enabled'] ?? false);

                    $exam->ai_feedback_enabled = $enable;
                    $exam->ai_feedback_enabled_at = $enable ? now() : null;
                    $exam->save();

                    if ($enable) {
                        $activeRun = ExamAiFeedbackRun::query()
                            ->where('exam_id', $exam->id)
                            ->where('status', 'running')
                            ->where('started_at', '>=', now()->subMinutes(30))
                            ->latest('id')
                            ->first();

                        if ($activeRun) {
                            Notification::make()
                                ->title('AI feedback already running')
                                ->body('A run is currently in progress for this exam. You were redirected to the progress page.')
                                ->warning()
                                ->send();

                            return redirect()->to(ExamSubmissionResource::getUrl('ai-feedback-progress', ['exam' => $exam->id]));
                        }

                        GenerateExamEssayFeedback::dispatch($exam->id);
                        AiQueueWorker::ensureRunning();

                        $body = 'Essay feedback generation has been queued for this exam.';

                        Notification::make()
                            ->title('AI feedback started')
                            ->body($body.' You can monitor progress on the progress page.')
                            ->success()
                            ->send();

                        return redirect()->to(ExamSubmissionResource::getUrl('ai-feedback-progress', ['exam' => $exam->id]));
                    } else {
                        Notification::make()
                            ->title('AI feedback disabled')
                            ->body('New essay submissions will not be assessed until you enable it again.')
                            ->warning()
                            ->send();
                    }
                }),
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
