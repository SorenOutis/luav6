<?php

namespace App\Filament\Widgets;

use App\Models\Assignment;
use App\Models\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SectionComparisonWidget extends BaseWidget
{
    protected static ?int $sort = 10;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 6,
    ];

    protected static ?string $heading = 'Section Comparison';

    protected static ?string $description = 'Performance overview per section — no N+1 queries.';

    protected ?string $pollingInterval = '120s';

    protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => Section::query()
                    ->withCount('users')
                    ->withCount('exams')
                    ->withAvg('progress as avg_exp', 'exp')
                    ->withAvg('progress as avg_level', 'level')
                    ->withCount([
                        'users as active_today_count' => fn (Builder $q) => $q
                            ->whereNotNull('last_login_at')
                            ->where('last_login_at', '>=', now()->startOfDay()),
                    ])
                    ->orderBy('users_count', 'desc')
            )
            ->paginated([10, 25])
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Section')
                    ->searchable()
                    ->weight('bold')
                    ->wrap()
                    ->limit(28),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Students')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('avg_exp')
                    ->label('Avg XP')
                    ->numeric(decimalPlaces: 0)
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('avg_level')
                    ->label('Avg Lvl')
                    ->numeric(decimalPlaces: 1)
                    ->sortable()
                    ->alignCenter()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('active_today_count')
                    ->label('Active Today')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn ($state) => $state > 0 ? 'success' : 'gray'),

                Tables\Columns\TextColumn::make('submission_rate')
                    ->label('Submission Rate')
                    ->state(function (Section $record): string {
                        static $cache = [];

                        if (isset($cache[$record->id])) {
                            return $cache[$record->id];
                        }

                        $assignmentIds = Assignment::query()->select('id')->where('status', '!=', 'draft');

                        $total = DB::table('assignment_user')
                            ->join('users', 'assignment_user.user_id', '=', 'users.id')
                            ->whereIn('assignment_user.assignment_id', $assignmentIds)
                            ->join('section_user', 'users.id', '=', 'section_user.user_id')
                            ->where('section_user.section_id', $record->id)
                            ->count();

                        $submitted = DB::table('assignment_user')
                            ->join('users', 'assignment_user.user_id', '=', 'users.id')
                            ->whereIn('assignment_user.assignment_id', $assignmentIds)
                            ->join('section_user', 'users.id', '=', 'section_user.user_id')
                            ->where('section_user.section_id', $record->id)
                            ->where('assignment_user.submitted', true)
                            ->count();

                        $result = $total > 0 ? round(($submitted / $total) * 100, 1).'%' : '—';
                        $cache[$record->id] = $result;

                        return $result;
                    })
                    ->badge()
                    ->color(fn ($state) => match (true) {
                        $state === '—' => 'gray',
                        (float) rtrim($state, '%') >= 80 => 'success',
                        (float) rtrim($state, '%') >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('exams_count')
                    ->label('Exams')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->url(fn (Section $record) => "/admin/sections/{$record->id}/edit"),
            ])
            ->emptyStateHeading('No sections yet')
            ->emptyStateIcon('heroicon-o-rectangle-group');
    }
}
