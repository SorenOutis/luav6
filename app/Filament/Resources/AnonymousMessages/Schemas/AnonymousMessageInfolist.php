<?php

namespace App\Filament\Resources\AnonymousMessages\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class AnonymousMessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('user.name')
                    ->label('Student'),
                TextEntry::make('user.sections.name')
                    ->label('Sections')
                    ->badge(),
                TextEntry::make('content')
                    ->columnSpanFull(),
                IconEntry::make('is_approved')
                    ->boolean(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
            ]);
    }
}
