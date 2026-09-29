<?php

namespace App\Filament\Resources\ProfileMusicTracks;

use App\Filament\Resources\ProfileMusicTracks\Pages\CreateProfileMusicTrack;
use App\Filament\Resources\ProfileMusicTracks\Pages\EditProfileMusicTrack;
use App\Filament\Resources\ProfileMusicTracks\Pages\ListProfileMusicTracks;
use App\Filament\Resources\ProfileMusicTracks\Schemas\ProfileMusicTrackForm;
use App\Filament\Resources\ProfileMusicTracks\Tables\ProfileMusicTracksTable;
use App\Models\ProfileMusicTrack;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ProfileMusicTrackResource extends Resource
{
    protected static ?string $model = ProfileMusicTrack::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-musical-note';

    protected static string|\UnitEnum|null $navigationGroup = 'Gamification';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?string $navigationLabel = 'Profile Music';

    protected static ?string $modelLabel = 'Profile Music Track';

    protected static ?string $pluralModelLabel = 'Profile Music Tracks';

    public static function form(Schema $schema): Schema
    {
        return ProfileMusicTrackForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProfileMusicTracksTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProfileMusicTracks::route('/'),
            'create' => CreateProfileMusicTrack::route('/create'),
            'edit' => EditProfileMusicTrack::route('/{record}/edit'),
        ];
    }
}
