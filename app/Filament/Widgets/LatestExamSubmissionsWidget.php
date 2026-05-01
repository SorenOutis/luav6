<?php

namespace App\Filament\Widgets;

use App\Models\ExamSubmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestExamSubmissionsWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Latest Exam Submissions';

    protected ?string $pollingInterval = '30s';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => ExamSubmission::query()
                    ->with(['user:id,name,email', 'exam:id,title', 'examPart:id,title'])
                    ->latest()
            )
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->description(fn ($record) => $record->user?->email)
                    ->wrap(),
                Tables\Columns\TextColumn::make('exam.title')
                    ->label('Exam')
                    ->searchable()
                    ->description(fn ($record) => $record->examPart?->title)
                    ->wrap(),
                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->numeric(decimalPlaces: 2)
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        (float) $state >= 80 => 'success',
                        (float) $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable(),
            ]);
    }
}
