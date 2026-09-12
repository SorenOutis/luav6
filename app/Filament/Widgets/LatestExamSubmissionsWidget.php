<?php

namespace App\Filament\Widgets;

use App\Models\ExamSubmission;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;

class LatestExamSubmissionsWidget extends BaseWidget
{
    protected static ?int $sort = 8;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 6,
    ];

    protected static ?string $heading = 'Latest Exam Submissions';

    protected ?string $pollingInterval = '60s';

    protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => ExamSubmission::query()
                    ->whereHas('exam')
                    ->with(['user:id,name,email', 'exam:id,title', 'examPart:id,title'])
                    ->latest()
            )
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->description(fn ($record) => $record->user?->email)
                    ->wrap()
                    ->weight('medium'),

                Tables\Columns\TextColumn::make('exam.title')
                    ->label('Exam')
                    ->searchable()
                    ->description(fn ($record) => $record->examPart?->title)
                    ->wrap()
                    ->limit(30),

                Tables\Columns\TextColumn::make('score')
                    ->label('Score')
                    ->numeric(decimalPlaces: 1)
                    ->badge()
                    ->color(fn ($state): string => match (true) {
                        $state === null => 'gray',
                        (float) $state >= 80 => 'success',
                        (float) $state >= 50 => 'warning',
                        default => 'danger',
                    })
                    ->formatStateUsing(fn ($state) => $state === null ? 'Pending' : number_format((float) $state, 1).'%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted')
                    ->since()
                    ->sortable()
                    ->tooltip(fn ($record) => $record->created_at?->format('M d, Y g:i A')),
            ])
            ->actions([
                Tables\Actions\Action::make('viewSubmission')
                    ->label('View')
                    ->icon('heroicon-m-eye')
                    ->url(fn (ExamSubmission $record) => "/admin/exam-submissions/{$record->id}/edit")
                    ->openUrlInNewTab(false),
            ])
            ->emptyStateHeading('No submissions yet')
            ->emptyStateDescription('Exam submissions will appear here once students start taking exams.')
            ->emptyStateIcon('heroicon-o-document-chart-bar');
    }
}
