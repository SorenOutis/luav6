<?php

namespace App\Filament\Resources\CommunityStories;

use App\Filament\Resources\CommunityStories\Pages\ListCommunityStories;
use App\Filament\Resources\CommunityStories\Tables\CommunityStoriesTable;
use App\Models\CommunityStory;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class CommunityStoryResource extends Resource
{
    protected static ?string $model = CommunityStory::class;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->is_admin;
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static ?string $navigationLabel = 'Stories (MyDay)';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Story';

    protected static ?string $slug = 'community-stories';

    protected static ?string $recordTitleAttribute = 'caption';

    public static function table(Table $table): Table
    {
        return CommunityStoriesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityStories::route('/'),
        ];
    }
}
