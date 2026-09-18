<?php

namespace App\Filament\Resources\Sections\Tables;

use App\Filament\Support\WorkspaceTable;
use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SectionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                WorkspaceTable::column(),
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('season.name')
                    ->label('School Year')
                    ->badge()
                    ->color('info')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('join_code')
                    ->label('Join code')
                    ->formatStateUsing(fn (?string $state): string => $state ? Section::formatJoinCode($state) : '—')
                    ->badge()
                    ->color('success')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('school_level')
                    ->label('School level')
                    ->formatStateUsing(fn (?string $state): string => Section::schoolLevelOptions()[$state] ?? 'College')
                    ->badge()
                    ->color('gray')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Students')
                    ->badge()
                    ->color('primary'),
            ])
            ->filters([
                WorkspaceTable::filter(),
                //
            ])
            ->actions([
                Action::make('regenerateCode')
                    ->label('Regen code')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalHeading('Regenerate section join code?')
                    ->modalDescription('Current students using this code will need the new code to join. Their existing enrollments will not be affected.')
                    ->modalSubmitActionLabel('Yes, regenerate')
                    ->action(function (Section $record) {
                        $newCode = Section::generateUniqueJoinCode();
                        $record->update(['join_code' => $newCode]);

                        Notification::make()
                            ->title('Join code regenerated')
                            ->body('New code: '.Section::formatJoinCode($newCode))
                            ->success()
                            ->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
