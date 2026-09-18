<?php

namespace App\Filament\Resources\Sections\RelationManagers;

use App\Models\Section;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class GradesRelationManager extends RelationManager
{
    protected static string $relationship = 'grades';

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $title = 'Student Grades';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Student')
                    ->options(fn () => User::query()
                        ->whereHas('sections', fn ($q) => $q->where('sections.id', $this->ownerRecord->id))
                        ->where('is_admin', false)
                        ->orderBy('name')
                        ->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload(),

                TextInput::make('subject')
                    ->default($this->ownerRecord->name)
                    ->required()
                    ->maxLength(255),

                Select::make('period')
                    ->label('Period')
                    ->options(fn () => $this->ownerRecord instanceof Section
                        ? $this->ownerRecord->gradePeriods()
                        : Section::collegeGradePeriods())
                    ->placeholder('Select a period'),

                TextInput::make('score')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->step(0.01),

                TextInput::make('max_score')
                    ->label('Max score')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(100)
                    ->step(0.01),

                Textarea::make('remarks')
                    ->rows(3)
                    ->maxLength(1000)
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('student.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('subject')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('period')
                    ->badge()
                    ->color('gray')
                    ->placeholder('—'),
                TextColumn::make('score')
                    ->formatStateUsing(fn ($record) => number_format((float) $record->score, 2).' / '.number_format((float) $record->max_score, 2)),
                TextColumn::make('percentage')
                    ->label('%')
                    ->state(fn ($record) => (float) $record->max_score > 0
                        ? round(((float) $record->score / (float) $record->max_score) * 100, 2)
                        : 0)
                    ->formatStateUsing(fn ($state) => number_format((float) $state, 2).'%')
                    ->color(fn ($state) => match (true) {
                        (float) $state >= 85 => 'success',
                        (float) $state >= 70 => 'warning',
                        default => 'danger',
                    })
                    ->badge(),
                TextColumn::make('updated_at')
                    ->dateTime('M d, Y')
                    ->label('Updated')
                    ->sortable(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->filters([
                SelectFilter::make('subject')
                    ->options(fn () => $this->ownerRecord->grades()
                        ->select('subject')
                        ->distinct()
                        ->orderBy('subject')
                        ->pluck('subject', 'subject')
                        ->toArray()),
            ])
            ->headerActions([
                CreateAction::make()
                    ->mutateDataUsing(function (array $data): array {
                        $data['recorded_by'] = auth()->id();

                        return $data;
                    }),
            ])
            ->actions([
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
