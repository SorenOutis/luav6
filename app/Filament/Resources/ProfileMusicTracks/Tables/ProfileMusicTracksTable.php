<?php

namespace App\Filament\Resources\ProfileMusicTracks\Tables;

use App\Filament\Support\WorkspaceTable;
use App\Models\ProfileMusicTrack;
use App\Support\PublicFileUrl;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\HtmlString;

class ProfileMusicTracksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                WorkspaceTable::column(),
                ImageColumn::make('cover_image_path')
                    ->label('Cover')
                    ->state(fn (ProfileMusicTrack $record): ?string => PublicFileUrl::resolve($record->cover_image_path))
                    ->circular()
                    ->defaultImageUrl(url('/favicon.ico')),
                TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('artist')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(function ($state): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }
                        $seconds = (float) $state;
                        if ($seconds < 60) {
                            return "{$seconds}s";
                        }
                        $mins = (int) floor($seconds / 60);
                        $rem = (int) round(fmod($seconds, 60));

                        return sprintf('%d:%02d', $mins, $rem);
                    })
                    ->sortable(),
                TextColumn::make('preview')
                    ->label('Listen')
                    ->state(function (ProfileMusicTrack $record): ?HtmlString {
                        if (! $record->audio_path) {
                            return null;
                        }
                        $url = PublicFileUrl::resolve($record->audio_path);

                        return new HtmlString(
                            '<audio controls preload="none" src="'.e($url).'" style="height: 30px; width: 180px;"></audio>'
                        );
                    }),
                ToggleColumn::make('is_active')
                    ->label('Active')
                    ->sortable(),
                IconColumn::make('is_global')
                    ->label('All Workspaces')
                    ->boolean()
                    ->trueIcon('heroicon-o-globe-alt')
                    ->falseIcon('heroicon-o-building-office')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->sortable(),
                TextColumn::make('users_count')
                    ->counts('users')
                    ->label('Students')
                    ->sortable(),
                TextColumn::make('license_name')
                    ->label('License')
                    ->limit(20)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                WorkspaceTable::filter(),
                SelectFilter::make('is_active')
                    ->label('Status')
                    ->options([
                        '1' => 'Active',
                        '0' => 'Inactive',
                    ]),
                SelectFilter::make('is_global')
                    ->label('Availability')
                    ->options([
                        '1' => 'All Workspaces (Global)',
                        '0' => 'Workspace Only',
                    ]),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')
                        ->label('Activate Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('deactivate')
                        ->label('Deactivate Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
