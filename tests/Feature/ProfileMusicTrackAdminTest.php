<?php

use App\Filament\Resources\ProfileMusicTracks\Pages\CreateProfileMusicTrack;
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
