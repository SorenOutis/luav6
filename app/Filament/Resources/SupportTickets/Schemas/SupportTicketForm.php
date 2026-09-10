<?php

namespace App\Filament\Resources\SupportTickets\Schemas;

use App\Models\SupportTicket;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SupportTicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Student')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),
                TextInput::make('subject')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('category')
                    ->options(SupportTicket::CATEGORIES)
                    ->required()
                    ->default('general'),
                Select::make('priority')
                    ->options(SupportTicket::PRIORITIES)
                    ->required()
                    ->default('medium'),
                Select::make('status')
                    ->options(SupportTicket::STATUSES)
                    ->required()
                    ->default(SupportTicket::STATUS_OPEN),
                Textarea::make('message')
                    ->required()
                    ->rows(5)
                    ->columnSpanFull(),
                FileUpload::make('attachment_path')
                    ->label('Attachment')
                    ->disk('public')
                    ->directory('support-tickets')
                    ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp', 'application/pdf', 'text/plain'])
                    ->maxSize(10240)
                    ->downloadable()
                    ->openable()
                    ->columnSpanFull(),
                Select::make('resolved_by')
                    ->label('Resolved by')
                    ->relationship('resolver', 'name')
                    ->searchable()
                    ->preload()
                    ->placeholder('Not resolved yet'),
                DateTimePicker::make('resolved_at')
                    ->label('Resolved at')
                    ->placeholder('Set when marked resolved/closed'),
            ]);
    }
}
