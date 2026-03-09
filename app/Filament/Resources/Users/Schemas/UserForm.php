<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                Section::make('Seasonal Progress')
                    ->description('Stats for the currently active season')
                    ->relationship('currentSeasonProgress')
                    ->schema([
                        TextInput::make('points')
                            ->numeric()
                            ->default(0),
                        TextInput::make('exp')
                            ->label('XP')
                            ->hint('100 XP = 1 Level')
                            ->numeric()
                            ->default(0)
                    ]),
                TextInput::make('password')
                    ->password()
                    ->dehydrated(fn($state) => filled($state))
                    ->required(fn(string $operation): bool => $operation === 'create'),
            ]);
    }
}
