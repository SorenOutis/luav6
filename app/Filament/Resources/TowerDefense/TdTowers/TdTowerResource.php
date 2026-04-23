<?php

namespace App\Filament\Resources\TowerDefense\TdTowers;

use App\Filament\Resources\TowerDefense\TdTowers\Pages\CreateTdTower;
use App\Filament\Resources\TowerDefense\TdTowers\Pages\EditTdTower;
use App\Filament\Resources\TowerDefense\TdTowers\Pages\ListTdTowers;
use App\Models\TowerDefense\TdTower;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TdTowerResource extends Resource
{
    protected static ?string $model = TdTower::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBolt;

    protected static ?string $navigationLabel = 'Towers';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Tower Defense';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => filled($get('slug')) ? null : $set('slug', Str::slug($state))),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            TextInput::make('cost')->numeric()->required()->default(50),
            TextInput::make('damage')->numeric()->required()->default(10),
            TextInput::make('range')->numeric()->step(0.1)->default(3)->helperText('Range in tiles'),
            TextInput::make('fire_rate')->numeric()->step(0.1)->default(1)->helperText('Shots per second'),
            Select::make('projectile_type')->options([
                'bullet' => 'Bullet',
                'laser' => 'Laser',
                'splash' => 'Splash (AoE)',
            ])->default('bullet')->required(),
            TextInput::make('splash_radius')->numeric()->step(0.1)->default(0)->helperText('0 = single target'),
            TextInput::make('projectile_speed')->numeric()->step(0.1)->default(8),
            ColorPicker::make('color')->default('#38bdf8'),
            Section::make('Upgrade Tree')->columnSpanFull()->schema([
                Repeater::make('upgrades')->label('Upgrade Tiers')->columns(4)->schema([
                    TextInput::make('cost')->numeric()->required(),
                    TextInput::make('damage')->numeric(),
                    TextInput::make('range')->numeric()->step(0.1),
                    TextInput::make('fire_rate')->numeric()->step(0.1),
                    TextInput::make('splash_radius')->numeric()->step(0.1),
                ])->addActionLabel('Add Tier')->collapsible(),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('cost')->sortable(),
            TextColumn::make('damage')->sortable(),
            TextColumn::make('range'),
            TextColumn::make('fire_rate')->label('Rate'),
            TextColumn::make('projectile_type')->badge(),
            ColorColumn::make('color'),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTdTowers::route('/'),
            'create' => CreateTdTower::route('/create'),
            'edit' => EditTdTower::route('/{record}/edit'),
        ];
    }
}
