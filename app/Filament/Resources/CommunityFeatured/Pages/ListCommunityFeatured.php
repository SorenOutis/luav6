<?php

namespace App\Filament\Resources\CommunityFeatured\Pages;

use App\Filament\Resources\CommunityFeatured\CommunityProfileFeaturedResource;
use Filament\Resources\Pages\ListRecords;

class ListCommunityFeatured extends ListRecords
{
    protected static string $resource = CommunityProfileFeaturedResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
