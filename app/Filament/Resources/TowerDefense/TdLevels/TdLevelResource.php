<?php

namespace App\Filament\Resources\TowerDefense\TdLevels;

use App\Filament\Resources\TowerDefense\TdLevels\Pages\CreateTdLevel;
use App\Filament\Resources\TowerDefense\TdLevels\Pages\EditTdLevel;
use App\Filament\Resources\TowerDefense\TdLevels\Pages\ListTdLevels;
use App\Models\TowerDefense\TdEnemy;
use App\Models\TowerDefense\TdLevel;
use App\Models\TowerDefense\TdTower;
use BackedEnum;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class TdLevelResource extends Resource
{
    protected static ?string $model = TdLevel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'TD · Levels';

    protected static ?string $recordTitleAttribute = 'name';

    protected static string|\UnitEnum|null $navigationGroup = 'Games';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->required()->columnSpan(1)->live(onBlur: true)
                ->afterStateUpdated(fn ($state, $set, $get) => filled($get('slug')) ? null : $set('slug', Str::slug($state))),
            TextInput::make('slug')->required()->unique(ignoreRecord: true)->columnSpan(1),
            Textarea::make('description')->columnSpanFull()->rows(2),
            Select::make('td_map_id')->relationship('map', 'name')->required()->label('Map'),
            Select::make('td_difficulty_id')->relationship('difficulty', 'name')->required()->label('Difficulty'),
            TextInput::make('starting_gold_override')->numeric()->label('Gold Override')
                ->helperText('Leave blank to use difficulty default'),
            TextInput::make('starting_lives_override')->numeric()->label('Lives Override')
                ->helperText('Leave blank to use difficulty default'),
            Select::make('allowed_tower_ids')->label('Allowed Towers')
                ->multiple()
                ->options(fn () => TdTower::pluck('name', 'id')->toArray())
                ->helperText('Leave empty to allow all towers')
                ->columnSpanFull(),
            TextInput::make('reward_score')->numeric()->default(100)->label('Bonus Score on Win'),
            TextInput::make('order')->numeric()->default(0),
            Toggle::make('is_published')->default(false)->columnSpanFull(),

            Section::make('Waves')
                ->description('Each wave contains one or more spawn groups. Spawns are concurrent; use offset_ms to stagger them within a wave.')
                ->columnSpanFull()
                ->schema([
                    Repeater::make('waves')
                        ->relationship('waves')
                        ->columns(2)
                        ->orderColumn('order')
                        ->schema([
                            TextInput::make('spawn_delay_ms')->numeric()->default(3000)->label('Delay (ms)'),
                            TextInput::make('bonus_gold')->numeric()->default(25)->label('Bonus Gold'),
                            Repeater::make('spawns')
                                ->relationship('spawns')
                                ->columns(5)
                                ->orderColumn('order')
                                ->schema([
                                    Select::make('td_enemy_id')->relationship('enemy', 'name')->required()->label('Enemy')->columnSpan(2),
                                    TextInput::make('count')->numeric()->required()->default(10),
                                    TextInput::make('interval_ms')->numeric()->default(800)->label('Interval (ms)'),
                                    TextInput::make('offset_ms')->numeric()->default(0)->label('Offset (ms)'),
                                ])
                                ->itemLabel(fn (array $state): ?string => isset($state['td_enemy_id'])
                                    ? (TdEnemy::find($state['td_enemy_id'])?->name ?? 'Spawn').' × '.($state['count'] ?? '?')
                                    : 'New Spawn Group')
                                ->addActionLabel('Add Spawn Group')
                                ->collapsible()
                                ->columnSpanFull(),
                        ])
                        ->itemLabel(function (string $uuid, Repeater $component): string {
                            $items = $component->getState();
                            $keys = array_keys($items);
                            $idx = array_search($uuid, $keys, true);
                            $n = $idx === false ? count($keys) + 1 : $idx + 1;

                            return "Wave {$n}";
                        })
                        ->addActionLabel('Add Wave')
                        ->collapsible(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('order')->sortable()->label('#'),
            TextColumn::make('name')->searchable()->sortable(),
            TextColumn::make('map.name')->label('Map'),
            TextColumn::make('difficulty.name')->badge(),
            TextColumn::make('waves_count')->counts('waves')->label('Waves'),
            IconColumn::make('is_published')->boolean(),
        ])->defaultSort('order');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTdLevels::route('/'),
            'create' => CreateTdLevel::route('/create'),
            'edit' => EditTdLevel::route('/{record}/edit'),
        ];
    }
}
