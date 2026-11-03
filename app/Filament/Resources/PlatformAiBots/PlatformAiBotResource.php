<?php

namespace App\Filament\Resources\PlatformAiBots;

use App\Filament\Resources\PlatformAiBots\Pages\BotOperations;
use App\Filament\Resources\PlatformAiBots\Pages\CreatePlatformAiBot;
use App\Filament\Resources\PlatformAiBots\Pages\EditPlatformAiBot;
use App\Filament\Resources\PlatformAiBots\Pages\ListPlatformAiBots;
use App\Filament\Resources\PlatformAiBots\Schemas\PlatformAiBotForm;
use App\Filament\Resources\PlatformAiBots\Tables\PlatformAiBotsTable;
use App\Models\AiBot;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

use function Filament\Support\original_request;

/**
 * Super-admin-only control center for every workspace's bot roster.
 *
 * Workspace admins manage their own bots through the tenant-scoped
 * AiBotResource; this resource deliberately bypasses the workspace scope so a
 * platform operator can audit and repair policy across tenants.
 */
class PlatformAiBotResource extends Resource
{
    protected static ?string $model = AiBot::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Bot Control Center';

    protected static ?string $modelLabel = 'AI Bot';

    protected static ?string $pluralModelLabel = 'AI Bots';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->isSuperAdmin() ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canCreate(): bool
    {
        return static::canAccess();
    }

    public static function canEdit($record): bool
    {
        return static::canAccess();
    }

    public static function canDelete($record): bool
    {
        return static::canAccess();
    }

    /** @return Builder<AiBot> */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScope('workspace')
            ->with(['workspace:id,name', 'creator:id,name']);
    }

    public static function form(Schema $schema): Schema
    {
        return PlatformAiBotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformAiBotsTable::configure($table);
    }

    /**
     * The control center keeps its management table while Operations gets its
     * own sidebar destination, so both are one click from AI Studio.
     *
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        $items = parent::getNavigationItems();

        if ($items === []) {
            return [];
        }

        $routeBase = static::getRouteBaseName();

        $items[0] = $items[0]->isActiveWhen(fn (): bool => original_request()->routeIs([
            $routeBase.'.index',
            $routeBase.'.create',
            $routeBase.'.edit',
        ]));

        $items[] = NavigationItem::make('Bot Operations')
            ->key(static::class.'::operations')
            ->group(static::getNavigationGroup())
            ->icon('heroicon-o-signal')
            ->isActiveWhen(fn (): bool => original_request()->routeIs($routeBase.'.operations'))
            ->sort((static::getNavigationSort() ?? 0) + 1)
            ->url(static::getUrl('operations'));

        return $items;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatformAiBots::route('/'),
            'operations' => BotOperations::route('/operations'),
            'create' => CreatePlatformAiBot::route('/create'),
            'edit' => EditPlatformAiBot::route('/{record}/edit'),
        ];
    }
}
