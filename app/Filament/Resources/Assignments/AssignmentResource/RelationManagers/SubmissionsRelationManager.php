<?php

namespace App\Filament\Resources\Assignments\AssignmentResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;
use App\Models\Submission;
use Illuminate\Support\Facades\Storage;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Actions\CreateAction;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;

class SubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'submissions';

    protected static ?string $title = 'Student Submissions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required()
                    ->label('Student'),
                Forms\Components\Select::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Submitted' => 'Submitted',
                        'Graded' => 'Graded',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('grade')
                    ->label('Grade'),
                Forms\Components\FileUpload::make('file_path')
                    ->label('Submission File')
                    ->disk('public')
                    ->directory('assignments')
                    ->visibility('public'),
                Forms\Components\DateTimePicker::make('submitted_at')
                    ->label('Submitted At'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('user.name')
                    ->label('Student')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('submitted')
                    ->boolean()
                    ->label('Submitted'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        'Pending' => 'warning',
                        'Submitted' => 'info',
                        'Graded' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('grade')
                    ->label('Grade')
                    ->placeholder('N/A'),
                Tables\Columns\TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'Pending' => 'Pending',
                        'Submitted' => 'Submitted',
                        'Graded' => 'Graded',
                    ]),
            ])
            ->headerActions([
                Tables\Actions\CreateAction::make()
                    ->label('Manual Submission'),
            ])
            ->actions([
                Tables\Actions\Action::make('download')
                    ->label('Download')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('info')
                    ->visible(fn($record) => $record->file_path !== null)
                    ->action(fn($record) => response()->download(Storage::disk('public')->path($record->file_path))),
                Tables\Actions\EditAction::make()
                    ->label('Grade/Edit'),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }
}
