<?php

namespace App\Filament\Resources\CommunityFeatured;

use App\Filament\Resources\CommunityFeatured\Pages\ListCommunityFeatured;
use App\Filament\Resources\CommunityFeatured\Tables\CommunityFeaturedTable;
use App\Models\CommunityProfileFeatured;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Tables\Table;

class CommunityProfileFeaturedResource extends Resource
{
    protected static ?string $model = CommunityProfileFeatured::class;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->is_admin;
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-star';

    protected static ?string $navigationLabel = 'Featured Media';

    protected static string|\UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'Featured Highlight';

    protected static ?string $slug = 'community-featured';

    protected static ?string $recordTitleAttribute = 'title';

    public static function table(Table $table): Table
    {
        return CommunityFeaturedTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityFeatured::route('/'),
        ];
    }
}
