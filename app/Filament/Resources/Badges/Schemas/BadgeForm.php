<?php

namespace App\Filament\Resources\Badges\Schemas;

use App\Models\Badge;
use Filament\Forms\Components\Select;
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
                Select::make('image_path')
                    ->label('Badge image')
                    ->options(fn (?Badge $record): array => static::badgeImageOptions($record))
                    ->searchable()
                    ->placeholder('No image')
                    ->helperText('Pick one of the bundled level 1-100 badge artwork files shipped with the app.'),
                Textarea::make('description')
                    ->columnSpanFull(),
            ]);
    }

    /**
     * All bundled level badge artwork, plus any custom image already saved
     * on the record so existing uploads are preserved when a badge is edited.
     *
     * @return array<string, string>
     */
    private static function badgeImageOptions(?Badge $record): array
    {
        $options = [];

        foreach (range(1, 100) as $level) {
            $options[sprintf('images/badges/level-%03d.svg', $level)] = "Level {$level} badge";
        }

        if ($record?->image_path && ! isset($options[$record->image_path])) {
            $options[$record->image_path] = 'Custom image: '.basename($record->image_path);
        }

        return $options;
    }
}
