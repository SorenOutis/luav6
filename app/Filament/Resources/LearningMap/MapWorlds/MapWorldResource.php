<?php

namespace App\Filament\Resources\LearningMap\MapWorlds;

use App\Filament\Resources\LearningMap\MapWorlds\Pages\CreateMapWorld;
use App\Filament\Resources\LearningMap\MapWorlds\Pages\EditMapWorld;
use App\Filament\Resources\LearningMap\MapWorlds\Pages\ListMapWorlds;
use App\Models\LearningMap\MapWorld;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class MapWorldResource extends Resource
{
    protected static ?string $model = MapWorld::class;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();
        return ! ($user && $user->is_admin && ! $user->isSuperAdmin());
    }


    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning Map';

    protected static ?string $navigationLabel = 'Worlds';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => filled($get('slug')) ? null : $set('slug', Str::slug($state))),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            TextInput::make('sort_order')->numeric()->default(0),
            ColorPicker::make('primary_color')->default('#10b981'),
            ColorPicker::make('accent_color')->default('#34d399'),
            TextInput::make('background_class')
                ->label('Background Class')
                ->helperText('Tailwind class applied to the biome background (e.g. bg-emerald-50/30).')
                ->default('bg-emerald-50/30'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('#')->sortable(),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->toggleable(),
                TextColumn::make('nodes_count')->counts('nodes')->label('Nodes'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMapWorlds::route('/'),
            'create' => CreateMapWorld::route('/create'),
            'edit' => EditMapWorld::route('/{record}/edit'),
        ];
    }
}
