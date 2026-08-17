<?php

namespace App\Filament\Resources\Workspaces\Schemas;

use App\Models\User;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WorkspaceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Tenant workspace')
                ->description('Workspace data is isolated from every other tenant. Archiving is non-destructive.')
                ->schema([
                    TextInput::make('name')
                        ->required()
                        ->maxLength(255),
                    TextInput::make('slug')
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->helperText('Leave blank to generate it from the name.'),
                    Select::make('owner_id')
                        ->label('Workspace owner')
                        ->options(fn (): array => User::query()
                            ->where('is_admin', true)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->required()
                        ->helperText('Other co-admins can be added from the Admins resource.'),
                ]),
        ]);
    }
}
