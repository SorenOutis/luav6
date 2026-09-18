<?php

namespace App\Filament\Resources\LearningMaterials\Tables;

use App\Filament\Support\WorkspaceTable;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class LearningMaterialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                WorkspaceTable::column(),
                ImageColumn::make('cover_image')
                    ->label('Cover')
                    ->disk('public')
                    ->square()
                    ->defaultImageUrl(null),
                TextColumn::make('title')
                    ->searchable()
                    ->limit(40)
                    ->sortable(),
                TextColumn::make('category.name')
                    ->label('Category')
                    ->badge()
                    ->placeholder('Uncategorized')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'draft' => 'warning',
                        'published' => 'success',
                        default => 'secondary',
                    })
                    ->sortable(),
                IconColumn::make('is_downloadable')
                    ->label('DL')
                    ->boolean()
                    ->trueIcon('heroicon-o-arrow-down-tray')
                    ->falseIcon('heroicon-o-eye')
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->tooltip(fn (bool $state) => $state ? 'Download allowed' : 'View only'),
                TextColumn::make('sections.name')
                    ->label('Sections')
                    ->badge()
                    ->separator(',')
                    ->placeholder('Unassigned')
                    ->searchable(),
                TextColumn::make('file_extension')
                    ->label('Type')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('view_count')
                    ->label('Views')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('download_count')
                    ->label('Downloads')
                    ->sortable()
                    ->badge()
                    ->color('gray'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                WorkspaceTable::filter(),
                SelectFilter::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
                SelectFilter::make('learning_material_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->preload()
                    ->multiple(),
                SelectFilter::make('sections')
                    ->relationship('sections', 'name')
                    ->multiple()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('sort_order')
            ->reorderable('sort_order');
    }
}
