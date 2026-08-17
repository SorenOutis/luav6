<?php

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => 'Test',
            'middle_name' => 'Middle',
            'last_name' => 'User',
            'email' => 'test@example.com',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->name)->toBe('Test Middle User');
    expect($user->first_name)->toBe('Test');
    expect($user->middle_name)->toBe('Middle');
    expect($user->last_name)->toBe('User');
    expect($user->email)->toBe('test@example.com');
    expect($user->email_verified_at)->toBeNull();
});

test('email verification status is unchanged when the email address is unchanged', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh())->toBeNull();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh())->not->toBeNull();
});

/*
|--------------------------------------------------------------------------
| Avatar / cover photo uploads
|--------------------------------------------------------------------------
|
| The profile form is multipart, and PHP only parses a multipart body into
| $_POST/$_FILES for POST requests. The frontend therefore submits POST with
| `_method=PATCH`; these tests exercise that exact shape so a regression back
| to a literal PATCH (which silently drops the file) is caught here.
|
*/

test('avatar is stored when the form is submitted with method spoofing', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->post(route('profile.update'), [
            '_method' => 'PATCH',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('me.jpg'),
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $stored = $user->refresh()->getRawOriginal('avatar');

    expect($stored)->not->toBeNull()
        ->and($stored)->toStartWith('avatars/');

    Storage::disk('public')->assertExists($stored);
});

test('cover photo is stored when the form is submitted with method spoofing', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.update'), [
            '_method' => 'PATCH',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'cover_photo' => UploadedFile::fake()->image('banner.jpg', 1600, 500),
        ])
        ->assertSessionHasNoErrors();

    $stored = $user->refresh()->getRawOriginal('cover_photo');

    expect($stored)->toStartWith('covers/');
    Storage::disk('public')->assertExists($stored);
});

test('the avatar column stores a relative path, never an uploaded file object', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.update'), [
            '_method' => 'PATCH',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->image('me.png'),
        ])
        ->assertSessionHasNoErrors();

    // The accessor turns the stored path into a URL; the raw column must stay
    // a relative path so PublicFileUrl can resolve it against the disk.
    expect($user->refresh()->getRawOriginal('avatar'))
        ->toBeString()
        ->not->toStartWith('http');
});

test('replacing an avatar deletes the previous file', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile.update'), [
        '_method' => 'PATCH',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('first.jpg'),
    ]);

    $first = $user->refresh()->getRawOriginal('avatar');

    $this->actingAs($user)->post(route('profile.update'), [
        '_method' => 'PATCH',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('second.jpg'),
    ]);

    $second = $user->refresh()->getRawOriginal('avatar');

    expect($second)->not->toBe($first);
    Storage::disk('public')->assertMissing($first);
    Storage::disk('public')->assertExists($second);
});

test('saving other fields leaves an existing avatar untouched', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)->post(route('profile.update'), [
        '_method' => 'PATCH',
        'first_name' => 'Test',
        'last_name' => 'User',
        'email' => $user->email,
        'avatar' => UploadedFile::fake()->image('me.jpg'),
    ]);

    $avatar = $user->refresh()->getRawOriginal('avatar');

    $this->actingAs($user)->post(route('profile.update'), [
        '_method' => 'PATCH',
        'first_name' => 'Renamed',
        'last_name' => 'User',
        'email' => $user->email,
    ])->assertSessionHasNoErrors();

    expect($user->refresh()->getRawOriginal('avatar'))->toBe($avatar);
    Storage::disk('public')->assertExists($avatar);
});

test('a non-image upload is rejected with a validation error', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('profile.update'), [
            '_method' => 'PATCH',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            'avatar' => UploadedFile::fake()->create('resume.pdf', 100, 'application/pdf'),
        ])
        ->assertSessionHasErrors('avatar');

    expect($user->refresh()->getRawOriginal('avatar'))->toBeNull();
});

test('profile privacy preferences can be updated', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.update'), [
            '_method' => 'PATCH',
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'profile_visibility' => User::PROFILE_VISIBILITY_PRIVATE,
            'profile_show_activity' => '1',
            'profile_show_sections' => '0',
            'profile_show_social' => '0',
            'profile_show_achievements' => '0',
        ])
        ->assertSessionHasNoErrors();

    $user->refresh();

    expect($user->profile_visibility)->toBe(User::PROFILE_VISIBILITY_PRIVATE)
        ->and($user->profile_show_activity)->toBeTrue()
        ->and($user->profile_show_sections)->toBeFalse()
        ->and($user->profile_show_social)->toBeFalse()
        ->and($user->profile_show_achievements)->toBeFalse();
});

test('invalid profile visibility is rejected', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('profile.update'), [
            '_method' => 'PATCH',
            'first_name' => $user->first_name,
            'last_name' => $user->last_name,
            'email' => $user->email,
            'profile_visibility' => 'everyone',
        ])
        ->assertSessionHasErrors('profile_visibility');
});

test('an oversized image is rejected rather than silently ignored', function () {
    Storage::fake('public');

    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->post(route('profile.update'), [
            '_method' => 'PATCH',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => $user->email,
            // The rule caps uploads at 10240 KB.
            'avatar' => UploadedFile::fake()->image('huge.jpg')->size(10241),
        ])
        ->assertSessionHasErrors('avatar');
});
