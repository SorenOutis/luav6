<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class TopStudentsWidget extends BaseWidget
{
    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 6,
    ];

    protected static ?string $heading = 'Top Students by XP';

    protected static ?string $description = 'Highest performing students in your workspace.';

    protected ?string $pollingInterval = '120s';

    protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => User::query()
                    ->where('is_admin', false)
                    ->where('is_banned', false)
                    ->forWorkspace()
                    ->orderByDesc('exp')
                    ->orderByDesc('level')
            )
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('rank')
                    ->label('#')
                    ->rowIndex()
                    ->badge()
                    ->color(fn (int $rowLoopIndex) => match (true) {
                        $rowLoopIndex === 1 => 'warning',
                        $rowLoopIndex === 2 => 'gray',
                        $rowLoopIndex === 3 => 'primary',
                        default => null,
                    }),

                Tables\Columns\TextColumn::make('name')
                    ->label('Student')
                    ->searchable()
                    ->description(fn (User $record) => $record->email)
                    ->wrap()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color('primary')
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('exp')
                    ->label('XP')
                    ->numeric()
                    ->sortable()
                    ->weight('bold')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('points')
                    ->label('Points')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('current_streak')
                    ->label('Streak')
                    ->badge()
                    ->color(fn ($state) => $state >= 7 ? 'success' : ($state >= 3 ? 'warning' : 'gray'))
                    ->formatStateUsing(fn ($state) => $state ? $state.'d' : '—')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Active')
                    ->since()
                    ->placeholder('Never')
                    ->sortable()
                    ->toggleable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->url(fn (User $record) => "/admin/users/{$record->id}/edit")
                    ->label('View'),
            ])
            ->emptyStateHeading('No students yet')
            ->emptyStateIcon('heroicon-o-users');
    }
}
