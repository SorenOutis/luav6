<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\SectionProgress;
use App\Models\User;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Image;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use MatondoJK\FilamentAvatarPicker\Components\AvatarPicker;

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
                                            ->label('Email address')
                                            ->email()
                                            ->required()
                                            ->unique(ignoreRecord: true)
                                            ->prefixIcon('heroicon-m-envelope'),
                                        TextInput::make('password')
                                            ->password()
                                            ->dehydrated(fn ($state) => filled($state))
                                            ->required(fn (string $operation): bool => $operation === 'create')
                                            ->prefixIcon('heroicon-m-lock-closed'),
                                        DateTimePicker::make('email_verified_at')
                                            ->prefixIcon('heroicon-m-check-badge')
                                            ->columnSpanFull(),
                                        Toggle::make('is_admin')
                                            ->label('Administrator Access')
                                            ->helperText('Grant full access to the admin panel')
                                            ->columnSpanFull(),
                                        Toggle::make('is_banned')
                                            ->label('Banned')
                                            ->helperText('Blocked students will be shown a banned modal on the dashboard.')
                                            ->live()
                                            ->columnSpanFull(),
                                        TextInput::make('ban_reason')
                                            ->label('Ban reason')
                                            ->maxLength(1000)
                                            ->visible(fn ($get) => (bool) $get('is_banned'))
                                            ->columnSpanFull(),
                                    ]),
                            ]),

                        Tabs\Tab::make('Profile Visuals')
                            ->icon('heroicon-o-photo')
                            ->schema([
                                Grid::make(2)
                                    ->schema([
                                        Section::make('Avatar')
                                            ->description('Choose a curated avatar or upload a custom profile picture.')
                                            ->schema([
                                                AvatarPicker::make('avatar')
                                                    ->label('Profile Picture')
                                                    ->maxSize(10240),
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
                            ]),

                        Tabs\Tab::make('Gamification')
                            ->icon('heroicon-o-trophy')
                            ->schema([
                                Section::make('Section Progress')
                                    ->description(fn (?User $record): string => $record && $record->sections->isNotEmpty()
                                        ? 'Level, XP, and points for each section this student is enrolled in. 100 XP = 1 Level.'
                                        : 'This student is not enrolled in any sections yet. Assign sections in the Relationships tab, then edit their per-section stats here.')
                                    ->schema([
                                        Repeater::make('section_progress_rows')
                                            ->label('')
                                            ->schema([
                                                Hidden::make('section_id')
                                                    ->required(),
                                                Grid::make(4)
                                                    ->schema([
                                                        TextInput::make('section_name')
                                                            ->label('Section')
                                                            ->disabled()
                                                            ->dehydrated(false)
                                                            ->columnSpan(1),
                                                        TextInput::make('level')
                                                            ->numeric()
                                                            ->minValue(1)
                                                            ->default(1)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                                $level = max(1, (int) $state);
                                                                $set('level', $level);
                                                                $set('exp', SectionProgress::expFloorForLevel($level));
                                                            })
                                                            ->columnSpan(1),
                                                        TextInput::make('points')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->default(0)
                                                            ->columnSpan(1),
                                                        TextInput::make('exp')
                                                            ->label('XP')
                                                            ->hint('100 XP = 1 Level')
                                                            ->numeric()
                                                            ->minValue(0)
                                                            ->default(0)
                                                            ->live(onBlur: true)
                                                            ->afterStateUpdated(function (Set $set, mixed $state): void {
                                                                $set('level', SectionProgress::levelFromExp((float) $state));
                                                            })
                                                            ->columnSpan(1),
                                                    ]),
                                            ])
                                            ->columnSpanFull()
                                            ->addable(false)
                                            ->deletable(false)
                                            ->reorderable(false)
                                            ->defaultItems(0)
                                            ->itemLabel(fn (array $state): ?string => $state['section_name'] ?? 'Section')
                                            ->collapsible(),
                                    ]),
                                Grid::make(3)
                                    ->schema([
                                        Placeholder::make('total_level')
                                            ->label('Total Level')
                                            ->content(fn (?User $record): string => (string) ($record?->level ?? 1)),
                                        Placeholder::make('total_points')
                                            ->label('Total Points')
                                            ->content(fn (?User $record): string => (string) ($record?->points ?? 0)),
                                        Placeholder::make('total_exp')
                                            ->label('Total XP')
                                            ->content(fn (?User $record): string => (string) ($record?->exp ?? 0)),
                                        TextInput::make('current_streak')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->label('Current Streak')
                                            ->prefixIcon('heroicon-m-fire'),
                                        TextInput::make('longest_streak')
                                            ->numeric()
                                            ->disabled()
                                            ->dehydrated(false)
                                            ->label('Longest Streak')
                                            ->prefixIcon('heroicon-m-star'),
                                    ]),
                                Section::make('Seasonal Progress')
                                    ->description('Totals for the currently active season. Updated automatically when section stats change.')
                                    ->schema([
                                        Grid::make(3)
                                            ->schema([
                                                Placeholder::make('season_level')
                                                    ->label('Season Level')
                                                    ->content(fn (?User $record): string => (string) ($record?->currentSeasonProgress?->level ?? '—')),
                                                Placeholder::make('season_points')
                                                    ->label('Season Points')
                                                    ->content(fn (?User $record): string => (string) ($record?->currentSeasonProgress?->points ?? '—')),
                                                Placeholder::make('season_exp')
                                                    ->label('Season XP')
                                                    ->content(fn (?User $record): string => (string) ($record?->currentSeasonProgress?->exp ?? '—')),
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
