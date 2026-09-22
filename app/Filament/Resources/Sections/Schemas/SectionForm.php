<?php

namespace App\Filament\Resources\Sections\Schemas;

use App\Models\Season;
use App\Models\Section;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class SectionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->unique(ignoreRecord: true),
                Select::make('season_id')
                    ->label('School Year / Season')
                    ->relationship('season', 'name')
                    ->default(fn () => Season::current()?->id)
                    ->required(),
                Select::make('school_level')
                    ->label('School level')
                    ->options(Section::schoolLevelOptions())
                    ->default(Section::SCHOOL_LEVEL_COLLEGE)
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('activity_record_terms', null)),
                Toggle::make('activity_record_enabled')
                    ->label('Enable Activity Record for Students')
                    ->helperText('Master switch to allow students in this section to view the Activity Record drawer and inspect their activity scores.')
                    ->default(true)
                    ->live(),
                CheckboxList::make('activity_record_terms')
                    ->label('Allowed Activity Record Terms / Quarters')
                    ->helperText('Choose which terms students in this section can see in their Activity Record. If all are unchecked, all terms are enabled by default.')
                    ->options(fn (Get $get, ?Section $record): array => Section::examTermOptions($get('school_level') ?? $record?->school_level ?? Section::SCHOOL_LEVEL_COLLEGE))
                    ->visible(fn (Get $get): bool => (bool) ($get('activity_record_enabled') ?? true))
                    ->columns(2),
                Placeholder::make('join_code')
                    ->label('Section join code')
                    ->content(fn ($record) => $record && $record->join_code
                        ? Section::formatJoinCode($record->join_code)
                        : 'Auto-generated on create'
                    )
                    ->helperText('Students enter this code after registration to join this section.')
                    ->visible(fn (string $operation): bool => $operation === 'edit'),
                TextInput::make('join_code_display')
                    ->label('Section join code')
                    ->default(fn () => Section::formatJoinCode(Section::generateUniqueJoinCode()))
                    ->disabled()
                    ->dehydrated(false)
                    ->helperText('This code will be assigned to this section. Students enter it after registration to join.')
                    ->visible(fn (string $operation): bool => $operation === 'create'),

            ]);
    }
}
