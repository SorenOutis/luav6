<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Grid;
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
                Tabs::make('User Details')
                    ->tabs([
                        Tabs\Tab::make('Account')
                            ->icon('heroicon-o-user-circle')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->required()
                                            ->prefixIcon('heroicon-m-user'),
                                        TextInput::make('email')
                                            ->label('Email address')
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->prefixIcon('heroicon-m-envelope'),
                                        TextInput::make('password')
                                            ->password()
                                            ->dehydrated(fn($state) => filled($state))
                                            ->required(fn(string $operation): bool => $operation === 'create')
                                            ->prefixIcon('heroicon-m-lock-closed'),
                                        DateTimePicker::make('email_verified_at')
                                            ->prefixIcon('heroicon-m-check-badge'),
                                        Toggle::make('is_admin')
                                            ->label('Administrator Access')
                                            ->helperText('Grant full access to the admin panel')
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Profile Visuals')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Section::make('Avatar')
                                            ->schema([
                                                Flex::make([
                                                    Image::make(fn ($record) => $record?->avatar ?? '', 'Profile Picture')
                                                        ->imageSize(120)
                                                        ->visible(fn ($record) => $record?->avatar),
                                                    FileUpload::make('avatar')
                                                        ->image()
                                                        ->disk('public')
                                                        ->directory('avatars')
                                                        ->maxSize(10240)
                                                        ->dehydrated(fn ($state) => filled($state))
                                                        ->label('Change Profile Picture'),
                                                ])
                                                ->from('md')
                                                ->gap()
                                                ->alignCenter(),
                                            ]),
                                        Section::make('Cover Photo')
                                            ->schema([
                                                Flex::make([
                                                    Image::make(fn ($record) => $record?->cover_photo ?? '', 'Cover Photo')
                                                        ->imageHeight(120)
                                                        ->visible(fn ($record) => $record?->cover_photo),
                                                    FileUpload::make('cover_photo')
                                                        ->image()
                                                        ->disk('public')
                                                        ->directory('covers')
                                                        ->maxSize(10240)
                                                        ->dehydrated(fn ($state) => filled($state))
                                                        ->label('Change Cover Photo'),
                                                ])
                                                ->from('md')
                                                ->gap()
                                                ->alignCenter(),
                                            ]),
                                    ]),
                                Section::make('Section Progress')
                                    ->description('Stats for each enrolled section')
                                    ->schema([
                                        Repeater::make('sectionProgress')
                                            ->relationship('sectionProgress')
                                            ->label('')
                                            ->schema([
                                                Grid::make(4)
                                                    ->schema([
                                                        Select::make('section_id')
                                                            ->relationship('section', 'name')
                                                            ->disabled()
                                                            ->label('Section')
                                                            ->columnSpan(1),
                                                        TextInput::make('level')
                                                            ->numeric()
                                                            ->disabled()
                                                            ->label('Level')
                                                            ->columnSpan(1),
                                                        TextInput::make('points')
                                                            ->numeric()
                                                            ->default(0)
                                                            ->columnSpan(1),
                                                        TextInput::make('exp')
                                                            ->label('XP')
                                                            ->hint('100 XP = 1 Level')
                                                            ->numeric()
                                                            ->default(0)
                                                            ->columnSpan(1),
                                                    ]),
                                            ])
                                            ->columnSpanFull()
                                            ->addable(false)
                                            ->deletable(false)
                                            ->collapsible(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Gamification')
                            ->icon('heroicon-o-trophy')
                            ->schema([
                                Grid::make(3)
                                    ->schema([
                                        TextInput::make('level')
                                            ->numeric()
                                            ->default(1)
                                            ->prefixIcon('heroicon-m-sparkles'),
                                        TextInput::make('points')
                                            ->numeric()
                                            ->default(0)
                                            ->prefixIcon('heroicon-m-currency-dollar'),
                                        TextInput::make('exp')
                                            ->label('XP')
                                            ->hint('100 XP = 1 Level')
                                            ->numeric()
                                            ->default(0)
                                            ->prefixIcon('heroicon-m-bolt'),
                                        TextInput::make('current_streak')
                                            ->numeric()
                                            ->disabled()
                                            ->label('Current Streak')
                                            ->prefixIcon('heroicon-m-fire'),
                                        TextInput::make('longest_streak')
                                            ->numeric()
                                            ->disabled()
                                            ->label('Longest Streak')
                                            ->prefixIcon('heroicon-m-star'),
                                    ]),
                                Section::make('Seasonal Progress')
                                    ->description('Stats for the currently active season')
                                    ->relationship('currentSeasonProgress')
                                    ->schema([
                                        Grid::make(2)
                                            ->schema([
                                                TextInput::make('points')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->prefixIcon('heroicon-m-currency-dollar'),
                                                TextInput::make('exp')
                                                    ->label('XP')
                                                    ->hint('100 XP = 1 Level')
                                                    ->numeric()
                                                    ->default(0)
                                                    ->prefixIcon('heroicon-m-bolt'),
                                            ]),
                                    ]),
                            ]),

                        Tabs\Tab::make('Relationships')
                            ->icon('heroicon-o-link')
                            ->schema([
                                Select::make('sections')
                                    ->relationship('sections', 'name')
                                    ->multiple()
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('No Section')
                                    ->label('Sections')
                                    ->prefixIcon('heroicon-m-tag'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
