<?php

namespace App\Filament\Resources\Announcements\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class AnnouncementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('title')
                    ->required(),
                Select::make('section_id')
                    ->label('Section')
                    ->relationship('section', 'name')
                    ->placeholder('All sections')
                    ->helperText('Pick the section that will see this announcement. Leave empty to show it to all students.')
                    ->searchable()
                    ->preload(),
                Textarea::make('description')
                    ->columnSpanFull(),
                TextInput::make('link')
                    ->label('Link URL')
                    ->placeholder('https://example.com or /ngl')
                    ->helperText('Students will be redirected to this link when they click the announcement.')
                    ->columnSpanFull(),
                Toggle::make('is_active')
                    ->required(),
            ]);
    }
}
