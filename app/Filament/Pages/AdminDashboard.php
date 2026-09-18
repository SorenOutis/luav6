<?php

namespace App\Filament\Pages;

use App\Support\WorkspaceContext;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;

class AdminDashboard extends BaseDashboard
{
    protected static bool $isDiscovered = false;

    protected static ?string $title = 'Dashboard';

    protected function getHeaderActions(): array
    {
        $context = app(WorkspaceContext::class);

        return [
            Action::make('exitWorkspaceInspection')
                ->label(fn (): string => 'Exit '.($context->workspace()?->name ?? 'workspace').' inspection')
                ->icon('heroicon-o-arrow-left-start-on-rectangle')
                ->color('warning')
                ->visible(fn (): bool => auth()->user()?->isSuperAdmin() && $context->isInspecting())
                ->action(function () use ($context) {
                    $context->stopInspecting();

                    return redirect('/admin');
                }),
        ];
    }

    public function getColumns(): int|array
    {
        // Every dashboard widget is full-width by design (string 'full'
        // spans only apply at lg and above), so the grid must stay a
        // single column below xl. A multi-column md grid would squeeze
        // full-width widgets side-by-side instead of stacking them.
        return [
            'default' => 1,
            'md' => 1,
            'xl' => 3,
        ];
    }

    public function getWidgetsContentComponent(): Component
    {
        return Grid::make($this->getColumns())
            ->schema(fn (): array => $this->getWidgetsSchemaComponents($this->getWidgets()))
            ->extraAttributes(['class' => 'admin-dashboard-grid']);
    }
}
