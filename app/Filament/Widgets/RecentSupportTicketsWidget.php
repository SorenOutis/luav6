<?php

namespace App\Filament\Widgets;

use App\Models\SupportTicket;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class RecentSupportTicketsWidget extends BaseWidget
{
    protected static ?int $sort = 13;

    protected int|string|array $columnSpan = [
        'default' => 12,
        'lg' => 6,
    ];

    protected static ?string $heading = 'Recent Support Tickets';

    protected static ?string $description = 'Latest open and in-progress tickets needing attention.';

    protected ?string $pollingInterval = '60s';

    protected static bool $isLazy = true;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn (): Builder => SupportTicket::query()
                    ->whereIn('status', [SupportTicket::STATUS_OPEN, SupportTicket::STATUS_IN_PROGRESS])
                    ->with(['user:id,name,email'])
                    ->latest()
            )
            ->paginated([5, 10])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('subject')
                    ->label('Subject')
                    ->searchable()
                    ->wrap()
                    ->limit(40)
                    ->weight('medium')
                    ->description(fn (SupportTicket $record) => Str::limit($record->message, 60)),

                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->description(fn ($record) => $record->user?->email)
                    ->wrap(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'technical' => 'danger',
                        'account' => 'warning',
                        'feature' => 'info',
                        'billing' => 'success',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => SupportTicket::CATEGORIES[$state] ?? $state),

                Tables\Columns\TextColumn::make('priority')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        'low' => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state) => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'resolved' => 'success',
                        'closed' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state) => SupportTicket::STATUSES[$state] ?? $state),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Opened')
                    ->since()
                    ->sortable()
                    ->tooltip(fn ($record) => $record->created_at?->format('M d, Y g:i A')),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->icon('heroicon-m-eye')
                    ->url(fn (SupportTicket $record) => "/admin/support-tickets/{$record->id}/edit"),
            ])
            ->emptyStateHeading('No open tickets')
            ->emptyStateDescription('All support tickets are resolved. Great job!')
            ->emptyStateIcon('heroicon-o-check-circle');
    }
}
