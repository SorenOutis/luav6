<?php

use App\Filament\Resources\ProfileMusicTracks\Pages\CreateProfileMusicTrack;
use App\Filament\Resources\ProfileMusicTracks\Pages\ListProfileMusicTracks;
use App\Models\ProfileMusicTrack;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('allows an admin to access the profile music tracks list', function () {
    $admin = User::factory()->superAdmin()->create();

    actingAs($admin)
        ->get('/admin/profile-music-tracks')
        ->assertSuccessful();
});

it('allows an admin to access the create profile music track page', function () {
    $admin = User::factory()->superAdmin()->create();

    actingAs($admin)
        ->get('/admin/profile-music-tracks/create')
        ->assertSuccessful();
});

it('forbids regular students from accessing admin profile music tracks', function () {
    $student = User::factory()->create();

    actingAs($student)
        ->get('/admin/profile-music-tracks')
        ->assertForbidden();
});

it('allows an admin to access the edit track page', function () {
    $admin = User::factory()->superAdmin()->create();
    $track = ProfileMusicTrack::factory()->create();

    actingAs($admin)
        ->get("/admin/profile-music-tracks/{$track->id}/edit")
        ->assertSuccessful();
});

it('accepts audio/x-wav uploads when creating a track', function () {
    Storage::fake('public');

    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $wavFile = UploadedFile::fake()->create('manhid_30s.wav', 100, 'audio/x-wav');

    Livewire::test(CreateProfileMusicTrack::class)
        ->fillForm([
            'title' => 'Manhid 30s',
            'artist' => 'Artist Name',
            'audio_path' => $wavFile,
            'duration_seconds' => 30.0,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProfileMusicTrack::where('title', 'Manhid 30s')->exists())->toBeTrue();
});

it('allows an admin to toggle the active status of a track from the list table', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $track = ProfileMusicTrack::factory()->create(['is_active' => true]);

    Livewire::test(ListProfileMusicTracks::class)
        ->call('updateTableColumnState', 'is_active', (string) $track->getKey(), false);

    expect($track->fresh()->is_active)->toBeFalse();

    Livewire::test(ListProfileMusicTracks::class)
        ->call('updateTableColumnState', 'is_active', (string) $track->getKey(), true);

    expect($track->fresh()->is_active)->toBeTrue();
});

it('allows an admin to bulk activate and deactivate tracks from the list table', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $track1 = ProfileMusicTrack::factory()->create(['is_active' => true]);
    $track2 = ProfileMusicTrack::factory()->create(['is_active' => true]);

    Livewire::test(ListProfileMusicTracks::class)
        ->callTableBulkAction('deactivate', [$track1, $track2]);

    expect($track1->fresh()->is_active)->toBeFalse()
        ->and($track2->fresh()->is_active)->toBeFalse();

    Livewire::test(ListProfileMusicTracks::class)
        ->callTableBulkAction('activate', [$track1, $track2]);

    expect($track1->fresh()->is_active)->toBeTrue()
        ->and($track2->fresh()->is_active)->toBeTrue();
});

it('allows creating a full length track exceeding 30 seconds and with optional duration', function () {
    Storage::fake('public');

    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $fullTrack = UploadedFile::fake()->create('full_song.mp3', 3000, 'audio/mpeg');

    Livewire::test(CreateProfileMusicTrack::class)
        ->fillForm([
            'title' => 'Full Length Symphony',
            'artist' => 'Orchestra',
            'audio_path' => $fullTrack,
            'duration_seconds' => 245.5,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $created = ProfileMusicTrack::where('title', 'Full Length Symphony')->first();
    expect($created)->not->toBeNull()
        ->and((float) $created->duration_seconds)->toBe(245.5);

    $optionalDurationTrack = UploadedFile::fake()->create('ambient.mp3', 2000, 'audio/mpeg');

    Livewire::test(CreateProfileMusicTrack::class)
        ->fillForm([
            'title' => 'Ambient Waves',
            'artist' => 'Chill Artist',
            'audio_path' => $optionalDurationTrack,
            'duration_seconds' => null,
            'is_active' => true,
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(ProfileMusicTrack::where('title', 'Ambient Waves')->exists())->toBeTrue();
});
