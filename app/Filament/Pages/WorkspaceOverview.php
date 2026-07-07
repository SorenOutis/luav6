<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Admins\AdminResource;
use App\Models\User;
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
                User::query()
                    ->where('is_admin', true)
                    ->withCount(['sections', 'courses', 'assignments'])
            )
            ->columns([
                TextColumn::make('name')
                    ->label('Admin')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('is_super_admin')
                    ->label('Role')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Super Admin' : 'Admin')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray'),

                TextColumn::make('sections_count')
                    ->label('Sections')
                    ->counts('sections')
                    ->sortable(),

                TextColumn::make('students_count')
                    ->label('Students')
                    ->state(fn (User $admin): int => (int) User::whereHas('sections', fn ($q) =>
                        $q->whereIn('sections.id', $admin->sections()->pluck('sections.id'))
                    )->where('is_admin', false)->count()),

                TextColumn::make('exams_count')
                    ->label('Exams')
                    ->state(fn (User $admin): int => (int) \App\Models\Exam::whereIn('section_id', $admin->sections()->pluck('sections.id'))->count())
                    ->sortable(false),

                TextColumn::make('courses_count')
                    ->label('Courses')
                    ->counts('courses')
                    ->sortable(),

                TextColumn::make('assignments_count')
                    ->label('Assignments')
                    ->counts('assignments')
                    ->sortable(),
            ])
            ->defaultSort('name')
            ->recordUrl(fn (User $record): string => $record->isSuperAdmin()
                ? ''
                : AdminResource::getUrl('edit', ['record' => $record]))
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
