<?php

namespace App\Filament\Resources\Users\RelationManagers;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Table;

class ExamSubmissionsRelationManager extends RelationManager
{
    protected static string $relationship = 'examSubmissions';

    protected static ?string $recordTitleAttribute = 'id';

    protected static ?string $title = 'Exam Submissions';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Forms\Components\Select::make('exam_id')
                    ->relationship('exam', 'title')
                    ->searchable()
                    ->required()
                    ->label('Exam'),
                Forms\Components\Select::make('exam_part_id')
                    ->relationship('examPart', 'title')
                    ->required()
                    ->label('Exam Part'),
                Forms\Components\Textarea::make('answers')
                    ->label('Answers')
                    ->columnSpanFull()
                    ->disabled()
                    ->formatStateUsing(function ($state) {
                        $answers = is_array($state) ? $state : json_decode($state, true) ?? [];

                        if (empty($answers)) {
                            return 'No answers provided';
                        }

                        $formatted = [];
                        foreach ($answers as $answer) {
                            $qNum = $answer['question_number'] ?? '?';
                            $qText = $answer['question_text'] ?? 'Unknown Question';
                            $qType = $answer['question_type'] ?? 'unknown';
                            $ans = $answer['answer'] ?? 'No answer provided';

                            $formatted[] = "Q{$qNum} ({$qType}): {$qText}\nAnswer: {$ans}";
                        }

                        return implode("\n".str_repeat('-', 50)."\n", $formatted);
                    }),
                Forms\Components\Select::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_ai' => 'Pending AI',
                        'pending_review' => 'Pending Review',
                        'graded' => 'Graded',
                    ])
                    ->required(),
                Forms\Components\TextInput::make('score')
                    ->numeric()
                    ->step(0.01)
                    ->minValue(0)
                    ->label('Score'),
                Forms\Components\Textarea::make('feedback')
                    ->columnSpanFull(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('exam.title')
                    ->label('Exam')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('examPart.title')
                    ->label('Part')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'submitted' => 'gray',
                        'pending_ai' => 'warning',
                        'pending_review' => 'info',
                        'graded' => 'success',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('score')
                    ->numeric(decimalPlaces: 2)
                    ->label('Score')
                    ->placeholder('N/A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Submitted At')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_ai' => 'Pending AI',
                        'pending_review' => 'Pending Review',
                        'graded' => 'Graded',
                    ]),
                Tables\Filters\SelectFilter::make('exam_id')
                    ->label('Exam')
                    ->relationship('exam', 'title'),
            ])
            ->recordActions([
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
