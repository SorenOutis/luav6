<?php

/**
 * Per-section leaderboard visibility tests.
 *
 * Admins can disable a section's leaderboard from the admin Leaderboards
 * page. Disabled sections disappear from every student-facing surface — the
 * student leaderboard page, the dashboard leaderboard block, the leaderboard
 * API and the season switcher — while staying visible (and toggleable) on the
 * admin page itself.
 */

use App\Filament\Pages\Leaderboards;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LeaderboardService;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('hides sections with disabled leaderboards from student-facing views', function () {
    $season = Season::factory()->active()->create();
    $workspace = Workspace::factory()->create();

    $visible = Section::factory()->forSeason($season)->create([
        'name' => 'Visible Section',
        'workspace_id' => $workspace->id,
    ]);
    $hidden = Section::factory()->forSeason($season)->leaderboardHidden()->create([
        'name' => 'Hidden Section',
        'workspace_id' => $workspace->id,
    ]);

    $student = User::factory()->create(['name' => 'Ivy Jones']);
    $student->sections()->attach($visible->id, ['season_id' => $season->id]);
    $student->sections()->attach($hidden->id, ['season_id' => $season->id]);
    $student->joinWorkspace($workspace->id);

    actingAs($student);

    $boards = app(LeaderboardService::class)->forUserSections($student, $season);

    expect($boards)->toHaveCount(1)
        ->and($boards[0]['sectionId'])->toBe($visible->id)
        ->and($boards[0]['leaderboardEnabled'])->toBeTrue();

    $superAdmin = User::factory()->superAdmin()->create();
    actingAs($superAdmin);

    $platformWide = app(LeaderboardService::class)->forViewer($superAdmin, $season);

    expect($platformWide)->toHaveCount(1)
        ->and($platformWide[0]['sectionId'])->toBe($visible->id);
});

it('drops seasons whose sections all have hidden leaderboards from the student season list', function () {
    $workspace = Workspace::factory()->create();

    $visibleSeason = Season::factory()->active()->create(['workspace_id' => $workspace->id]);
    $hiddenSeason = Season::factory()->create(['workspace_id' => $workspace->id]);

    $visible = Section::factory()->forSeason($visibleSeason)->create(['workspace_id' => $workspace->id]);
    $hidden = Section::factory()->forSeason($hiddenSeason)->leaderboardHidden()->create(['workspace_id' => $workspace->id]);

    $student = User::factory()->create();
    $student->sections()->attach($visible->id, ['season_id' => $visibleSeason->id]);
    $student->sections()->attach($hidden->id, ['season_id' => $hiddenSeason->id]);

    actingAs($student);

    $seasons = app(LeaderboardService::class)->availableSeasons($student);

    expect($seasons->pluck('id'))->toContain($visibleSeason->id)
        ->not->toContain($hiddenSeason->id);
});

it('excludes hidden sections from the student leaderboard page', function () {
    $workspace = Workspace::factory()->create();
    $season = Season::factory()->active()->create(['workspace_id' => $workspace->id]);

    $visible = Section::factory()->forSeason($season)->create([
        'name' => 'Visible',
        'workspace_id' => $workspace->id,
    ]);
    $hidden = Section::factory()->forSeason($season)->leaderboardHidden()->create([
        'name' => 'Hidden',
        'workspace_id' => $workspace->id,
    ]);

    $student = User::factory()->create(['name' => 'Jack Kim']);
    $student->sections()->attach($visible->id, ['season_id' => $season->id]);
    $student->sections()->attach($hidden->id, ['season_id' => $season->id]);

    actingAs($student);

    $this->get(route('leaderboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('sectionLeaderboards', 1)
            ->where('sectionLeaderboards.0.sectionName', 'Visible'));
});

it('keeps disabled sections on the admin page and toggles their visibility', function () {
    $admin = User::factory()->admin()->create();
    $workspace = $admin->currentWorkspace;
    $season = Season::factory()->active()->create(['workspace_id' => $workspace->id]);
    $section = Section::factory()->forSeason($season)->create([
        'name' => 'Toggle Me',
        'workspace_id' => $workspace->id,
    ]);

    $student = User::factory()->create(['name' => 'Kai Lopez']);
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    // The admin page still lists the section while it is enabled.
    actingAs($admin);
    $boards = app(LeaderboardService::class)->forAdminSections($admin, $season);
    expect($boards)->toHaveCount(1)
        ->and($boards[0]['leaderboardEnabled'])->toBeTrue();

    Livewire::test(Leaderboards::class)
        ->assertOk()
        ->assertSee('Toggle Me')
        ->call('toggleLeaderboardVisibility', $section->id);

    expect($section->fresh()->leaderboard_enabled)->toBeFalse();

    // Students immediately lose the section from their leaderboards.
    actingAs($student);
    expect(app(LeaderboardService::class)->forUserSections($student, $season))->toBe([]);

    // The admin page still shows it, now flagged as hidden, and can re-enable it.
    actingAs($admin);
    Livewire::test(Leaderboards::class)
        ->assertSee('Toggle Me')
        ->assertSee('Hidden from students')
        ->call('toggleLeaderboardVisibility', $section->id);

    expect($section->fresh()->leaderboard_enabled)->toBeTrue();

    actingAs($student);
    $boards = app(LeaderboardService::class)->forUserSections($student, $season);
    expect($boards)->toHaveCount(1)
        ->and($boards[0]['sectionId'])->toBe($section->id);
});

it('does not let a tenant admin toggle a section from another workspace', function () {
    $admin = User::factory()->admin()->create();
    $season = Season::factory()->active()->create();

    $otherWorkspace = Workspace::factory()->create();
    $other = Section::factory()->forSeason($season)->create([
        'name' => 'Foreign Section',
        'workspace_id' => $otherWorkspace->id,
    ]);

    actingAs($admin);

    Livewire::test(Leaderboards::class)
        ->call('toggleLeaderboardVisibility', $other->id);

    expect($other->fresh()->leaderboard_enabled)->toBeTrue();
});
