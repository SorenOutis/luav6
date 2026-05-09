<?php

namespace App\Filament\Resources\Sections\Schemas;

use App\Models\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
                Select::make('school_level')
                    ->label('School level')
                    ->options(Section::schoolLevelOptions())
                    ->default(Section::SCHOOL_LEVEL_COLLEGE)
                    ->required(),
                TextInput::make('password')
                    ->password()
                    ->revealable()
                    ->helperText('Students will be required to enter this password when joining the section. Leave blank on edit to keep the current password.')
                    ->required(fn (string $operation): bool => $operation === 'create')
                    ->dehydrated(fn (?string $state): bool => filled($state))
                    ->maxLength(255),
            ]);
    }
}
