<?php

namespace App\Filament\Resources\Grades\Schemas;

use App\Models\Section;
use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class GradeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('section_id')
                    ->label('Section')
                    ->options(fn () => Section::orderBy('name')->pluck('name', 'id'))
                    ->required()
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(function (Get $get, Set $set) {
                        $set('user_id', null);
                        $set('period', null);
                        $sectionId = $get('section_id');
                        if ($sectionId) {
                            $section = Section::find($sectionId);
                            if ($section) {
                                $set('subject', $section->name);
                            }
                        }
                    }),

                Select::make('user_id')
                    ->label('Student')
                    ->options(function (Get $get) {
                        $sectionId = $get('section_id');

                        if (! $sectionId) {
                            return [];
                        }

                        return User::query()
                            ->whereHas('sections', fn ($q) => $q->where('sections.id', $sectionId))
                            ->where('is_admin', false)
                            ->orderBy('name')
                            ->pluck('name', 'id');
                    })
                    ->required()
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => ! $get('section_id'))
                    ->helperText('Pick a section first to populate this list.'),

                TextInput::make('subject')
                    ->required()
                    ->maxLength(255)
                    ->placeholder('e.g. Mathematics, Science, English'),

                Select::make('period')
                    ->label('Period')
                    ->options(function (Get $get) {
                        $sectionId = $get('section_id');

                        if (! $sectionId) {
                            return Section::collegeGradePeriods();
                        }

                        return Section::find($sectionId)?->gradePeriods() ?? Section::collegeGradePeriods();
                    })
                    ->required()
                    ->placeholder('Select a period')
                    ->helperText('Select the grading period.'),

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
}
