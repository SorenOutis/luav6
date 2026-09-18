<?php

namespace App\Filament\Resources\SupportTickets\Actions;

use App\Models\SupportTicket;
use App\Services\SupportReplyService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

class ReplyToStudent
{
    public static function make(): Action
    {
        return Action::make('replyToStudent')
            ->label('Reply')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->color('info')
            ->schema([
                Textarea::make('message')
                    ->label('Reply to student')
                    ->helperText('The student is notified and can read this in the Support conversation.')
                    ->required()
                    ->maxLength(2000)
                    ->rows(5)
                    ->columnSpanFull(),
            ])
            ->action(function (array $data, SupportTicket $record): void {
                try {
                    app(SupportReplyService::class)->createAdminReply(
                        $record,
                        auth()->user(),
                        (string) ($data['message'] ?? ''),
                    );
                } catch (ValidationException) {
                    Notification::make()
                        ->title('Reply cannot be empty.')
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Reply sent to student.')
                    ->success()
                    ->send();
            });
    }
}
