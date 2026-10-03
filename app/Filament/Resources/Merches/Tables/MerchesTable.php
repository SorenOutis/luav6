<?php

namespace App\Filament\Resources\Merches\Tables;

use App\Models\Merch;
use App\Support\PublicFileUrl;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class MerchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('image_path')
                    ->label('Image')
                    ->state(fn (Merch $record): ?string => PublicFileUrl::resolve($record->image_path))
                    ->square()
                    ->defaultImageUrl(url('/favicon.ico')),

                TextColumn::make('name')
                    ->label('Merch Name')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold')
                    ->description(fn (Merch $record): ?string => $record->description ? Str::limit($record->description, 50) : null),

                TextColumn::make('price')
                    ->label('Price')
                    ->state(fn (Merch $record): string => $record->formatted_price)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('price', $direction)),

                TextColumn::make('stock')
                    ->label('Stock')
                    ->sortable(),

                TextColumn::make('stock_status')
                    ->label('Status')
                    ->state(fn (Merch $record): string => $record->stock_status_label)
                    ->badge()
                    ->color(fn (Merch $record): string => match (true) {
                        $record->effective_out_of_stock => 'danger',
                        $record->stock <= 5 => 'warning',
                        default => 'success',
                    }),

                ToggleColumn::make('is_active')
                    ->label('Published')
                    ->sortable(),

                TextColumn::make('sort_order')
                    ->label('Order')
                    ->sortable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('sort_order', 'asc')
            ->filters([
                SelectFilter::make('is_active')
                    ->label('Publication')
                    ->options([
                        '1' => 'Published',
                        '0' => 'Hidden / Draft',
                    ]),

                Filter::make('out_of_stock')
                    ->label('Out of Stock')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $builder): void {
                        $builder->where('is_out_of_stock', true)
                            ->orWhere('stock', '<=', 0);
                    })),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('publish')
                        ->label('Publish Selected')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => true]))
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('unpublish')
                        ->label('Hide Selected')
                        ->icon('heroicon-o-x-circle')
                        ->color('warning')
                        ->action(fn (Collection $records) => $records->each->update(['is_active' => false]))
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
