<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->disk('public'),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email address')
                    ->searchable(),
                TextColumn::make('sections.name')
                    ->label('Sections')
                    ->badge()
                    ->color('success')
                    ->placeholder('None')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('email_verified_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('currentSeasonProgress.level')
                    ->label('Level')
                    ->sortable(),
                TextColumn::make('currentSeasonProgress.points')
                    ->label('Points')
                    ->sortable(),
                TextColumn::make('currentSeasonProgress.exp')
                    ->label('Exp')
                    ->sortable(),
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
                \Filament\Tables\Filters\SelectFilter::make('sections')
                    ->relationship('sections', 'name')
                    ->label('Filter by Sections')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                \Filament\Actions\DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign_section')
                        ->label('Assign Sections')
                        ->icon('heroicon-o-folder-plus')
                        ->form([
                            \Filament\Forms\Components\Select::make('sections')
                                ->label('Sections')
                                ->multiple()
                                ->options(\App\Models\Section::pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records, array $data) {
                            $records->each(function ($record) use ($data) {
                                $record->sections()->sync($data['sections']);
                            });
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
