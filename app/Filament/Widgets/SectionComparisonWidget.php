<?php

namespace App\Filament\Widgets;

use App\Models\Section;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class SectionComparisonWidget extends BaseWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Section Comparison';

    protected ?string $pollingInterval = '60s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => Section::query()
                    ->withCount('users')
                    ->withCount('exams')
                    ->withAvg('progress', 'exp')
                    ->withAvg('progress', 'level')
                    ->orderBy('users_count', 'desc')
            )
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(10)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Section')
                    ->searchable()
                    ->weight('bold')
                    ->wrap(),

                Tables\Columns\TextColumn::make('users_count')
                    ->label('Students')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('avg_exp')
                    ->label('Avg XP')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('avg_level')
                    ->label('Avg Level')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('active_today')
                    ->label('Active Today')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->getStateUsing(function ($record) {
                        return $record->users()
                            ->whereNotNull('last_login_at')
                            ->where('last_login_at', '>=', now()->startOfDay())
                            ->count();
                    }),

                Tables\Columns\TextColumn::make('submission_rate')
                    ->label('Submission Rate')
                    ->getStateUsing(function ($record) {
                        $total = DB::table('assignment_user')
                            ->join('users', 'assignment_user.user_id', '=', 'users.id')
                            ->join('section_user', 'users.id', '=', 'section_user.user_id')
                            ->where('section_user.section_id', $record->id)
                            ->count();

                        $submitted = DB::table('assignment_user')
                            ->join('users', 'assignment_user.user_id', '=', 'users.id')
                            ->join('section_user', 'users.id', '=', 'section_user.user_id')
                            ->where('section_user.section_id', $record->id)
                            ->where('submitted', true)
                            ->count();

                        return $total > 0 ? round(($submitted / $total) * 100, 1).'%' : 'N/A';
                    })
                    ->alignCenter(),

                Tables\Columns\TextColumn::make('exams_count')
                    ->label('Exams')
                    ->numeric()
                    ->sortable()
                    ->alignCenter(),
            ]);
    }
}
