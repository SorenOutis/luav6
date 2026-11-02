<?php

namespace App\Filament\Resources\CommunityStories\Tables;

use App\Filament\Support\WorkspaceTable;
use App\Support\PublicFileUrl;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CommunityStoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                WorkspaceTable::column(),
                ImageColumn::make('media_url')
                    ->label('Preview')
                    ->state(fn ($record): ?string => $record->media_type === 'image' ? PublicFileUrl::resolve($record->media_url) : null)
                    ->square(),
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('section.name')
                    ->label('Section')
                    ->placeholder('All Sections')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('media_type')
                    ->label('Type')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'video' => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('duration_seconds')
                    ->label('Duration')
                    ->formatStateUsing(fn ($state) => "{$state}s"),
                TextColumn::make('caption')
                    ->limit(40)
                    ->placeholder('—')
                    ->searchable(),
                TextColumn::make('views_count')
                    ->label('Views')
                    ->badge()
                    ->color('success')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn ($record): string => $record->expires_at?->isPast() ? 'Expired' : 'Active')
                    ->color(fn (string $state): string => match ($state) {
                        'Active' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                WorkspaceTable::filter(),
                SelectFilter::make('media_type')
                    ->options([
                        'image' => 'Image',
                        'video' => 'Video',
                    ]),
                TernaryFilter::make('is_active')
                    ->label('Status')
                    ->placeholder('All Stories')
                    ->trueLabel('Active Only')
                    ->falseLabel('Expired Only')
                    ->queries(
                        true: fn ($q) => $q->where('expires_at', '>', now()),
                        false: fn ($q) => $q->where('expires_at', '<=', now()),
                    ),
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
