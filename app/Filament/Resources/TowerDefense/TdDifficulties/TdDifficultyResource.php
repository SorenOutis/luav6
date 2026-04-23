<?php

namespace App\Filament\Resources\TowerDefense\TdDifficulties;

use App\Filament\Resources\TowerDefense\TdDifficulties\Pages\CreateTdDifficulty;
use App\Filament\Resources\TowerDefense\TdDifficulties\Pages\EditTdDifficulty;
use App\Filament\Resources\TowerDefense\TdDifficulties\Pages\ListTdDifficulties;
use App\Models\TowerDefense\TdDifficulty;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TdDifficultyResource extends Resource
{
    protected static ?string $model = TdDifficulty::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFire;

    protected static ?string $navigationLabel = 'TD · Difficulties';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Games';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => filled($get('slug')) ? null : $set('slug', Str::slug($state))),
            TextInput::make('slug')->required()->unique(ignoreRecord: true),
            TextInput::make('order')->numeric()->default(0),
            TextInput::make('starting_gold')->numeric()->required()->default(150),
            TextInput::make('starting_lives')->numeric()->required()->default(20),
            TextInput::make('enemy_hp_mult')->numeric()->step(0.05)->default(1.0),
            TextInput::make('enemy_speed_mult')->numeric()->step(0.05)->default(1.0),
            TextInput::make('gold_mult')->numeric()->step(0.05)->default(1.0),
            TextInput::make('score_mult')->numeric()->step(0.05)->default(1.0),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('starting_gold'),
            TextColumn::make('starting_lives'),
            TextColumn::make('enemy_hp_mult')->label('HP x'),
            TextColumn::make('enemy_speed_mult')->label('Speed x'),
            TextColumn::make('score_mult')->label('Score x'),
        ])->defaultSort('order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTdDifficulties::route('/'),
            'create' => CreateTdDifficulty::route('/create'),
            'edit' => EditTdDifficulty::route('/{record}/edit'),
        ];
    }
}
