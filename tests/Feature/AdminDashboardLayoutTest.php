<?php

use App\Filament\Pages\AdminDashboard;
use App\Filament\Widgets\AdminCommandCenterWidget;
use App\Models\User;

test('admin dashboard stays single column below the xl breakpoint', function () {
    // Every dashboard widget is full-width by design, and Filament string
    // 'full' spans only apply at lg and above. A multi-column md grid
    // would squeeze those widgets side-by-side instead of stacking them.
    $columns = app(AdminDashboard::class)->getColumns();

    expect($columns['default'])->toBe(1)
        ->and($columns['md'])->toBe(1)
        ->and($columns['xl'])->toBe(3);
});

test('admin command center renders on first paint without lazy loading', function () {
    expect(AdminCommandCenterWidget::isLazy())->toBeFalse();
});

test('admin command center renders its hero, metrics, and queue sections', function () {
    $admin = User::query()->where('is_admin', true)->first()
        ?? User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin);

    Livewire::test(AdminCommandCenterWidget::class)
        ->assertSee('Admin control center')
        ->assertSee('Active today')
        ->assertSee('Upcoming deadlines');
});

test('admin command center grid cannot overflow narrow containers', function () {
    $theme = file_get_contents(resource_path('css/filament/admin/theme.css'));

    // Tracks must shrink (minmax floors of 0) instead of overflowing, and
    // panels must be allowed to shrink past their content size.
    expect($theme)->toContain('minmax(0, 1.15fr)')
        ->and($theme)->not->toContain('minmax(18rem, 0.72fr)')
        ->and($theme)->toContain('.admin-command-center-widget')
        ->and($theme)->toContain('container-type: inline-size')
        ->and($theme)->toContain('@container (max-width: 56rem)')
        ->and($theme)->toContain('.admin-dashboard-grid .fi-grid-col');
});

test('super admin can view the admin dashboard', function () {
    $admin = User::query()->where('is_admin', true)->first()
        ?? User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
});
