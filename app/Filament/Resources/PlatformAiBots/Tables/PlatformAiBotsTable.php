<?php

namespace App\Filament\Resources\PlatformAiBots\Tables;

use App\Models\AiBot;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class PlatformAiBotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workspace.name')
                    ->label('Workspace')
                    ->placeholder('Platform')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('kind')
                    ->badge()
                    ->color(fn (string $state): string => $state === AiBot::KIND_COORDINATOR ? 'warning' : 'gray')
                    ->formatStateUsing(fn (string $state): string => $state === AiBot::KIND_COORDINATOR
                        ? 'Coordinator'
                        : 'Specialist'),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->description(fn (AiBot $record): ?string => $record->title),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('allowed_tools')
                    ->label('Tools')
                    ->badge()
                    ->state(fn (AiBot $record): int => count($record->allowed_tools)),
                TextColumn::make('drift')
                    ->label('Drift')
                    ->badge()
                    ->state(fn (AiBot $record): int => count($record->driftWarnings()))
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success')
                    ->formatStateUsing(fn (int $state): string => $state > 0 ? (string) $state : 'Clean'),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                SelectFilter::make('workspace_id')
                    ->label('Workspace')
                    ->relationship('workspace', 'name')
                    ->searchable()
                    ->preload(),
                SelectFilter::make('kind')
                    ->options([
                        AiBot::KIND_COORDINATOR => 'Coordinator',
                        AiBot::KIND_SPECIALIST => 'Specialist',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Active'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleActive')
                    ->label(fn (AiBot $record): string => $record->is_active ? 'Deactivate' : 'Activate')
                    ->icon(fn (AiBot $record): string => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (AiBot $record): string => $record->is_active ? 'warning' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (AiBot $record): string => $record->is_active ? 'Deactivate bot?' : 'Activate bot?')
                    ->modalDescription('Inactive bots disappear from the picker and reject new handoffs. Active writes still require approval.')
                    ->action(function (AiBot $record): void {
                        try {
                            $record->update(['is_active' => ! $record->is_active]);
                        } catch (ValidationException $exception) {
                            Notification::make()
                                ->title('Could not change status')
                                ->body(collect($exception->errors())->flatten()->first())
                                ->danger()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->title($record->is_active ? 'Bot activated' : 'Bot deactivated')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('workspace_id');
    }
}
