<?php

namespace App\Filament\Resources\Admins\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AdminsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('First')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('middle_name')
                    ->label('Middle')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('last_name')
                    ->label('Last')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('currentWorkspace.name')
                    ->label('Workspace')
                    ->placeholder('Platform / none')
                    ->badge(),
                IconColumn::make('is_super_admin')
                    ->label('Super Admin')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('workspace_sections')
                    ->label('Sections')
                    ->getStateUsing(fn ($record): int => $record->currentWorkspace?->sections()->count() ?? 0)
                    ->badge()
                    ->color('primary'),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalHeading('Delete admin account')
                    ->modalDescription('This removes the administrator account. Shared workspace data remains owned by the tenant. This action cannot be undone.')
                    ->modalSubmitActionLabel('Yes, delete admin'),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
