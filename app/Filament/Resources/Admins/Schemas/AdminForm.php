<?php

namespace App\Filament\Resources\Admins\Schemas;

use App\Models\Workspace;
use Filament\Forms\Components\Select;
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
                    ->description('Create an administrator and either assign an existing tenant workspace or create a new one.')
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

                                Select::make('workspace_id')
                                    ->label('Tenant workspace')
                                    ->options(fn (): array => Workspace::query()->orderBy('name')->pluck('name', 'id')->all())
                                    ->searchable()
                                    ->placeholder('Create a new workspace for this admin')
                                    ->helperText('Select an existing tenant to add another administrator, or leave blank to create a new tenant.')
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
