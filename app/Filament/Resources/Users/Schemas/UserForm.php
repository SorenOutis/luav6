<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Flex::make([
                    Image::make(fn ($record) => $record?->avatar ?? '', 'Profile Picture')
                        ->imageSize(120)
                        ->visible(fn ($record) => $record?->avatar),
                    FileUpload::make('avatar')
                        ->image()
                        ->disk('public')
                        ->directory('avatars')
                        ->maxSize(10240)
                        ->formatStateUsing(fn ($state, $record) => $record?->getRawOriginal('avatar'))
                        ->label('Change Profile Picture'),
                ])
                ->from('md')
                ->gap()
                ->alignCenter(),
                Flex::make([
                    Image::make(fn ($record) => $record?->cover_photo ?? '', 'Cover Photo')
                        ->imageHeight(120)
                        ->visible(fn ($record) => $record?->cover_photo),
                    FileUpload::make('cover_photo')
                        ->image()
                        ->disk('public')
                        ->directory('covers')
                        ->maxSize(10240)
                        ->formatStateUsing(fn ($state, $record) => $record?->getRawOriginal('cover_photo'))
                        ->label('Change Cover Photo'),
                ])
                ->from('md')
                ->gap()
                ->alignCenter(),
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
                    ->label('Sections'),
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
