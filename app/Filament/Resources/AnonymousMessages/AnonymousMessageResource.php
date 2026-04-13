<?php

namespace App\Filament\Resources\AnonymousMessages;

use App\Filament\Resources\AnonymousMessages\Pages\CreateAnonymousMessage;
use App\Filament\Resources\AnonymousMessages\Pages\EditAnonymousMessage;
use App\Filament\Resources\AnonymousMessages\Pages\ListAnonymousMessages;
use App\Filament\Resources\AnonymousMessages\Pages\ViewAnonymousMessage;
use App\Filament\Resources\AnonymousMessages\Schemas\AnonymousMessageForm;
use App\Filament\Resources\AnonymousMessages\Schemas\AnonymousMessageInfolist;
use App\Filament\Resources\AnonymousMessages\Tables\AnonymousMessagesTable;
use App\Models\AnonymousMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class AnonymousMessageResource extends Resource
{
    protected static ?string $model = AnonymousMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleBottomCenterText;

    protected static ?string $navigationLabel = 'NGL Messages';

    protected static ?string $modelLabel = 'Anonymous Message';

    protected static ?string $slug = 'ngl-messages';

    public static function form(Schema $schema): Schema
    {
        return AnonymousMessageForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AnonymousMessageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AnonymousMessagesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnonymousMessages::route('/'),
            'create' => CreateAnonymousMessage::route('/create'),
            'view' => ViewAnonymousMessage::route('/{record}'),
            'edit' => EditAnonymousMessage::route('/{record}/edit'),
        ];
    }
}
