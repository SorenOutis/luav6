<?php

namespace App\Filament\Resources\Seasons\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class SeasonForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                DateTimePicker::make('start_date')
                    ->required(),
                DateTimePicker::make('end_date'),
                Toggle::make('is_active')
                    ->required(),
                Toggle::make('show_countdown_on_welcome')
                    ->label('Show Countdown on Welcome Page')
                    ->helperText('When enabled, a live countdown timer for this season will be displayed on the public Welcome page. Requires an end date to be set.'),
            ]);
    }
}
