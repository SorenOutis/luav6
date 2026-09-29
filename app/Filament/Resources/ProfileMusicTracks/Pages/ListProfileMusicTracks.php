<?php

namespace App\Filament\Resources\ProfileMusicTracks\Pages;

use App\Filament\Resources\ProfileMusicTracks\ProfileMusicTrackResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListProfileMusicTracks extends ListRecords
{
    protected static string $resource = ProfileMusicTrackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
