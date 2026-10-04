<?php

use App\Filament\Pages\AdminAiApp;
use App\Filament\Pages\AdminAiChat;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('lets superadmins open the installable assistant', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(AdminAiApp::getUrl())
        ->assertOk()
        ->assertSee('ai-assistant.webmanifest')
        ->assertSee('ai-app-install.js')
        ->assertSee('Install Echo AI');
});

it('denies other users access to the app entry', function (string $role) {
    $factory = User::factory();
    $user = ($role === 'admin' ? $factory->admin() : $factory)->create();

    $this->actingAs($user)
        ->get(AdminAiApp::getUrl())
        ->assertForbidden();
})->with(['admin', 'student']);

it('requires login for the app entry', function () {
    $this->get(AdminAiApp::getUrl())->assertRedirect();
});

it('keeps ordinary admin chat without install controls', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(AdminAiChat::getUrl())
        ->assertOk()
        ->assertDontSee('ai-assistant.webmanifest')
        ->assertDontSee('Install Echo AI');
});

it('offers installation on the existing superadmin chat page', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(AdminAiChat::getUrl())
        ->assertOk()
        ->assertSee('ai-assistant.webmanifest')
        ->assertSee('Install Echo AI');
});

it('ships a standalone manifest with correctly sized icons', function () {
    $manifest = json_decode(file_get_contents(public_path('ai-assistant.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['start_url'])->toBe('/admin/ai-assistant-app')
        ->and($manifest['scope'])->toBe('/admin/ai-assistant-app')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['name'])->toBe('Echo AI')
        ->and($manifest['short_name'])->toBe('Echo AI');

    // First icons follow the school logo via /favicon.png (FaviconController serves
    // the uploaded school_logo_path); bundled PNGs remain as installable fallback.
    expect(collect($manifest['icons'])->pluck('src'))
        ->toContain('/favicon.png?size=192')
        ->toContain('/images/ai-app-192.png');

    foreach ($manifest['icons'] as $icon) {
        if (str_starts_with($icon['src'], '/favicon.png')) {
            continue;
        }

        $size = getimagesize(public_path(ltrim(explode('?', $icon['src'])[0], '/')));
        expect("{$size[0]}x{$size[1]}")->toBe($icon['sizes']);
    }
});

it('labels the assistant Echo AI in navigation', function () {
    expect(AdminAiChat::getNavigationLabel())->toBe('Echo AI');
});

it('uses the school logo when uploaded and falls back otherwise', function () {
    Storage::fake('public');
    Storage::disk('public')->put('branding/logo.png', file_get_contents(public_path('images/ai-app-192.png')));
    Setting::setGlobal('school_logo_path', 'branding/logo.png');
    Setting::setGlobal('school_name', 'Test Academy');

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(AdminAiChat::getUrl())
        ->assertOk()
        ->assertSee('/storage/branding/logo.png', escape: false)
        ->assertSee('Test Academy logo', escape: false);

    Setting::setGlobal('school_logo_path', null);

    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(AdminAiChat::getUrl())
        ->assertOk()
        ->assertSee('wolf-persona');
});
