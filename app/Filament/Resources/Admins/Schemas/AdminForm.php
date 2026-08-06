<?php

namespace App\Filament\Resources\Admins\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AdminForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Admin Account')
                    ->description('Create a new admin workspace. The admin will start with an empty workspace — no sections, students, or data.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                TextInput::make('first_name')
                                    ->label('First name')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-user'),

                                TextInput::make('last_name')
                                    ->label('Last name')
                                    ->required()
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-user'),

                                TextInput::make('middle_name')
                                    ->label('Middle name (optional)')
                                    ->maxLength(255)
                                    ->prefixIcon('heroicon-m-user')
                                    ->columnSpanFull(),

                                TextInput::make('email')
                                    ->label('Email Address')
                                    ->email()
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->prefixIcon('heroicon-m-envelope'),

                                TextInput::make('password')
                                    ->label('Password')
                                    ->password()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(fn ($state) => filled($state))
                                    ->minLength(8)
                                    ->confirmed()
                                    ->helperText('Minimum 8 characters. Leave blank to keep existing password on edit.')
                                    ->prefixIcon('heroicon-m-lock-closed'),

                                TextInput::make('password_confirmation')
                                    ->label('Confirm Password')
                                    ->password()
                                    ->required(fn (string $operation): bool => $operation === 'create')
                                    ->dehydrated(false)
                                    ->minLength(8)
                                    ->prefixIcon('heroicon-m-lock-closed')
                                    ->columnSpanFull(),
                            ]),
                    ]),
            ]);
    }
}
