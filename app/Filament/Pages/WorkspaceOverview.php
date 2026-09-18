<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Admins\AdminResource;
use App\Models\Workspace;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;

class WorkspaceOverview extends Page implements HasActions, HasTable
{
    use InteractsWithActions;
    use InteractsWithTable;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-globe-alt';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 0;

    protected static ?string $title = 'Workspace Overview';

    protected static ?string $navigationLabel = 'Workspaces';

    protected string $view = 'filament.pages.workspace-overview';

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->isSuperAdmin() ?? false;
    }

    public function mount(): void
    {
        //
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Workspace::query()->withCount([
                    'admins',
                    'students',
                    'sections',
                    'courses',
                    'assignments',
                    'exams',
                ])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Tenant workspace')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('slug')
                    ->label('Slug')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('admins_count')
                    ->label('Admins')
                    ->sortable(),

                TextColumn::make('students_count')
                    ->label('Students')
                    ->sortable(),

                TextColumn::make('sections_count')
                    ->label('Sections')
                    ->sortable(),

                TextColumn::make('exams_count')
                    ->label('Exams')
                    ->sortable(),

                TextColumn::make('courses_count')
                    ->label('Courses')
                    ->sortable(),

                TextColumn::make('assignments_count')
                    ->label('Assignments')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordUrl(function (Workspace $workspace): string {
                $adminId = $workspace->admins()->value('users.id');

                return $adminId
                    ? AdminResource::getUrl('edit', ['record' => $adminId])
                    : '';
            })
            ->recordAction(null);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('createAdmin')
                ->label('Create Admin')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->url(fn (): string => AdminResource::getUrl('create')),
        ];
    }
}
