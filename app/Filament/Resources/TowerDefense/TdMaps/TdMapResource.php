<?php

namespace App\Filament\Resources\TowerDefense\TdMaps;

use App\Filament\Resources\TowerDefense\TdMaps\Pages\CreateTdMap;
use App\Filament\Resources\TowerDefense\TdMaps\Pages\EditTdMap;
use App\Filament\Resources\TowerDefense\TdMaps\Pages\ListTdMaps;
use App\Models\TowerDefense\TdMap;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TdMapResource extends Resource
{
    protected static ?string $model = TdMap::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMap;

    protected static ?string $navigationLabel = 'TD · Maps';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Games';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => filled($get('slug')) ? null : $set('slug', Str::slug($state))),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            TextInput::make('grid_width')->numeric()->required()->default(20),
            TextInput::make('grid_height')->numeric()->required()->default(12),
            TextInput::make('tile_size')->numeric()->default(48)->helperText('Pixels per tile'),
            ColorPicker::make('background_color')->default('#0a0a0a'),
            Section::make('Path Waypoints')
                ->description('Ordered list of [x, y] grid coordinates. Enemies walk in order from first to last.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('path_waypoints')->label('Waypoints')->columns(2)
                        ->schema([
                            TextInput::make('0')->label('X')->numeric()->required(),
                            TextInput::make('1')->label('Y')->numeric()->required(),
                        ])->addActionLabel('Add Waypoint')->reorderable(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('slug')->toggleable(),
            TextColumn::make('grid_width'),
            TextColumn::make('grid_height'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTdMaps::route('/'),
            'create' => CreateTdMap::route('/create'),
            'edit' => EditTdMap::route('/{record}/edit'),
        ];
    }
}
