<?php

namespace App\Filament\Resources\SupportTickets\Tables;

use App\Filament\Support\WorkspaceTable;
use App\Models\SupportTicket;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class SupportTicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                WorkspaceTable::column(),
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->limit(40)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->subject),
                TextColumn::make('category')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SupportTicket::CATEGORIES[$state] ?? $state)
                    ->sortable(),
                TextColumn::make('priority')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'high' => 'danger',
                        'medium' => 'warning',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => SupportTicket::STATUSES[$state] ?? $state)
                    ->color(fn (string $state): string => match ($state) {
                        'open' => 'danger',
                        'in_progress' => 'warning',
                        'resolved', 'closed' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                TextColumn::make('resolver.name')
                    ->label('Resolved by')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('resolved_at')
                    ->label('Resolved at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                WorkspaceTable::filter(),
                SelectFilter::make('status')
                    ->options(SupportTicket::STATUSES),
                SelectFilter::make('category')
                    ->options(SupportTicket::CATEGORIES),
                SelectFilter::make('priority')
                    ->options(SupportTicket::PRIORITIES),
            ])
            ->actions([
                Action::make('startProgress')
                    ->label('Start progress')
                    ->icon('heroicon-o-play')
                    ->color('warning')
                    ->visible(fn ($record) => $record->status === SupportTicket::STATUS_OPEN)
                    ->action(fn ($record) => $record->update(['status' => SupportTicket::STATUS_IN_PROGRESS])),
                Action::make('resolve')
                    ->label('Resolve')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn ($record) => ! $record->isResolved())
                    ->action(fn ($record) => $record->markResolved(auth()->user(), SupportTicket::STATUS_RESOLVED)),
                Action::make('close')
                    ->label('Close')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->visible(fn ($record) => ! $record->isResolved())
                    ->action(fn ($record) => $record->markResolved(auth()->user(), SupportTicket::STATUS_CLOSED)),
                Action::make('reopen')
                    ->label('Reopen')
                    ->icon('heroicon-o-arrow-path')
                    ->color('info')
                    ->visible(fn ($record) => $record->isResolved())
                    ->action(fn ($record) => $record->update([
                        'status' => SupportTicket::STATUS_OPEN,
                        'resolved_by' => null,
                        'resolved_at' => null,
                    ])),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
