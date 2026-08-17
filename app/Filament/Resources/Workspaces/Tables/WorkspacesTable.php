<?php

namespace App\Filament\Resources\Workspaces\Tables;

use App\Models\Workspace;
use App\Support\WorkspaceContext;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WorkspacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('admins_count')
                    ->counts('admins')
                    ->label('Admins')
                    ->badge(),
                TextColumn::make('students_count')
                    ->counts('students')
                    ->label('Students')
                    ->badge(),
                TextColumn::make('sections_count')
                    ->counts('sections')
                    ->label('Sections'),
                IconColumn::make('archived_at')
                    ->label('Archived')
                    ->boolean()
                    ->getStateUsing(fn (Workspace $record): bool => $record->isArchived()),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('archived')
                    ->label('Archived')
                    ->queries(
                        true: fn ($query) => $query->whereNotNull('archived_at'),
                        false: fn ($query) => $query->whereNull('archived_at'),
                        blank: fn ($query) => $query,
                    ),
            ])
            ->actions([
                Action::make('inspect')
                    ->label('Enter workspace')
                    ->icon('heroicon-o-arrow-right-start-on-rectangle')
                    ->visible(fn (Workspace $record): bool => ! $record->isArchived())
                    ->action(function (Workspace $record) {
                        app(WorkspaceContext::class)->inspect($record);

                        return redirect('/admin');
                    }),
                EditAction::make(),
                Action::make('archive')
                    ->color('danger')
                    ->icon('heroicon-o-archive-box')
                    ->requiresConfirmation()
                    ->modalHeading('Archive workspace?')
                    ->modalDescription('Access is disabled, but all tenant data is preserved. The workspace can be restored later.')
                    ->visible(fn (Workspace $record): bool => ! $record->isArchived())
                    ->action(function (Workspace $record): void {
                        $record->archive(auth()->user());
                    }),
                Action::make('restore')
                    ->color('success')
                    ->icon('heroicon-o-arrow-path')
                    ->requiresConfirmation()
                    ->visible(fn (Workspace $record): bool => $record->isArchived())
                    ->action(function (Workspace $record): void {
                        $record->restore();
                    }),
            ])
            ->defaultSort('name');
    }
}
