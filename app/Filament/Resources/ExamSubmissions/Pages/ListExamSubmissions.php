<?php

namespace App\Filament\Resources\ExamSubmissions\Pages;

use App\Filament\Resources\ExamSubmissions\ExamSubmissionResource;
use App\Models\Section;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
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

    public function getTabs(): array
    {
        $tabs = [
            'all' => Tab::make('All sections'),
        ];

        foreach (Section::query()->orderBy('name')->get() as $section) {
            $sectionId = $section->id;
            $tabs['section_'.$sectionId] = Tab::make($section->name)
                ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                    'user',
                    fn (Builder $q) => $q->where('section_id', $sectionId),
                ));
        }

        $tabs['no_section'] = Tab::make('No section')
            ->modifyQueryUsing(fn (Builder $query) => $query->whereHas(
                'user',
                fn (Builder $q) => $q->whereNull('section_id'),
            ));

        return $tabs;
    }
}
