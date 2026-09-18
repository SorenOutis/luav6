<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\SectionProgress;
use App\Models\User;
use App\Support\AvatarGallery;
use App\Support\PublicFileUrl;
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
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use MatondoJK\FilamentAvatarPicker\Components\AvatarPicker;
use Throwable;

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
                                                    ->maxSize(10240)
                                                    // The field is a file upload bound to the public disk, but
                                                    // the `avatar` accessor resolves full URLs: Filament cannot
                                                    // find the value on the disk, drops it, and dehydrates the
                                                    // empty result — an empty preview now and a cleared column
                                                    // on the next save.
                                                    ->afterStateHydrated(function (AvatarPicker $component, mixed $state): void {
                                                        $component->rawState(self::normaliseAvatarState($state));
                                                    })
                                                    // Previews are fetched over HTTP, so they have to be
                                                    // resolved the same way the rest of the application does.
                                                    ->getUploadedFileUsing(fn (string $file): ?array => self::avatarPreview($file)),
                                            ]),
                                        Section::make('Cover Photo')
                                            ->schema([
                                                Flex::make([
                                                    Image::make(fn (?User $record) => PublicFileUrl::resolve($record?->cover_photo) ?? '', 'Cover Photo')
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

    /**
     * Put the avatar field's hydrated state back into the shape the picker
     * expects: a path on the public disk.
     *
     * The `avatar` accessor returns a full URL, which a file upload field
     * cannot resolve, so the state is reduced back to the stored path (this
     * also heals rows that already hold a URL). Curated avatars ship with the
     * application and are never uploaded to the bucket, so they cannot be
     * existence-checked when the public disk is S3/R2; uploaded avatars can,
     * and a file that is gone should not render as a broken preview.
     *
     * @return array<array-key, string>
     */
    private static function normaliseAvatarState(mixed $state): array
    {
        $files = [];

        foreach (Arr::wrap($state) as $fileKey => $file) {
            if (! is_string($file) || blank($file)) {
                continue;
            }

            $path = PublicFileUrl::storedPath($file);

            if (blank($path)) {
                continue;
            }

            // Values that are still full URLs (a provider avatar, a bucket from
            // another environment) cannot be checked against the disk and the
            // browser fetches them directly, so they stay as they are.
            $isUnverifiable = self::isRemoteUrl($path) || AvatarGallery::isCurated($path);

            if (! $isUnverifiable && ! self::existsOnPublicDisk($path)) {
                continue;
            }

            $files[$fileKey] = $path;
        }

        return $files;
    }

    private static function existsOnPublicDisk(string $path): bool
    {
        try {
            return Storage::disk('public')->exists($path);
        } catch (Throwable) {
            return false;
        }
    }

    private static function isRemoteUrl(string $value): bool
    {
        return Str::startsWith($value, ['http://', 'https://', '//']);
    }

    /**
     * Preview payload for the avatar field.
     *
     * The browser fetches this URL (FilePond loads stored files over HTTP), so
     * it has to go through the same helper the rest of the application uses —
     * the disk's own URL cannot see the curated avatars that only exist in
     * `public/avatars`, and it double-prefixes values that are already URLs.
     *
     * @return array{name: string, size: int, type: null, url: string}|null
     */
    private static function avatarPreview(string $file): ?array
    {
        $url = PublicFileUrl::resolve($file);

        if (blank($url)) {
            return null;
        }

        return [
            'name' => basename($file),
            // FilePond reads the size and mime type from the file it fetches;
            // the curated SVGs are not stat()-able when the disk is S3/R2.
            'size' => 0,
            'type' => null,
            'url' => $url,
        ];
    }
}
