<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

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
                IconColumn::make('is_banned')
                    ->label('Banned')
                    ->boolean()
                    ->sortable(),
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
                TextColumn::make('banned_at')
                    ->dateTime()
                    ->label('Banned at')
                    ->placeholder('-')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('sections')
                    ->relationship('sections', 'name')
                    ->label('Filter by Sections')
                    ->multiple()
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                Action::make('ban')
                    ->label('Ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->hidden(fn ($record) => $record->is_banned || $record->is_admin || $record->id === auth()->id())
                    ->form([
                        Textarea::make('ban_reason')
                            ->label('Ban reason')
                            ->rows(3)
                            ->placeholder('Optional reason shown to the student.'),
                    ])
                    ->requiresConfirmation()
                    ->action(function ($record, array $data) {
                        $record->update([
                            'is_banned' => true,
                            'banned_at' => now(),
                            'ban_reason' => filled($data['ban_reason'] ?? null) ? $data['ban_reason'] : null,
                        ]);

                        Notification::make()
                            ->title('Student banned')
                            ->success()
                            ->send();
                    }),
                Action::make('unban')
                    ->label('Unban')
                    ->icon('heroicon-o-check-badge')
                    ->color('success')
                    ->hidden(fn ($record) => ! $record->is_banned)
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $record->update([
                            'is_banned' => false,
                            'banned_at' => null,
                            'ban_reason' => null,
                        ]);

                        Notification::make()
                            ->title('Student unbanned')
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('assign_section')
                        ->label('Assign Sections')
                        ->icon('heroicon-o-folder-plus')
                        ->form([
                            Select::make('sections')
                                ->label('Sections')
                                ->multiple()
                                ->options(Section::pluck('name', 'id'))
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
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
