<?php

namespace App\Filament\Resources\Badges\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class BadgeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('required_level')
                    ->label('Unlock Level')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->helperText('Students unlock this badge automatically once they reach this level.'),
                Textarea::make('description')
                    ->columnSpanFull(),
                FileUpload::make('image_path')
                    ->label('Badge Image')
                    ->image()
                    ->disk('public')
                    ->directory('badges')
                    ->maxSize(10240)
                    ->dehydrated(fn ($state) => filled($state))
                    ->helperText('Upload the image students will see when this badge is unlocked.'),
            ]);
    }
}
