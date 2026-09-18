<?php

namespace App\Filament\Resources\Sections\RelationManagers;

use App\Models\Section;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\AttachAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DetachAction;
use Filament\Tables\Actions\DetachBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $recordTitleAttribute = 'name';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('first_name')
                    ->label('First name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('last_name')
                    ->label('Last name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('middle_name')
                    ->label('Middle name (optional)')
                    ->maxLength(255)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('first_name')
                    ->label('First')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('middle_name')
                    ->label('Middle')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('last_name')
                    ->label('Last')
                    ->placeholder('-')
                    ->toggleable(),
                TextColumn::make('email')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Add Students')
                    ->modalHeading('Add Students to Section')
                    ->modalSubmitActionLabel('Add')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'email', 'first_name', 'last_name'])
                    ->recordSelectOptionsQuery(fn (Builder $query) => $query
                        ->where('is_admin', false)
                        ->orderBy('name')
                    )
                    ->mutateFormDataUsing(function (array $data, \Filament\Resources\RelationManagers\RelationManager $livewire): array {
                        $owner = $livewire->getOwnerRecord();
                        if ($owner instanceof Section && $owner->season_id) {
                            $data['season_id'] = $owner->season_id;
                        }

                        return $data;
                    }),
            ])
            ->actions([
                EditAction::make(),
                DetachAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DetachBulkAction::make(),
                ]),
            ]);
    }
}
