<?php

namespace App\Filament\Resources\CommunityStories\Pages;

use App\Filament\Resources\CommunityStories\CommunityStoryResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunityStories extends ListRecords
{
    protected static string $resource = CommunityStoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
