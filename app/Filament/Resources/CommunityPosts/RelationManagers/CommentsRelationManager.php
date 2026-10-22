<?php

namespace App\Filament\Resources\CommunityPosts\RelationManagers;

use App\Models\CommunityComment;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CommentsRelationManager extends RelationManager
{
    protected static string $relationship = 'comments';

    protected static ?string $recordTitleAttribute = 'content';

    protected static ?string $title = 'Comments & Threaded Replies';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('content')
                    ->label('Comment Content')
                    ->required()
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('likes.user'))
            ->columns([
                TextColumn::make('user.name')
                    ->label('Author')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('content')
                    ->limit(60)
                    ->wrap()
                    ->searchable(),
                TextColumn::make('parent_id')
                    ->label('Type')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Reply' : 'Top Comment')
                    ->color(fn ($state) => $state ? 'info' : 'gray'),
                TextColumn::make('likes_count')
                    ->label('Likes')
                    ->badge()
                    ->color('danger')
                    ->sortable(),
                TextColumn::make('reactions')
                    ->label('Reacted By')
                    ->state(fn (CommunityComment $record): string => $record->likes->pluck('user.name')->filter()->join(', '))
                    ->placeholder('No reactions')
                    ->limit(40)
                    ->tooltip(fn (CommunityComment $record): string => $record->likes->pluck('user.name')->filter()->join(', ')),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_reply')
                    ->label('Comment Type')
                    ->placeholder('All')
                    ->trueLabel('Replies Only')
                    ->falseLabel('Top Comments Only')
                    ->queries(
                        true: fn ($q) => $q->whereNotNull('parent_id'),
                        false: fn ($q) => $q->whereNull('parent_id'),
                    ),
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
