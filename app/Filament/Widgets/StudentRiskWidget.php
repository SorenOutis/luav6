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
    protected static ?int $sort = 11;

    protected int|string|array $columnSpan = 12;

    protected static ?string $heading = 'Students At Risk';

    protected static ?string $description = 'Students who may need attention — inactive, low XP, or no submissions.';

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
                    // Avoid N+1: preload counts and sections in single query
                    ->withCount([
                        'examSubmissions as submissions_count',
                        'sections',
                    ])
                    ->with(['sections:id,name'])
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
                    ->wrap()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('days_inactive')
                    ->label('Inactive')
                    ->state(function (User $record): string {
                        if (! $record->last_login_at) {
                            return 'Never logged in';
                        }
                        $days = $record->last_login_at->diffInDays(now());

                        return $days === 0 ? 'Today' : ($days === 1 ? '1 day' : "{$days} days");
                    })
                    ->badge()
                    ->color(function (User $record) {
                        if (! $record->last_login_at) {
                            return 'danger';
                        }
                        $days = $record->last_login_at->diffInDays(now());

                        return $days >= 14 ? 'danger' : ($days >= 7 ? 'warning' : 'gray');
                    })
                    ->sortable(query: fn (Builder $q, string $direction) => $q->orderBy('last_login_at', $direction))
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('exp')
                    ->label('XP')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state < 50 ? 'danger' : ($state < 150 ? 'warning' : 'success')),

                Tables\Columns\TextColumn::make('submissions_count')
                    ->label('Submissions')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state === 0 ? 'danger' : 'gray'),

                Tables\Columns\TextColumn::make('level')
                    ->badge()
                    ->color('primary')
                    ->alignCenter()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sections.name')
                    ->label('Section')
                    ->badge()
                    ->color('gray')
                    ->listWithLineBreaks()
                    ->limitList(2)
                    ->expandableLimitedList(),

                Tables\Columns\TextColumn::make('risk_level')
                    ->label('Risk')
                    ->badge()
                    ->state(function (User $record): string {
                        $inactive = $record->last_login_at ? $record->last_login_at->diffInDays(now()) : 999;
                        if ($inactive >= 14 || $record->exp < 20) {
                            return 'Critical';
                        }
                        if ($inactive >= 7 || $record->exp < 50 || $record->submissions_count === 0) {
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
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->url(fn (User $record) => "/admin/users/{$record->id}/edit"),

                Tables\Actions\Action::make('impersonate')
                    ->label('Impersonate')
                    ->icon('heroicon-m-user-circle')
                    ->color('gray')
                    ->visible(fn (User $record) => $record->canBeImpersonated())
                    ->url(fn (User $record) => route('impersonate', $record)),
            ])
            ->emptyStateHeading('No at-risk students')
            ->emptyStateDescription('All students are active and engaged. Great job!')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
