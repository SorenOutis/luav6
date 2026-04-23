<?php

namespace App\Filament\Resources\TowerDefense\TdRuns;

use App\Filament\Resources\TowerDefense\TdRuns\Pages\ListTdRuns;
use App\Models\TowerDefense\TdRun;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class TdRunResource extends Resource
{
    protected static ?string $model = TdRun::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $navigationLabel = 'Runs & Leaderboard';

    protected static string|\UnitEnum|null $navigationGroup = 'Tower Defense';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([]);
    }

    public static function table(Table $table): Table
    {
        return $table->defaultSort('score', 'desc')
            ->columns([
                TextColumn::make('user.name')->label('Player')->searchable()->sortable(),
                TextColumn::make('level.name')->label('Level')->searchable()->sortable(),
                TextColumn::make('status')->badge()->color(fn (string $state): string => match ($state) {
                    'win' => 'success',
                    'lose' => 'danger',
                    'abandoned' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('waves_completed')->sortable()->label('Waves'),
                TextColumn::make('score')->sortable(),
                TextColumn::make('lives_remaining')->sortable()->label('Lives'),
                TextColumn::make('duration_ms')->label('Duration (s)')->formatStateUsing(fn ($state) => number_format($state / 1000, 1)),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('td_level_id')->relationship('level', 'name')->label('Level'),
                SelectFilter::make('status')->options([
                    'in_progress' => 'In Progress',
                    'win' => 'Win',
                    'lose' => 'Lose',
                    'abandoned' => 'Abandoned',
                ]),
            ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTdRuns::route('/'),
        ];
    }
}
