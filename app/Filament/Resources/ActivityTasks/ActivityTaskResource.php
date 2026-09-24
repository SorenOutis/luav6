<?php

namespace App\Filament\Resources\ActivityTasks;

use App\Filament\Resources\ActivityTasks\Pages\CreateActivityTask;
use App\Filament\Resources\ActivityTasks\Pages\EditActivityTask;
use App\Filament\Resources\ActivityTasks\Pages\ListActivityTasks;
use App\Filament\Resources\ActivityTasks\RelationManagers\ScoresRelationManager;
use App\Filament\Resources\ActivityTasks\Schemas\ActivityTaskForm;
use App\Filament\Resources\ActivityTasks\Tables\ActivityTasksTable;
use App\Models\ActivityTask;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;

class ActivityTaskResource extends Resource
{
    protected static ?string $model = ActivityTask::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'Learning';

    protected static ?string $navigationLabel = 'Performance Tasks';

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ActivityTaskForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityTasksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ScoresRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityTasks::route('/'),
            'create' => CreateActivityTask::route('/create'),
            'edit' => EditActivityTask::route('/{record}/edit'),
        ];
    }
}
