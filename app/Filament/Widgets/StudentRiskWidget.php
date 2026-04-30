<?php

namespace App\Filament\Widgets;

use App\Models\User;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class StudentRiskWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = [
        'md' => 6,
        'xl' => 5,
    ];

    protected static ?string $heading = 'Students At Risk';

    protected static ?string $description = 'Students who may need attention.';

    protected ?string $pollingInterval = '60s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => User::query()
                    ->where('is_admin', false)
                    ->where('is_banned', false)
                    ->where(function ($query) {
                        $query
                            ->where('last_login_at', '<=', now()->subDays(7))
                            ->orWhereNull('last_login_at')
                            ->orWhere('exp', '<', 50)
                            ->orWhereNotExists(function ($sub) {
                                $sub->select(DB::raw(1))
                                    ->from('exam_submissions')
                                    ->whereColumn('exam_submissions.user_id', 'users.id');
                            });
                    })
            )
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Student')
                    ->searchable()
                    ->description(fn (User $record) => $record->email)
                    ->wrap(),

                Tables\Columns\TextColumn::make('days_inactive')
                    ->label('Days Inactive')
                    ->getStateUsing(function (User $record) {
                        if (! $record->last_login_at) {
                            return 'Never';
                        }
                        $days = $record->last_login_at->diffInDays(now());

                        return $days >= 14 ? "{$days} days" : "{$days}d";
                    })
                    ->sortable()
                    ->color(function (User $record) {
                        if (! $record->last_login_at) {
                            return 'danger';
                        }
                        $days = $record->last_login_at->diffInDays(now());

                        return $days >= 14 ? 'danger' : ($days >= 7 ? 'warning' : null);
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('exp')
                    ->label('XP')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('submissions')
                    ->label('Submissions')
                    ->getStateUsing(function (User $record) {
                        return DB::table('exam_submissions')
                            ->where('user_id', $record->id)
                            ->count();
                    })
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color('primary')
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('sections.name')
                    ->label('Section')
                    ->badge()
                    ->color('gray')
                    ->wrap()
                    ->limit(15),

                Tables\Columns\TextColumn::make('risk_level')
                    ->label('Risk')
                    ->badge()
                    ->getStateUsing(function (User $record) {
                        $inactive = $record->last_login_at ? $record->last_login_at->diffInDays(now()) : 999;
                        if ($inactive >= 14) {
                            return 'Critical';
                        }
                        if ($inactive >= 7 || $record->exp < 50) {
                            return 'Warning';
                        }

                        return 'Low';
                    })
                    ->color(fn ($state) => match ($state) {
                        'Critical' => 'danger',
                        'Warning' => 'warning',
                        default => 'info',
                    })
                    ->alignCenter(),
            ]);
    }
}
