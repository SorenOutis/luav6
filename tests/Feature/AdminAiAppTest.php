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
        ->assertSee('echo-ai.webmanifest')
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
        ->assertDontSee('echo-ai.webmanifest')
        ->assertDontSee('Install Echo AI');
});

it('offers installation on the existing superadmin chat page', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(AdminAiChat::getUrl())
        ->assertOk()
        ->assertSee('echo-ai.webmanifest')
        ->assertSee('Install Echo AI');
});

it('renders the floating chat head for superadmins across the admin panel', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(route('filament.admin.pages.admin-dashboard'))
        ->assertOk()
        ->assertSee('data-echo-chat-head', escape: false)
        ->assertSee('ai-chat-head.js');
});

it('keeps the floating chat head away from regular admins', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->get(route('filament.admin.pages.admin-dashboard'))
        ->assertOk()
        ->assertDontSee('data-echo-chat-head', escape: false)
        ->assertDontSee('ai-chat-head.js');
});

it('serves an embed variant of the assistant without page chrome', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(AdminAiApp::getUrl().'?embed=1')
        ->assertOk()
        ->assertSee('data-ai-chat-embed', escape: false)
        ->assertDontSee('data-ai-chat-sidebar', escape: false)
        ->assertDontSee('Toggle chat history')
        ->assertDontSee('ai-chat-head.js');
});

it('keeps the full chat page chrome outside embed mode', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(AdminAiApp::getUrl())
        ->assertOk()
        ->assertSee('data-ai-chat-sidebar', escape: false)
        ->assertSee('Toggle chat history')
        ->assertDontSee('data-ai-chat-embed', escape: false);
});

it('ships a standalone manifest with correctly sized icons', function () {
    $manifest = json_decode(file_get_contents(public_path('ai-assistant.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['start_url'])->toBe('/admin/ai-assistant-app')
        ->and($manifest['scope'])->toBe('/admin')
        ->and($manifest['display'])->toBe('standalone')
        ->and($manifest['name'])->toBe('Echo AI')
        ->and($manifest['short_name'])->toBe('Echo AI');

    // "any" icons follow the uploaded school logo via /favicon.png
    // (FaviconController serves school_logo_path and grants exact sizing),
    // while the maskable entry points at the bundled safe-zone artwork.
    expect(collect($manifest['icons'])->pluck('src')->all())
        ->toBe(['/favicon.png?size=192', '/favicon.png?size=512', '/images/ai-app-512.png']);

    $maskable = collect($manifest['icons'])->firstWhere('purpose', 'maskable');

    expect($maskable)->not->toBeNull()
        ->and($maskable['sizes'])->toBe('512x512')
        ->and(file_exists(public_path('images/ai-app-512.png')))->toBeTrue();
});

it('serves a manifest with the school accent color applied', function () {
    Setting::setGlobal('school_accent_color', '#123456');

    $this->get(route('ai-app-manifest'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/manifest+json')
        ->assertJsonPath('theme_color', '#123456')
        ->assertJsonPath('background_color', '#123456')
        ->assertJsonPath('start_url', '/admin/ai-assistant-app');
});

it('ignores invalid accent colors in the manifest', function () {
    Setting::setGlobal('school_accent_color', 'javascript:alert(1)');

    $this->get(route('ai-app-manifest'))
        ->assertOk()
        ->assertJsonPath('theme_color', '#09090b');
});

it('falls back to correctly sized bundled icons when no logo is uploaded', function (int $size, string $expected) {
    Setting::setGlobal('school_logo_path', null);

    $this->get('/favicon.png?size='.$size)->assertRedirect($expected);
})->with([
    [512, '/images/ai-app-512.png'],
    [192, '/images/ai-app-192.png'],
    [180, '/apple-touch-icon.png'],
    [32, '/favicon.ico'],
]);

it('registers a scoped service worker for the installed app', function () {
    $installScript = file_get_contents(public_path('js/ai-app-install.js'));

    expect($installScript)
        ->toContain('/ai-app-sw.js')
        ->toContain("scope: '/admin/ai-assistant-app'");

    $worker = file_get_contents(public_path('ai-app-sw.js'));

    expect($worker)
        ->toContain("addEventListener('fetch'")
        ->toContain("addEventListener('install'")
        ->toContain("addEventListener('activate'");

    // Navigations and API traffic must never be served from cache.
    expect($worker)->toContain("request.mode === 'navigate'")
        ->and($worker)->toContain("url.pathname.startsWith('/api/')");
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
