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
                TextColumn::make('section.name')
                    ->label('Section')
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
                \Filament\Tables\Filters\SelectFilter::make('section')
                    ->relationship('section', 'name')
                    ->label('Filter by Section')
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
                        ->label('Assign Section')
                        ->icon('heroicon-o-folder-plus')
                        ->form([
                            \Filament\Forms\Components\Select::make('section_id')
                                ->label('Section')
                                ->options(\App\Models\Section::pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(fn(\Illuminate\Database\Eloquent\Collection $records, array $data) => $records->each->update(['section_id' => $data['section_id']]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
