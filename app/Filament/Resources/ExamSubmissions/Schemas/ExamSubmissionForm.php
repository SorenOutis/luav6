<?php

namespace App\Filament\Resources\ExamSubmissions\Schemas;

use Filament\Schemas\Schema;

class ExamSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                \Filament\Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->disabled()
                    ->required(),
                \Filament\Forms\Components\Select::make('exam_id')
                    ->relationship('exam', 'title')
                    ->disabled()
                    ->required(),
                \Filament\Forms\Components\Select::make('exam_part_id')
                    ->relationship('examPart', 'title')
                    ->disabled()
                    ->required(),
                \Filament\Forms\Components\TextInput::make('score')
                    ->numeric()
                    ->required(),
                \Filament\Forms\Components\Select::make('status')
                    ->options([
                        'submitted' => 'Submitted',
                        'pending_review' => 'Pending Review',
                        'graded' => 'Graded',
                    ])
                    ->required(),
                \Filament\Forms\Components\Textarea::make('feedback')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                \Filament\Forms\Components\Placeholder::make('answers_display')
                    ->label('Student Submission Content')
                    ->columnSpanFull()
                    ->content(function ($record) {
                        if (!$record || !$record->answers) return 'No answers recorded.';
                        
                        $answers = is_array($record->answers) ? $record->answers : json_decode($record->answers, true);
                        if (!$answers) return 'Invalid answer format.';

                        $output = '';
                        foreach ($answers as $answer) {
                            $type = strtoupper($answer['question_type'] ?? 'N/A');
                            $qNum = $answer['question_number'] ?? '?';
                            $qText = $answer['question_text'] ?? 'No text';
                            $studentAns = $answer['answer'] ?? 'No answer';
                            $qPts = $answer['points'] ?? '?';
                            
                            $output .= "<div class='p-4 mb-4 rounded-xl border border-border/40 bg-muted/20'>";
                            $output .= "<div class='flex items-center justify-between mb-2'>";
                            $output .= "<p class='text-[10px] font-black uppercase tracking-[0.1em] text-primary/60'>Question {$qNum} - {$type}</p>";
                            $output .= "<p class='text-[10px] font-bold px-2 py-0.5 rounded bg-primary/10 text-primary border border-primary/20 uppercase tracking-widest'>{$qPts} PTS MAX</p>";
                            $output .= "</div>";
                            $output .= "<p class='font-bold mb-3'>{$qText}</p>";
                            $output .= "<div class='p-3 rounded-lg bg-card border border-border/20'><p class='text-sm italic text-foreground/80 leading-relaxed'>{$studentAns}</p></div>";
                            $output .= "</div>";
                        }
                        
                        return new \Illuminate\Support\HtmlString($output);
                    }),
            ]);
    }
}
