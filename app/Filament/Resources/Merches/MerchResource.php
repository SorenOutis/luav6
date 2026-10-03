<?php

namespace App\Filament\Resources\Merches;

use App\Filament\Resources\Merches\Pages\CreateMerch;
use App\Filament\Resources\Merches\Pages\EditMerch;
use App\Filament\Resources\Merches\Pages\ListMerches;
use App\Filament\Resources\Merches\Schemas\MerchForm;
use App\Filament\Resources\Merches\Tables\MerchesTable;
use App\Models\Merch;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class MerchResource extends Resource
{
    protected static ?string $model = Merch::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shopping-bag';

    protected static string|\UnitEnum|null $navigationGroup = 'Shop';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $navigationLabel = 'Merchandise';

    protected static ?string $modelLabel = 'Merch Item';

    protected static ?string $pluralModelLabel = 'Merchandise';

    public static function form(Schema $schema): Schema
    {
        return MerchForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MerchesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMerches::route('/'),
            'create' => CreateMerch::route('/create'),
            'edit' => EditMerch::route('/{record}/edit'),
        ];
    }
}
