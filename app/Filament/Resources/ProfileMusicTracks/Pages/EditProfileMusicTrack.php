<?php

namespace App\Filament\Resources\ProfileMusicTracks\Pages;

use App\Filament\Resources\ProfileMusicTracks\ProfileMusicTrackResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditProfileMusicTrack extends EditRecord
{
    protected static string $resource = ProfileMusicTrackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
