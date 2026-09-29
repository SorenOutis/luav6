<?php

use App\Models\ProfileMusicTrack;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;

use function Pest\Laravel\actingAs;

function sharedMusicProfileContext(array $profileOverrides = []): array
{
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create();
    $viewer = User::factory()->create();
    $profile = User::factory()->create($profileOverrides);
    $viewer->sections()->attach($section->id, ['season_id' => $season->id]);
    $profile->sections()->attach($section->id, ['season_id' => $season->id]);

    return [$viewer, $profile, $section, $season];
}

it('respects the workspace availability toggle on profile music tracks', function () {
    $workspace1 = Workspace::factory()->create();
    $workspace2 = Workspace::factory()->create();

    $globalTrack = ProfileMusicTrack::factory()->create([
        'title' => 'Global Beat',
        'is_active' => true,
        'is_global' => true,
        'workspace_id' => $workspace1->id,
    ]);

    $restrictedTrack = ProfileMusicTrack::factory()->workspaceOnly()->create([
        'title' => 'Workspace 1 Beat',
        'is_active' => true,
        'workspace_id' => $workspace1->id,
    ]);

    $studentInWs2 = User::factory()->create(['current_workspace_id' => $workspace2->id]);

    actingAs($studentInWs2)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Profile')
            ->has('musicTracks', 1)
            ->where('musicTracks.0.title', 'Global Beat')
        );

    // Attempting to save a restricted track from another workspace fails validation
    actingAs($studentInWs2)
        ->patch(route('profile.update'), [
            'first_name' => $studentInWs2->first_name,
            'last_name' => $studentInWs2->last_name,
            'email' => $studentInWs2->email,
            'profile_music_track_id' => $restrictedTrack->id,
        ])
        ->assertSessionHasErrors(['profile_music_track_id']);
});

it('displays available active profile music tracks in profile settings', function () {
    $activeTrack = ProfileMusicTrack::factory()->create([
        'title' => 'Spectre',
        'artist' => 'Alan Walker',
        'duration_seconds' => 29.5,
        'is_active' => true,
    ]);

    $inactiveTrack = ProfileMusicTrack::factory()->inactive()->create([
        'title' => 'Secret Song',
    ]);

    $student = User::factory()->create();

    actingAs($student)
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('settings/Profile')
            ->has('musicTracks', 1)
            ->where('musicTracks.0.id', $activeTrack->id)
            ->where('musicTracks.0.title', 'Spectre')
            ->where('musicTracks.0.artist', 'Alan Walker')
        );
});

it('allows a student to choose a profile soundtrack', function () {
    $track = ProfileMusicTrack::factory()->create([
        'is_active' => true,
    ]);

    $student = User::factory()->create();

    actingAs($student)
        ->patch(route('profile.update'), [
            'first_name' => 'Jane',
            'last_name' => 'Doe',
            'email' => $student->email,
            'profile_music_track_id' => $track->id,
        ])
        ->assertRedirect(route('profile.edit'));

    expect($student->fresh()->profile_music_track_id)->toBe($track->id);
});

it('allows a student to remove their soundtrack', function () {
    $track = ProfileMusicTrack::factory()->create(['is_active' => true]);
    $student = User::factory()->create(['profile_music_track_id' => $track->id]);

    actingAs($student)
        ->patch(route('profile.update'), [
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'profile_music_track_id' => null,
        ])
        ->assertRedirect(route('profile.edit'));

    expect($student->fresh()->profile_music_track_id)->toBeNull();
});

it('rejects an inactive or non-existent profile music track', function () {
    $inactiveTrack = ProfileMusicTrack::factory()->inactive()->create();
    $student = User::factory()->create();

    actingAs($student)
        ->patch(route('profile.update'), [
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'profile_music_track_id' => $inactiveTrack->id,
        ])
        ->assertSessionHasErrors(['profile_music_track_id']);

    actingAs($student)
        ->patch(route('profile.update'), [
            'first_name' => $student->first_name,
            'last_name' => $student->last_name,
            'email' => $student->email,
            'profile_music_track_id' => 999999,
        ])
        ->assertSessionHasErrors(['profile_music_track_id']);
});

it('includes active profile music in public profile for section classmates', function () {
    $track = ProfileMusicTrack::factory()->create([
        'title' => 'Fade',
        'artist' => 'Alan Walker',
        'duration_seconds' => 30.0,
        'attribution_text' => 'Music provided by NoCopyrightSounds.',
        'is_active' => true,
    ]);

    [$viewer, $profile] = sharedMusicProfileContext([
        'profile_music_track_id' => $track->id,
    ]);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/PublicProfile')
            ->where('profileMusic.id', $track->id)
            ->where('profileMusic.title', 'Fade')
            ->where('profileMusic.artist', 'Alan Walker')
            ->where('profileMusic.attributionText', 'Music provided by NoCopyrightSounds.')
            ->has('profileMusic.audioUrl')
        );
});

it('omits profile music if the selected track was deactivated or deleted', function () {
    $track = ProfileMusicTrack::factory()->create([
        'is_active' => false,
    ]);

    [$viewer, $profile] = sharedMusicProfileContext([
        'profile_music_track_id' => $track->id,
    ]);

    actingAs($viewer)
        ->get(route('users.show', ['user' => $profile->public_id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('User/PublicProfile')
            ->where('profileMusic', null)
        );
});
