<?php

namespace App\Filament\Resources\Workspaces\Pages;

use App\Filament\Resources\Workspaces\WorkspaceResource;
use App\Support\WorkspaceContext;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWorkspaces extends ListRecords
{
    protected static string $resource = WorkspaceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exitInspection')
                ->label('Exit workspace inspection')
                ->color('warning')
                ->visible(fn (): bool => app(WorkspaceContext::class)->isInspecting())
                ->action(function () {
                    app(WorkspaceContext::class)->stopInspecting();

                    return redirect(static::$resource::getUrl('index'));
                }),
            CreateAction::make(),
        ];
    }
}
