<?php

namespace App\Filament\Resources\ProfileMusicTracks\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ViewField;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ProfileMusicTrackForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Track Information')
                    ->description('Details and required attribution for this profile soundtrack.')
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('title')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Spectre'),
                            TextInput::make('artist')
                                ->required()
                                ->maxLength(255)
                                ->placeholder('e.g. Alan Walker'),
                            TextInput::make('source_url')
                                ->label('Official Source URL')
                                ->url()
                                ->maxLength(500)
                                ->placeholder('https://ncs.io/track/...'),
                            TextInput::make('license_name')
                                ->label('License / Usage Terms')
                                ->maxLength(255)
                                ->placeholder('e.g. NCS Usage Policy / CC BY 4.0'),
                        ]),
                        Textarea::make('attribution_text')
                            ->label('Required Attribution & Credits')
                            ->rows(3)
                            ->columnSpanFull()
                            ->placeholder("Track: Alan Walker - Spectre [NCS Release]\nMusic provided by NoCopyrightSounds.\nWatch: http://youtu.be/...\nFree Download / Stream: http://ncs.io/...")
                            ->helperText('Full attribution required under the track\'s license. This is displayed on student profiles to satisfy copyright requirements.'),
                        Grid::make(2)->schema([
                            Toggle::make('is_active')
                                ->label('Active in library')
                                ->default(true)
                                ->helperText('When enabled, students can select this track for their profile.'),
                            Toggle::make('is_global')
                                ->label('Allow in all workspaces')
                                ->default(true)
                                ->helperText('When enabled, students across all workspaces can choose this soundtrack. When disabled, only students in this workspace can use it.'),
                        ]),
                    ]),

                Section::make('Audio Track & Optional Trimmer')
                    ->description('Upload any full soundtrack or use the built-in browser trimmer to preview and optionally clip a specific segment.')
                    ->schema([
                        ViewField::make('audio_trimmer')
                            ->view('filament.components.audio-trimmer')
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        Grid::make(2)->schema([
                            FileUpload::make('audio_path')
                                ->label('Audio File')
                                ->disk('public')
                                ->directory('profile-music')
                                ->acceptedFileTypes([
                                    'audio/*',
                                    'audio/mpeg',
                                    'audio/mp3',
                                    'audio/wav',
                                    'audio/x-wav',
                                    'audio/wave',
                                    'audio/x-pn-wav',
                                    'audio/ogg',
                                    'audio/x-ogg',
                                    'audio/mp4',
                                    'audio/x-m4a',
                                    'audio/aac',
                                    'audio/x-aac',
                                    'audio/flac',
                                    'audio/x-flac',
                                ])
                                ->validationMessages([
                                    'mimetypes' => 'The audio file must be a valid audio format (WAV, MP3, OGG, M4A, AAC, FLAC).',
                                ])
                                ->required()
                                ->maxSize(51200)
                                ->helperText('Upload any complete song/soundtrack or a trimmed clip exported from the trimmer above.'),
                            TextInput::make('duration_seconds')
                                ->label('Duration (Seconds, Optional)')
                                ->numeric()
                                ->nullable()
                                ->minValue(0.1)
                                ->step(0.1)
                                ->placeholder('e.g. 185.0')
                                ->helperText('Optional track length in seconds. Automatically populated when using the trimmer or previewer.'),
                        ]),
                        FileUpload::make('cover_image_path')
                            ->label('Cover Artwork (Optional)')
                            ->image()
                            ->disk('public')
                            ->directory('profile-music/covers')
                            ->columnSpanFull()
                            ->helperText('Album art or track thumbnail displayed on student profiles.'),
                    ]),
            ]);
    }
}
