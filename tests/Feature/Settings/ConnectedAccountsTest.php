<?php

use App\Models\SocialAccount;
use App\Models\User;

beforeEach(function () {
    config([
        'services.google.client_id' => 'google-id',
        'services.google.client_secret' => 'google-secret',
        'services.github.client_id' => 'github-id',
        'services.github.client_secret' => 'github-secret',
    ]);
});

test('connected accounts page lists the providers', function () {
    $user = User::factory()->create();

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => '1',
        'email' => $user->email,
    ]);

    $this->actingAs($user)
        ->get(route('connected-accounts.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/ConnectedAccounts')
            ->where('providers.0.name', 'google')
            ->where('providers.0.connected', true)
            ->where('providers.1.connected', false)
            ->where('hasPassword', true)
        );
});

test('a provider can be disconnected when a password exists', function () {
    $user = User::factory()->create();

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => '1',
    ]);

    $this->actingAs($user)
        ->from(route('connected-accounts.edit'))
        ->delete(route('connected-accounts.destroy', 'google'))
        ->assertSessionHasNoErrors();

    expect($user->socialAccounts()->count())->toBe(0);
});

test('the last sign in method cannot be disconnected', function () {
    $user = User::factory()->create();
    $user->forceFill(['password' => null])->save();

    SocialAccount::create([
        'user_id' => $user->id,
        'provider' => 'google',
        'provider_id' => '1',
    ]);

    $this->actingAs($user)
        ->from(route('connected-accounts.edit'))
        ->delete(route('connected-accounts.destroy', 'google'))
        ->assertSessionHasErrors('provider');

    expect($user->socialAccounts()->count())->toBe(1);
});

test('a social only user can set a first password without confirming one', function () {
    $user = User::factory()->create();
    $user->forceFill(['password' => null])->save();

    $this->actingAs($user)
        ->from(route('user-password.edit'))
        ->put(route('user-password.update'), [
            'password' => 'new-password-123',
            'password_confirmation' => 'new-password-123',
        ])
        ->assertSessionHasNoErrors();

    expect($user->fresh()->hasPassword())->toBeTrue();
});
