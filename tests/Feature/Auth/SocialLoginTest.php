<?php

use App\Models\SocialAccount;
use App\Models\Setting;
use App\Models\User;
use Laravel\Socialite\Contracts\Provider as SocialiteProvider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

beforeEach(function () {
    config([
        'services.google.client_id' => 'google-id',
        'services.google.client_secret' => 'google-secret',
        'services.google.redirect' => 'http://localhost/auth/google/callback',
        'services.github.client_id' => 'github-id',
        'services.github.client_secret' => 'github-secret',
        'services.github.redirect' => 'http://localhost/auth/github/callback',
    ]);
});

/**
 * Fake the Socialite driver so the callback never touches the network.
 */
function fakeSocialiteUser(string $driver, array $attributes = []): SocialiteUser
{
    $socialUser = (new SocialiteUser)->map(array_merge([
        'id' => '1234567890',
        'nickname' => 'juandelacruz',
        'name' => 'Juan Dela Cruz',
        'email' => 'juan@example.com',
        'avatar' => 'https://example.com/avatar.png',
    ], $attributes));

    $provider = Mockery::mock(SocialiteProvider::class);
    $provider->shouldReceive('scopes')->andReturnSelf();
    $provider->shouldReceive('user')->andReturn($socialUser);

    Socialite::shouldReceive('driver')->with($driver)->andReturn($provider);

    return $socialUser;
}

test('login page exposes the configured social providers', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('socialProviders.0.name', 'google')
            ->where('socialProviders.1.name', 'github')
        );
});

test('social routes 404 when the provider is not configured', function () {
    config([
        'services.github.client_id' => null,
        'services.github.client_secret' => null,
    ]);

    $this->get('/auth/github/redirect')->assertNotFound();
});

test('unknown providers are rejected', function () {
    $this->get('/auth/facebook/redirect')->assertNotFound();
});

test('a first time google user gets an account without a section', function () {
    fakeSocialiteUser('google');

    $response = $this->get(route('social.callback', 'google'));

    $response->assertRedirect(config('fortify.home'));
    $this->assertAuthenticated();

    $user = User::where('email', 'juan@example.com')->firstOrFail();

    expect($user->first_name)->toBe('Juan')
        ->and($user->last_name)->toBe('Cruz')
        ->and($user->section_id)->toBeNull()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasPassword())->toBeFalse();

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => '1234567890',
    ]);
});

test('an existing email signs in to the same account instead of duplicating', function () {
    $existing = User::factory()->create(['email' => 'juan@example.com']);

    fakeSocialiteUser('google');

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(config('fortify.home'));

    $this->assertAuthenticatedAs($existing);
    expect(User::where('email', 'juan@example.com')->count())->toBe(1);

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $existing->id,
        'provider' => 'google',
    ]);
});

test('a returning social user is matched on the provider id even after an email change', function () {
    $user = User::factory()->create(['email' => 'old@example.com']);

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => '1234567890',
        'email' => 'old@example.com',
    ]);

    fakeSocialiteUser('github', ['email' => 'new@example.com']);

    $this->get(route('social.callback', 'github'));

    $this->assertAuthenticatedAs($user);
    expect(User::count())->toBe(1);
});

test('a provider without an email is rejected', function () {
    fakeSocialiteUser('github', ['email' => null]);

    $this->get(route('social.callback', 'github'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
});

test('social login respects the login disabled switch', function () {
    Setting::setGlobal('login_enabled', false);

    $this->get(route('social.redirect', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');
});

test('social signup respects the registration disabled switch', function () {
    Setting::setGlobal('registration_enabled', false);

    fakeSocialiteUser('google');

    $this->get(route('social.callback', 'google'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors('email');

    $this->assertGuest();
    expect(User::where('email', 'juan@example.com')->exists())->toBeFalse();
});

test('registration disabled still lets an existing user sign in', function () {
    $existing = User::factory()->create(['email' => 'juan@example.com']);

    Setting::setGlobal('registration_enabled', false);

    fakeSocialiteUser('google');

    $this->get(route('social.callback', 'google'));

    $this->assertAuthenticatedAs($existing);
});

test('an authenticated visit links the provider instead of logging in', function () {
    $user = User::factory()->create(['email' => 'someone@example.com']);

    fakeSocialiteUser('github');

    $this->actingAs($user)
        ->get(route('social.callback', 'github'))
        ->assertRedirect(route('connected-accounts.edit'));

    $this->assertDatabaseHas('social_accounts', [
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => '1234567890',
    ]);
});

test('a provider already linked elsewhere cannot be stolen', function () {
    $owner = User::factory()->create();
    $other = User::factory()->create();

    SocialAccount::create([
        'user_id' => $owner->id,
        'provider' => 'github',
        'provider_id' => '1234567890',
    ]);

    fakeSocialiteUser('github');

    $this->actingAs($other)
        ->get(route('social.callback', 'github'))
        ->assertRedirect(route('connected-accounts.edit'))
        ->assertSessionHasErrors('provider');

    expect(SocialAccount::where('provider_id', '1234567890')->first()->user_id)
        ->toBe($owner->id);
});
