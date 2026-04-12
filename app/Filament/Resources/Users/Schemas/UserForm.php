<?php

namespace App\Filament\Resources\Users\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('avatar')
                    ->image()
                    ->avatar()
                    ->disk('public')
                    ->directory('avatars')
                    ->maxSize(10240)
                    ->formatStateUsing(fn ($record) => $record?->getRawOriginal('avatar'))
                    ->label('Profile Picture'),
                FileUpload::make('cover_photo')
                    ->image()
                    ->disk('public')
                    ->directory('covers')
                    ->maxSize(10240)
                    ->formatStateUsing(fn ($record) => $record?->getRawOriginal('cover_photo'))
                    ->label('Cover Photo'),
                TextInput::make('name')
                    ->required(),
                TextInput::make('email')
                    ->label('Email address')
                    ->email()
                    ->required(),
                DateTimePicker::make('email_verified_at'),
                \Filament\Forms\Components\Select::make('sections')
                    ->relationship('sections', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->placeholder('No Section')
                    ->label('Sections')
                    ->saveRelationshipsUsing(function ($model, $state) {
                        $model->sections()->sync($state);
                    }),
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
