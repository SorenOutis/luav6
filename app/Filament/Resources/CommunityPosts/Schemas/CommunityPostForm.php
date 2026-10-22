<?php

namespace App\Filament\Resources\CommunityPosts\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommunityPostForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Post Details')
                    ->schema([
                        Textarea::make('content')
                            ->required()
                            ->rows(4)
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                Select::make('user_id')
                                    ->relationship('user', 'name')
                                    ->label('Author')
                                    ->disabled()
                                    ->dehydrated(false),
                                Select::make('section_id')
                                    ->relationship('section', 'name')
                                    ->label('Section')
                                    ->placeholder('All Sections')
                                    ->searchable()
                                    ->preload(),
                            ]),
                        Toggle::make('is_anonymous')
                            ->label('Posted Anonymously')
                            ->helperText('Hides the student real identity on the community lounge.'),
                    ]),
                Section::make('Attachment & Statistics')
                    ->schema([
                        Grid::make(3)
                            ->schema([
                                TextInput::make('attachment_type')
                                    ->label('Attachment Type')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('None'),
                                TextInput::make('attachment_name')
                                    ->label('File Name')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('None'),
                                TextInput::make('attachment_size')
                                    ->label('Size (bytes)')
                                    ->disabled()
                                    ->dehydrated(false)
                                    ->placeholder('0'),
                            ]),
                        TextInput::make('attachment_url')
                            ->label('Attachment Storage Path')
                            ->disabled()
                            ->dehydrated(false)
                            ->placeholder('No attachment')
                            ->columnSpanFull(),
                        Grid::make(2)
                            ->schema([
                                TextInput::make('likes_count')
                                    ->label('Likes')
                                    ->disabled()
                                    ->dehydrated(false),
                                TextInput::make('comments_count')
                                    ->label('Comments')
                                    ->disabled()
                                    ->dehydrated(false),
                            ]),
                    ])
                    ->collapsible(),
            ]);
    }
}
