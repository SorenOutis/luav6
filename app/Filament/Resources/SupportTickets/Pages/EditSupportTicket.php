<?php

namespace App\Filament\Resources\SupportTickets\Pages;

use App\Filament\Resources\SupportTickets\Actions\ReplyToStudent;
use App\Filament\Resources\SupportTickets\SupportTicketResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditSupportTicket extends EditRecord
{
    protected static string $resource = SupportTicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ReplyToStudent::make(),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (in_array($data['status'] ?? null, ['resolved', 'closed'], true) && empty($data['resolved_by'])) {
            $data['resolved_by'] = auth()->id();
            $data['resolved_at'] ??= now()->toDateTimeString();
        }

        if (in_array($data['status'] ?? null, ['open', 'in_progress'], true)) {
            $data['resolved_by'] = null;
            $data['resolved_at'] = null;
        }

        return $data;
    }
}
