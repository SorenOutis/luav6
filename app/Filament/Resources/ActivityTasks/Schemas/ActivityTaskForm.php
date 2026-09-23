<?php

namespace App\Filament\Resources\ActivityTasks\Schemas;

use App\Models\ActivityTask;
use App\Models\Section;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class ActivityTaskForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                Select::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $set('term', null);
                        $sectionId = $get('section_id');
                        $section = $sectionId ? Section::find($sectionId) : null;

                        if ($section?->school_level === Section::SCHOOL_LEVEL_SENIOR_HIGH) {
                            $set('task_type', 'Performance Task');
                        } elseif ($section?->school_level === Section::SCHOOL_LEVEL_COLLEGE && $get('task_type') === 'Performance Task') {
                            $set('task_type', null);
                        }
                    })
                    ->columnSpan(1),

                TextInput::make('task_type')
                    ->label(function (Get $get, ?ActivityTask $record) {
                        $sectionId = $get('section_id') ?? $record?->section_id;
                        $section = $sectionId ? Section::find($sectionId) : null;

                        if ($section?->school_level === Section::SCHOOL_LEVEL_SENIOR_HIGH) {
                            return 'Assessment Type (Senior High School)';
                        }

                        return 'Activity / Assessment Name (College)';
                    })
                    ->default(function (Get $get, ?ActivityTask $record) {
                        $sectionId = $get('section_id') ?? $record?->section_id;
                        $section = $sectionId ? Section::find($sectionId) : null;

                        return $section?->school_level === Section::SCHOOL_LEVEL_SENIOR_HIGH ? 'Performance Task' : null;
                    })
                    ->placeholder(function (Get $get, ?ActivityTask $record) {
                        $sectionId = $get('section_id') ?? $record?->section_id;
                        $section = $sectionId ? Section::find($sectionId) : null;

                        if ($section?->school_level === Section::SCHOOL_LEVEL_SENIOR_HIGH) {
                            return 'Performance Task';
                        }

                        return 'e.g. Laboratory, Major Project, Case Study, Practicum';
                    })
                    ->helperText(function (Get $get, ?ActivityTask $record) {
                        $sectionId = $get('section_id') ?? $record?->section_id;
                        $section = $sectionId ? Section::find($sectionId) : null;

                        if ($section?->school_level === Section::SCHOOL_LEVEL_SENIOR_HIGH) {
                            return 'Pre-set as Performance Task for Senior High School coursework.';
                        }

                        return 'Name the activity type or component for college (e.g. Laboratory, Project, Oral Defense).';
                    })
                    ->required()
                    ->maxLength(255)
                    ->columnSpan(1),

                TextInput::make('title')
                    ->label('Task Title')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. PT 1: System Presentation or Lab 1: Circuit Setup')
                    ->columnSpan(1),

                Select::make('term')
                    ->label('Grading Period / Term')
                    ->placeholder('Select grading period')
                    ->options(function (Get $get, ?ActivityTask $record) {
                        $sectionId = $get('section_id') ?? $record?->section_id;
                        $section = $sectionId ? Section::find($sectionId) : null;

                        return Section::examTermOptions($section?->school_level);
                    })
                    ->searchable()
                    ->required()
                    ->columnSpan(1),

                TextInput::make('max_points')
                    ->label('Max Points')
                    ->numeric()
                    ->required()
                    ->minValue(1)
                    ->default(100)
                    ->step(0.01)
                    ->columnSpan(1),

                DatePicker::make('due_date')
                    ->label('Activity / Due Date')
                    ->native(false)
                    ->columnSpan(1),

                Textarea::make('description')
                    ->label('Description / Rubric Notes')
                    ->rows(3)
                    ->maxLength(65535)
                    ->columnSpanFull(),
            ]);
    }
}
