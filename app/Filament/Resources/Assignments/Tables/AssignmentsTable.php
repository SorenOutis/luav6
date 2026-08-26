<?php

namespace App\Filament\Resources\Assignments\Tables;

use App\Filament\Support\WorkspaceTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssignmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                WorkspaceTable::column(),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        'closed' => 'danger',
                        default => 'secondary',
                    })
                    ->sortable(),
                TextColumn::make('sections.name')
                    ->label('Assigned sections')
                    ->badge()
                    ->separator(',')
                    ->placeholder('Unassigned')
                    ->searchable(),
                TextColumn::make('users_count')
                    ->label('Students')
                    ->counts('users')
                    ->badge()
                    ->color('gray')
                    ->toggleable(),
                TextColumn::make('due_date')
                    ->label('Due')
                    ->dateTime('M d, Y g:i A')
                    ->placeholder('No deadline')
                    ->sortable(),
                TextColumn::make('points_possible')
                    ->label('Points')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('course.name')
                    ->label('Course')
                    ->placeholder('—')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                WorkspaceTable::filter(),
                SelectFilter::make('sections')
                    ->label('Section')
                    ->relationship('sections', 'name')
                    ->multiple()
                    ->preload(),
                Filter::make('unassigned')
                    ->label('Unassigned only')
                    ->query(fn (Builder $query): Builder => $query->whereDoesntHave('sections')),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
