<?php

namespace App\Filament\Resources\Assignments\AssignmentResource\RelationManagers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Storage;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Student Submissions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required()
                    ->label('Student'),
                Select::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Submitted' => 'Submitted',
                        'Graded' => 'Graded',
                    ])
                    ->required(),
                TextInput::make('grade')
                    ->label('Grade'),
                FileUpload::make('file_path')
                    ->label('Submission File')
                    ->disk('public')
                    ->directory('assignments')
                    ->visibility('public'),
                DateTimePicker::make('submitted_at')
                    ->label('Submitted At'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                IconColumn::make('submitted')
                    ->boolean()
                    ->label('Submitted'),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Submitted' => 'info',
                        'Graded' => 'success',
                        default => 'gray',
                    }),
                TextColumn::make('grade')
                    ->label('Grade')
                    ->placeholder('N/A'),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Submitted' => 'Submitted',
                        'Graded' => 'Graded',
                    ]),
            ])
            ->headerActions([
                CreateAction::make()
                    ->label('Manual Submission'),
            ])
            ->actions([
                Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn ($record) => $record->file_path !== null)
                    ->action(fn ($record) => response()->download(Storage::disk('public')->path($record->file_path))),
                EditAction::make()
                    ->label('Grade/Edit'),
                DeleteAction::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
