<?php

namespace App\Filament\Resources\TowerDefense\TdEnemies;

use App\Filament\Resources\TowerDefense\TdEnemies\Pages\CreateTdEnemy;
use App\Filament\Resources\TowerDefense\TdEnemies\Pages\EditTdEnemy;
use App\Filament\Resources\TowerDefense\TdEnemies\Pages\ListTdEnemies;
use App\Models\TowerDefense\TdEnemy;
use BackedEnum;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\KeyValue;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TdEnemyResource extends Resource
{
    protected static ?string $model = TdEnemy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBugAnt;

    protected static ?string $navigationLabel = 'Enemies';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Tower Defense';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->maxLength(255)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => filled($get('slug')) ? null : $set('slug', Str::slug($state))),
            TextInput::make('slug')->required()->maxLength(255)->unique(ignoreRecord: true),
            TextInput::make('hp')->numeric()->required()->default(100),
            TextInput::make('speed')->numeric()->step(0.1)->required()->default(1.5)->helperText('Tiles per second'),
            TextInput::make('armor')->numeric()->default(0),
            TextInput::make('damage')->numeric()->default(1)->helperText('Lives lost if it leaks'),
            TextInput::make('bounty')->numeric()->default(10)->helperText('Gold on kill'),
            TextInput::make('score')->numeric()->default(10),
            TextInput::make('radius')->numeric()->default(14),
            ColorPicker::make('color')->default('#ef4444'),
            KeyValue::make('abilities')->columnSpanFull()->keyLabel('Ability')->valueLabel('Value')
                ->helperText('e.g. flying=true, regen=2'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('slug')->toggleable(),
            TextColumn::make('hp')->sortable(),
            TextColumn::make('speed')->sortable(),
            TextColumn::make('bounty')->sortable(),
            ColorColumn::make('color'),
        ])->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTdEnemies::route('/'),
            'create' => CreateTdEnemy::route('/create'),
            'edit' => EditTdEnemy::route('/{record}/edit'),
        ];
    }
}
