<?php

use App\Filament\Pages\AdminAiApp;
use App\Filament\Pages\AdminAiChat;
use App\Models\User;

it('lets superadmins open the installable assistant', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(AdminAiApp::getUrl())
        ->assertOk()
        ->assertSee('ai-assistant.webmanifest')
        ->assertSee('ai-app-install.js')
        ->assertSee('Install AI Assistant');
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
        ->assertDontSee('Install AI Assistant');
});

it('offers installation on the existing superadmin chat page', function () {
    $this->actingAs(User::factory()->superAdmin()->create())
        ->get(AdminAiChat::getUrl())
        ->assertOk()
        ->assertSee('ai-assistant.webmanifest')
        ->assertSee('Install AI Assistant');
});

it('ships a standalone manifest with correctly sized icons', function () {
    $manifest = json_decode(file_get_contents(public_path('ai-assistant.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

    expect($manifest['start_url'])->toBe('/admin/ai-assistant-app')
        ->and($manifest['scope'])->toBe('/admin/ai-assistant-app')
        ->and($manifest['display'])->toBe('standalone');

    foreach ($manifest['icons'] as $icon) {
        $size = getimagesize(public_path(ltrim($icon['src'], '/')));
        expect("{$size[0]}x{$size[1]}")->toBe($icon['sizes']);
    }
});
