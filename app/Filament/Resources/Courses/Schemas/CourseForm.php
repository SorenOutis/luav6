<?php

namespace App\Filament\Resources\Courses\Schemas;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CourseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                TextInput::make('total_lessons')
                    ->required()
                    ->numeric()
                    ->default(0)
                    ->helperText('Auto-calculated from modules/lessons. You can set it manually as a fallback.'),
                FileUpload::make('cover_photo')
                    ->image()
                    ->directory('course-covers')
                    ->columnSpan(1),
                Section::make('Course Modules & Lessons')
                    ->description('Organize your course into modules. Each module can contain multiple lessons with rich content, videos, and completion quizzes.')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('modules')
                            ->relationship('modules')
                            ->columns(2)
                            ->schema([
                                TextInput::make('title')
                                    ->label('Module Title')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpanFull(),
                                Textarea::make('description')
                                    ->maxLength(65535)
                                    ->columnSpanFull(),
                                Hidden::make('sort_order')
                                    ->default(0),
                                Section::make('Lessons')
                                    ->description('Add lessons to this module')
                                    ->columnSpanFull()
                                    ->schema([
                                        Repeater::make('lessons')
                                            ->relationship('lessons')
                                            ->columns(2)
                                            ->schema([
                                                TextInput::make('title')
                                                    ->label('Lesson Title')
                                                    ->required()
                                                    ->maxLength(255)
                                                    ->columnSpanFull(),
                                                RichEditor::make('content')
                                                    ->label('Lesson Content')
                                                    ->toolbarButtons([
                                                        'bold', 'italic', 'underline', 'strike',
                                                        'blockquote', 'bulletList', 'orderedList',
                                                        'codeBlock', 'link', 'image',
                                                        'heading', 'h2', 'h3', 'h4',
                                                        'alignLeft', 'alignCenter', 'alignRight',
                                                    ])
                                                    ->columnSpanFull()
                                                    ->fileAttachmentsDirectory('lesson-content'),
                                                TextInput::make('video_url')
                                                    ->label('Video URL (YouTube/Vimeo)')
                                                    ->url()
                                                    ->placeholder('https://www.youtube.com/watch?v=...')
                                                    ->columnSpan(1),
                                                Hidden::make('sort_order')
                                                    ->default(0),
                                                Section::make('Completion Quiz')
                                                    ->description('Quiz that students must pass to mark this lesson as complete')
                                                    ->columnSpanFull()
                                                    ->collapsed()
                                                    ->schema([
                                                        Repeater::make('quiz')
                                                            ->relationship('quiz')
                                                            ->schema([
                                                                TextInput::make('pass_score')
                                                                    ->label('Pass Score (%)')
                                                                    ->numeric()
                                                                    ->default(75)
                                                                    ->required()
                                                                    ->columnSpan(1),
                                                                TextInput::make('allowed_attempts')
                                                                    ->label('Allowed Attempts (0 = unlimited)')
                                                                    ->numeric()
                                                                    ->default(0)
                                                                    ->columnSpan(1),
                                                                Repeater::make('questions')
                                                                    ->label('Quiz Questions')
                                                                    ->columns(2)
                                                                    ->schema([
                                                                        TextInput::make('question')
                                                                            ->label('Question')
                                                                            ->required()
                                                                            ->columnSpanFull(),
                                                                        Repeater::make('options')
                                                                            ->label('Answer Choices')
                                                                            ->schema([
                                                                                TextInput::make('text')
                                                                                    ->required()
                                                                                    ->placeholder('Option text'),
                                                                                Checkbox::make('is_correct')
                                                                                    ->label('Correct?'),
                                                                            ])
                                                                            ->grid(2)
                                                                            ->itemLabel(fn (array $state): ?string => $state['text'] ?? null)
                                                                            ->collapsible()
                                                                            ->addActionLabel('Add Option')
                                                                            ->columnSpanFull()
                                                                            ->minItems(2),
                                                                    ])
                                                                    ->itemLabel(fn (array $state): ?string => $state['question'] ?? 'New Question')
                                                                    ->collapsible()
                                                                    ->addActionLabel('Add Question')
                                                                    ->columnSpanFull(),
                                                            ])
                                                            ->columns(2)
                                                            ->columnSpanFull(),
                                                    ]),
                                            ])
                                            ->orderColumn('sort_order')
                                            ->collapsible()
                                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'New Lesson')
                                            ->addActionLabel('Add Lesson')
                                            ->columnSpanFull(),
                                    ]),
                            ])
                            ->orderColumn('sort_order')
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => $state['title'] ?? 'New Module')
                            ->addActionLabel('Add Module'),
                    ]),
            ]);
    }
}
