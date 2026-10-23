<?php

namespace App\Filament\Resources\CommunityPosts\Tables;

use App\Filament\Support\WorkspaceTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CommunityPostsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                WorkspaceTable::column(),
                TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable()
                    ->description(fn ($record) => $record->is_anonymous ? 'Posted as anonymous' : null),
                TextColumn::make('section.name')
                    ->label('Section')
                    ->placeholder('All Sections')
                    ->badge()
                    ->searchable()
                    ->sortable(),
                TextColumn::make('content')
                    ->limit(50)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->content),
                TextColumn::make('attachment_type')
                    ->label('Attachment')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'image' => 'info',
                        'document' => 'warning',
                        'code' => 'success',
                        default => 'gray',
                    })
                    ->placeholder('None'),
                IconColumn::make('is_anonymous')
                    ->label('Anon')
                    ->boolean()
                    ->sortable(),
                TextColumn::make('likes_count')
                    ->label('Likes')
                    ->badge()
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('comments_count')
                    ->label('Comments')
                    ->badge()
                    ->color('primary')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                WorkspaceTable::filter(),
                SelectFilter::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->searchable()
                    ->preload(),
                TernaryFilter::make('is_anonymous')
                    ->label('Anonymous'),
                Filter::make('has_attachment')
                    ->label('Has Attachment')
                    ->query(fn ($query) => $query->whereNotNull('attachment_url')),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
