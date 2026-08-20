<?php

namespace App\Filament\Resources\Assignments\AssignmentResource\RelationManagers;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
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
                    ->required()
                    ->live()
                    ->helperText('Set to Graded to trigger points/XP award and student notification.'),
                TextInput::make('grade')
                    ->label('Grade (e.g., A, 95%, 8/10)')
                    ->placeholder('e.g., 90, A, 8/10')
                    ->maxLength(20),
                TextInput::make('points')
                    ->label('Points Awarded')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->default(0)
                    ->helperText('Points are added to student total and section/season progress. 0 = no points.')
                    ->required(),
                TextInput::make('xp_earned')
                    ->label('XP Bonus (Optional)')
                    ->numeric()
                    ->minValue(0)
                    ->step(0.01)
                    ->default(0)
                    ->helperText('Optional XP bonus on top of points. Leave 0 if you only want points.')
                    ->required(),
                Textarea::make('feedback')
                    ->label('Teacher Feedback')
                    ->placeholder('Write feedback for the student... This will be shown on their assignments page and included in the notification.')
                    ->rows(4)
                    ->columnSpanFull()
                    ->maxLength(2000),
                FileUpload::make('file_path')
                    ->label('Submission File')
                    ->disk('public')
                    ->directory('assignments')
                    ->visibility('public')
                    ->acceptedFileTypes([
                        'application/pdf',
                        'application/msword',
                        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'application/vnd.ms-powerpoint',
                        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                        'application/vnd.ms-excel',
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'image/jpeg',
                        'image/png',
                    ])
                    ->maxSize(10240)
                    ->openable()
                    ->downloadable()
                    ->helperText('Allowed: docx, pptx, excel (xls/xlsx), pdf, jpg, png. Max 10MB. Stored on R2 via public disk (same as avatars). Preview works for images/pdf.'),
                DateTimePicker::make('submitted_at')
                    ->label('Submitted At'),
                DateTimePicker::make('graded_at')
                    ->label('Graded At')
                    ->helperText('Auto-set when status becomes Graded.'),
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
                TextColumn::make('points')
                    ->label('Points')
                    ->numeric(2)
                    ->placeholder('0')
                    ->sortable(),
                TextColumn::make('xp_earned')
                    ->label('XP')
                    ->numeric(2)
                    ->placeholder('0')
                    ->sortable(),
                TextColumn::make('feedback')
                    ->label('Feedback')
                    ->limit(30)
                    ->placeholder('No feedback')
                    ->tooltip(fn ($record) => $record->feedback)
                    ->toggleable(),
                TextColumn::make('file_path')
                    ->label('File')
                    ->placeholder('No file')
                    ->formatStateUsing(fn (?string $state) => $state ? basename($state) : null)
                    ->tooltip(fn (?string $state) => $state)
                    ->icon(fn (?string $state) => $state ? 'heroicon-o-document' : null)
                    ->url(fn ($record) => $record->file_url, shouldOpenInNewTab: true)
                    ->searchable(),
                TextColumn::make('submitted_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('graded_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
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
                    ->action(function ($record) {
                        $stream = Storage::disk('public')->readStream($record->file_path);
                        if (! $stream) {
                            abort(404);
                        }

                        return response()->streamDownload(function () use ($stream) {
                            fpassthru($stream);
                            if (is_resource($stream)) {
                                fclose($stream);
                            }
                        }, basename((string) $record->file_path), [
                            'Content-Type' => Storage::disk('public')->mimeType($record->file_path) ?? 'application/octet-stream',
                        ]);
                    }),
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
