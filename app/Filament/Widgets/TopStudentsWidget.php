<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopStudentsWidget extends BaseWidget
{
    protected static ?int $sort = 4;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Top Students by XP';

    protected ?string $pollingInterval = '60s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => User::query()
                    ->where('is_admin', false)
                    ->where('is_banned', false)
                    ->orderByDesc('exp')
                    ->orderByDesc('level')
            )
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Student')
                    ->searchable()
                    ->description(fn (User $record) => $record->email)
                    ->wrap(),
                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                Tables\Columns\TextColumn::make('exp')
                    ->label('XP')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('points')
                    ->label('Points')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Active')
                    ->since()
                    ->placeholder('Never')
                    ->sortable(),
            ]);
    }
}
