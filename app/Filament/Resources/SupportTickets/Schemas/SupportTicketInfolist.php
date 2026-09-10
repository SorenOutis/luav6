<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class SupportTicketInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('Student'),
                TextEntry::make('workspace.name')
                    ->label('Workspace')
                    ->placeholder('Platform global'),
                TextEntry::make('subject')
                    ->columnSpanFull(),
                TextEntry::make('category')
                    ->badge(),
                TextEntry::make('priority')
                    ->badge(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('message')
                    ->columnSpanFull(),
                TextEntry::make('attachment_path')
                    ->label('Attachment')
                    ->placeholder('No attachment')
                    ->columnSpanFull(),
                TextEntry::make('resolver.name')
                    ->label('Resolved by')
                    ->placeholder('—'),
                TextEntry::make('resolved_at')
                    ->label('Resolved at')
                    ->dateTime()
                    ->placeholder('—'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
