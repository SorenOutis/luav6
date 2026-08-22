<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Models\Section;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Read-only XP ledger for a single student.
 *
 * Every XP movement is already recorded by User::recordGamificationHistory(),
 * so this manager only reads it back: the ledger is an audit trail and must not
 * be editable from the panel. Admins adjust XP through the Gamification tab on
 * the edit form or the "Award XP" action, both of which append a row here.
 */
class XpHistoryRelationManager extends RelationManager
{
    protected static string $relationship = 'gamificationHistories';

    protected static ?string $recordTitleAttribute = 'reason';

    protected static ?string $title = 'XP History';

    /** Total XP earned, so the tab is readable without opening it. */
    public static function getBadge(Model $ownerRecord, string $pageClass): ?string
    {
        $total = (float) static::scopeToVisibleWorkspaces($ownerRecord->gamificationHistories())
            ->sum('amount_xp');

        return $total === 0.0 ? null : number_format($total, 0).' XP';
    }

    /**
     * A student may be enrolled in sections owned by more than one tenant.
     * `whereHas('section')` runs Section's workspace global scope, so a
     * co-admin only sees the rows their workspace owns while a super admin
     * (whose scope is a no-op) still sees all of them. Section-less rows are
     * user-global — daily claims, season rewards — and stay visible.
     *
     * @template TQuery of Builder|HasMany
     *
     * @param  TQuery  $query
     * @return TQuery
     */
    protected static function scopeToVisibleWorkspaces($query)
    {
        return $query->where(fn (Builder $nested): Builder => $nested
            ->whereNull('section_id')
            ->orWhereHas('section'));
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => static::scopeToVisibleWorkspaces(
                $query->with([
                    'section:id,name',
                    'season:id,name',
                    'awardedBy:id,name',
                ])
            ))
            ->columns([
                TextColumn::make('created_at')
                    ->label('Earned At')
                    ->dateTime('M d, Y h:i A')
                    ->description(fn ($record): string => $record->created_at?->diffForHumans() ?? '')
                    ->sortable(),
                TextColumn::make('amount_xp')
                    ->label('XP')
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (float) $state > 0 => 'success',
                        (float) $state < 0 => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => ((float) $state > 0 ? '+' : '')
                        .number_format((float) $state, 2))
                    ->sortable()
                    ->summarize(Sum::make()->label('Total XP')->numeric(decimalPlaces: 2)),
                TextColumn::make('reason')
                    ->label('Reason')
                    ->badge()
                    ->color(fn (?string $state): string => match ($state) {
                        'Daily Claim', 'Bonus Claim' => 'info',
                        'Teacher Award' => 'success',
                        'Admin Adjustment' => 'warning',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),
                TextColumn::make('description')
                    ->label('Details')
                    ->placeholder('—')
                    ->limit(50)
                    ->tooltip(fn ($record): ?string => $record->description)
                    ->searchable()
                    ->wrap(),
                TextColumn::make('section.name')
                    ->label('Section')
                    ->placeholder('All sections')
                    ->sortable(),
                TextColumn::make('awardedBy.name')
                    ->label('Awarded By')
                    ->placeholder('System')
                    ->toggleable(),
                TextColumn::make('amount_points')
                    ->label('Points')
                    ->numeric(decimalPlaces: 2)
                    ->placeholder('0.00')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->summarize(Sum::make()->label('Total points')->numeric(decimalPlaces: 2)),
                TextColumn::make('season.name')
                    ->label('Season')
                    ->placeholder('No season')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                // Point-only rows (e.g. section point rewards) would otherwise
                // show up as "0.00 XP" noise in an XP ledger.
                Filter::make('xp_only')
                    ->label('XP entries only')
                    ->default()
                    ->query(fn (Builder $query): Builder => $query->where('amount_xp', '!=', 0)),
                SelectFilter::make('reason')
                    ->label('Reason')
                    ->options(fn (): array => $this->getOwnerRecord()
                        ->gamificationHistories()
                        ->select('reason')
                        ->distinct()
                        ->orderBy('reason')
                        ->pluck('reason', 'reason')
                        ->all()),
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->options(fn (): array => Section::query()
                        ->whereIn('id', $this->getOwnerRecord()->gamificationHistories()->select('section_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all()),
                SelectFilter::make('period')
                    ->label('Period')
                    ->options([
                        '7' => 'Last 7 days',
                        '30' => 'Last 30 days',
                        '90' => 'Last 90 days',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        $data['value'] ?? null,
                        fn (Builder $query, $days): Builder => $query
                            ->where('created_at', '>=', now()->subDays((int) $days)),
                    )),
            ])
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->emptyStateIcon('heroicon-o-sparkles')
            ->emptyStateHeading('No XP recorded yet')
            ->emptyStateDescription('XP earned from exams, assignments, claims, and teacher awards will appear here.');
    }
}
