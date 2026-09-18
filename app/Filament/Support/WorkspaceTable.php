<?php

namespace App\Filament\Support;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;

class WorkspaceTable
{
    public static function column(): TextColumn
    {
        return TextColumn::make('workspace.name')
            ->label('Workspace')
            ->badge()
            ->placeholder('Platform global')
            ->sortable()
            ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false);
    }

    public static function filter(): SelectFilter
    {
        return SelectFilter::make('workspace_id')
            ->label('Workspace')
            ->relationship('workspace', 'name')
            ->searchable()
            ->preload()
            ->visible(fn (): bool => auth()->user()?->isSuperAdmin() ?? false);
    }
}
