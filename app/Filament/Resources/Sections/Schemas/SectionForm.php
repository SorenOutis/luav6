<?php

namespace App\Filament\Resources\Sections\Schemas;

use App\Models\Season;
use App\Models\Section;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                    ->required(),
                Toggle::make('activity_record_enabled')
                    ->label('Enable Activity Record for Students')
                    ->helperText('Allow students in this section to view the Activity Record button and inspect their activity scores and completion history.')
                    ->default(true),
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
