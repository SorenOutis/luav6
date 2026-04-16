<?php

namespace App\Filament\Resources\AnonymousMessages\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AnonymousMessageForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Anonymous Message')
                    ->schema([
                        Textarea::make('content')
                            ->required()
                            ->columnSpanFull()
                            ->rows(5),
                        Toggle::make('is_approved')
                            ->label('Approve Message')
                            ->helperText('Approved messages will be visible on the NGL page.')
                            ->default(false),
                    ]),
            ]);
    }
}
