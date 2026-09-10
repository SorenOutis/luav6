<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\Actions\ReplyToStudent;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewSupportTicket extends ViewRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReplyToStudent::make(),
            Action::make('resolve')
                ->label('Mark resolved')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->hidden(fn ($record) => $record->isResolved())
                ->action(fn ($record) => $record->markResolved(auth()->user(), SupportTicket::STATUS_RESOLVED)),
            EditAction::make(),
        ];
    }
}
