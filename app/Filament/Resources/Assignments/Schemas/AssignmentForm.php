<?php

namespace App\Filament\Resources\Assignments\Schemas;

use App\Models\Assignment;
use App\Models\Section;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Collection;

class AssignmentForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(2)
            ->components([
                TextInput::make('title')
                    ->required()
                    ->maxLength(255)
                    ->columnSpanFull(),
                Select::make('sections')
                    ->label('Assign to sections')
                    ->relationship('sections', 'name')
                    ->multiple()
                    ->preload()
                    ->searchable()
                    ->required()
                    ->minItems(1)
                    ->live()
                    ->afterStateUpdated(function (Set $set, Get $get, $state): void {
                        $currentTerm = $get('term');
                        if (! $currentTerm) {
                            return;
                        }

                        $validOptions = static::termOptionsForSections((array) $state);
                        $validKeys = is_array(reset($validOptions))
                            ? array_merge(...array_map('array_keys', array_values($validOptions)))
                            : array_keys($validOptions);

                        if (! in_array($currentTerm, $validKeys, true)) {
                            $set('term', null);
                        }
                    })
                    ->helperText('Only students in the selected sections receive this assignment and can submit to it.')
                    ->columnSpanFull(),
                Textarea::make('description')
                    ->label('Instructions')
                    ->maxLength(65535)
                    ->columnSpanFull(),
                Select::make('status')
                    ->options([
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'closed' => 'Closed',
                    ])
                    ->required()
                    ->default('draft')
                    ->helperText('Draft is hidden from students. Published is open for submissions. Closed stays visible but no longer accepts work.')
                    ->columnSpan(1),
                Select::make('term')
                    ->label('Grading Period / Term')
                    ->placeholder('Select grading period (optional)')
                    ->options(function (Get $get, ?Assignment $record): array {
                        $sectionIds = $get('sections') ?? $record?->sections()->pluck('sections.id')->all() ?? [];

                        return static::termOptionsForSections((array) $sectionIds);
                    })
                    ->searchable()
                    ->helperText(function (Get $get, ?Assignment $record): string {
                        $sectionIds = $get('sections') ?? $record?->sections()->pluck('sections.id')->all() ?? [];

                        return static::termHelperTextForSections((array) $sectionIds);
                    })
                    ->columnSpan(1),
                DateTimePicker::make('due_date')
                    ->label('Due date')
                    ->seconds(false)
                    ->columnSpan(1),
                TextInput::make('points_possible')
                    ->label('Points possible')
                    ->numeric()
                    ->minValue(0)
                    ->maxValue(999999)
                    ->placeholder('e.g. 100')
                    ->helperText('Optional. Students see what the work is worth, and grades display as earned / possible.')
                    ->columnSpan(1),
                TextInput::make('group_size')
                    ->label('Group size')
                    ->numeric()
                    ->minValue(1)
                    ->maxValue(20)
                    ->default(1)
                    ->required()
                    ->helperText('Number of students per group. 1 = individual; groups form up to this size (max 20).')
                    ->formatStateUsing(fn ($record) => $record?->max_group_size ?? 1)
                    ->columnSpan(1),
                Select::make('course_id')
                    ->relationship('course', 'name')
                    ->searchable()
                    ->preload()
                    ->label('Course')
                    ->placeholder('Select a course (optional)')
                    ->helperText('Optional label only — visibility is controlled by the sections above.')
                    ->columnSpan(1),
            ]);
    }

    /**
     * @param  array<int, int|string>|Collection<int, int|string>  $sectionIds
     * @return array<string, mixed>
     */
    public static function termOptionsForSections(array|Collection $sectionIds): array
    {
        $ids = collect($sectionIds)->filter()->values()->all();
        $levels = empty($ids)
            ? []
            : Section::whereIn('id', $ids)->pluck('school_level')->unique()->values()->all();

        $schoolLevel = count($levels) === 1 ? $levels[0] : null;

        return Section::examTermOptions($schoolLevel);
    }

    /**
     * @param  array<int, int|string>|Collection<int, int|string>  $sectionIds
     */
    public static function termHelperTextForSections(array|Collection $sectionIds): string
    {
        $ids = collect($sectionIds)->filter()->values()->all();
        $levels = empty($ids)
            ? []
            : Section::whereIn('id', $ids)->pluck('school_level')->unique()->values()->all();

        if (count($levels) === 1) {
            if ($levels[0] === Section::SCHOOL_LEVEL_SENIOR_HIGH) {
                return 'Senior High School grading quarters (1st / 2nd Quarter).';
            }

            if ($levels[0] === Section::SCHOOL_LEVEL_COLLEGE) {
                return 'College grading periods (Prelim, Midterm, Final).';
            }
        }

        return 'Pick sections to scope options to Senior High quarters or College periods.';
    }
}
