<?php

use App\Filament\Resources\Merches\Pages\CreateMerch;
use App\Filament\Resources\Merches\Pages\EditMerch;
use App\Filament\Resources\Merches\Pages\ListMerches;
use App\Models\Merch;
use App\Models\User;
use Livewire\Livewire;

test('guests can visit the dedicated shop page and see active merches', function () {
    $this->withoutVite();

    $activeMerch = Merch::factory()->create([
        'name' => 'KOAMISHIN Alpha Hoodie',
        'price' => 1299.00,
        'stock' => 15,
        'is_active' => true,
    ]);

    $inactiveMerch = Merch::factory()->create([
        'name' => 'Hidden Draft Cap',
        'is_active' => false,
    ]);

    $response = $this->get(route('shop'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Shop')
            ->has('merches')
        );

    $merches = $response->viewData('page')['props']['merches'];
    $names = collect($merches)->pluck('name');

    expect($names)->toContain('KOAMISHIN Alpha Hoodie')
        ->and($names)->not->toContain('Hidden Draft Cap');
});

test('merch stock labels and out of stock states compute correctly', function () {
    $inStock = Merch::factory()->create([
        'stock' => 20,
        'is_out_of_stock' => false,
    ]);

    $lowStock = Merch::factory()->create([
        'stock' => 3,
        'is_out_of_stock' => false,
    ]);

    $zeroStock = Merch::factory()->create([
        'stock' => 0,
        'is_out_of_stock' => false,
    ]);

    $forcedOutOfStock = Merch::factory()->create([
        'stock' => 10,
        'is_out_of_stock' => true,
    ]);

    expect($inStock->stock_status_label)->toBe('20 in stock')
        ->and($inStock->effective_out_of_stock)->toBeFalse()
        ->and($lowStock->stock_status_label)->toBe('Only 3 left')
        ->and($lowStock->effective_out_of_stock)->toBeFalse()
        ->and($zeroStock->stock_status_label)->toBe('Out of Stock')
        ->and($zeroStock->effective_out_of_stock)->toBeTrue()
        ->and($forcedOutOfStock->stock_status_label)->toBe('Out of Stock')
        ->and($forcedOutOfStock->effective_out_of_stock)->toBeTrue();
});

test('merch redirect url defaults to https://koamishin.com/', function () {
    $defaultMerch = Merch::factory()->create([
        'button_url' => null,
    ]);

    $customMerch = Merch::factory()->create([
        'button_url' => 'https://koamishin.com/products/special-hoodie',
    ]);

    expect($defaultMerch->redirect_url)->toBe('https://koamishin.com/')
        ->and($customMerch->redirect_url)->toBe('https://koamishin.com/products/special-hoodie');
});

test('welcome header links to dedicated shop page and landing page has no embedded shop section', function () {
    $headerSource = file_get_contents(resource_path('js/components/welcome/WelcomeHeader.vue'));
    $welcomeSource = file_get_contents(resource_path('js/pages/Welcome.vue'));
    $shopPageSource = file_get_contents(resource_path('js/pages/Shop.vue'));
    $footerSource = file_get_contents(resource_path('js/components/welcome/WelcomeFooter.vue'));

    expect($headerSource)->toContain('href="/shop"')
        ->and($welcomeSource)->not->toContain('<ShopSection')
        ->and($shopPageSource)->toContain('Coming Soon...')
        ->and($shopPageSource)->toContain('https://koamishin.com/')
        ->and($footerSource)->toContain('href="/shop"');
});

test('admins can list and manage merches in filament', function () {
    $admin = User::factory()->superAdmin()->create();

    $merch = Merch::factory()->create([
        'name' => 'Test Filament Merch',
    ]);

    $this->actingAs($admin)
        ->get('/admin/merches')
        ->assertSuccessful();

    Livewire::test(ListMerches::class)
        ->assertCanSeeTableRecords([$merch]);
});

test('admins can create a new merch in filament', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    Livewire::test(CreateMerch::class)
        ->fillForm([
            'name' => 'KOAMISHIN Windbreaker Jacket',
            'price' => 1850.00,
            'currency' => 'PHP',
            'stock' => 18,
            'is_out_of_stock' => false,
            'description' => 'Weatherproof shell with reflective accents.',
            'button_url' => 'https://koamishin.com/products/windbreaker',
            'is_active' => true,
            'sort_order' => 5,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(Merch::where('name', 'KOAMISHIN Windbreaker Jacket')->exists())->toBeTrue();
});

test('admins can edit an existing merch in filament', function () {
    $admin = User::factory()->superAdmin()->create();
    $this->actingAs($admin);

    $merch = Merch::factory()->create([
        'name' => 'Original Name',
        'price' => 500.00,
    ]);

    Livewire::test(EditMerch::class, ['record' => $merch->getRouteKey()])
        ->fillForm([
            'name' => 'Updated Name',
            'price' => 599.00,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($merch->refresh()->name)->toBe('Updated Name')
        ->and((float) $merch->price)->toBe(599.00);
});
