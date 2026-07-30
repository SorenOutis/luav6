<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Models\Exam;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;

class ListExamSubmissions extends ListRecords
{
    protected static string $resource = ExamSubmissionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Add submission'),
            ActionGroup::make([
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
            ])
                ->label('More actions')
                ->icon('heroicon-o-ellipsis-horizontal'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                EmbeddedTable::make(),
            ]);
    }

    public function getSubheading(): ?string
    {
        return 'Review grading progress, scores, and live exam activity from one workspace.';
    }
}
