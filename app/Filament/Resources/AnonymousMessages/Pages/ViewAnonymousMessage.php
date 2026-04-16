<?php

namespace App\Filament\Resources\AnonymousMessages\Pages;

use App\Filament\Resources\AnonymousMessages\AnonymousMessageResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewAnonymousMessage extends ViewRecord
{
    protected static string $resource = AnonymousMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Approve')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->hidden(fn ($record) => $record->is_approved)
                ->action(fn ($record) => $record->update(['is_approved' => true])),
            Action::make('unapprove')
                ->label('Reject')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn ($record) => $record->is_approved)
                ->action(fn ($record) => $record->update(['is_approved' => false])),
            EditAction::make(),
        ];
    }
}
