<?php

/**
 * Super admins see every section's leaderboard — per section, per workspace —
 * while students keep seeing only the sections they are enrolled in for the
 * selected season.
 */

use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LeaderboardService;
use App\Support\WorkspaceContext;

use function Pest\Laravel\actingAs;

it('shows super admins every section across all workspaces', function () {
    $season = Season::factory()->active()->create();

    $workspaceA = Workspace::factory()->create();
    $workspaceB = Workspace::factory()->create();

    $sectionA = Section::factory()->forSeason($season)->create(['workspace_id' => $workspaceA->id]);
    $sectionB = Section::factory()->forSeason($season)->create(['workspace_id' => $workspaceB->id]);

    $studentA = User::factory()->create();
    $studentA->sections()->attach($sectionA->id, ['season_id' => $season->id]);

    $studentB = User::factory()->create();
    $studentB->sections()->attach($sectionB->id, ['season_id' => $season->id]);

    $superAdmin = User::factory()->superAdmin()->create();
    actingAs($superAdmin);

    $boards = app(LeaderboardService::class)->forViewer($superAdmin, $season);

    expect($boards)->toHaveCount(2)
        ->and(collect($boards)->pluck('sectionId')->all())
        ->toEqualCanonicalizing([$sectionA->id, $sectionB->id])
        ->and(collect($boards)->pluck('workspaceName')->all())
        ->toEqualCanonicalizing([$workspaceA->name, $workspaceB->name])
        ->and(collect($boards)->pluck('totalPlayers')->all())
        ->toEqualCanonicalizing([1, 1]);
});

it('scopes the super admin view to the inspected workspace', function () {
    $season = Season::factory()->active()->create();

    $workspaceA = Workspace::factory()->create();
    $workspaceB = Workspace::factory()->create();

    $sectionA = Section::factory()->forSeason($season)->create(['workspace_id' => $workspaceA->id]);
    $sectionB = Section::factory()->forSeason($season)->create(['workspace_id' => $workspaceB->id]);

    $studentA = User::factory()->create();
    $studentA->sections()->attach($sectionA->id, ['season_id' => $season->id]);

    $studentB = User::factory()->create();
    $studentB->sections()->attach($sectionB->id, ['season_id' => $season->id]);

    $superAdmin = User::factory()->superAdmin()->create();
    actingAs($superAdmin);

    app(WorkspaceContext::class)->inspect($workspaceA);

    $boards = app(LeaderboardService::class)->forViewer($superAdmin, $season);

    expect($boards)->toHaveCount(1)
        ->and($boards[0]['sectionId'])->toBe($sectionA->id)
        ->and($boards[0]['workspaceName'])->toBe($workspaceA->name);
});

it('shows students only the sections they joined, without workspace metadata', function () {
    $season = Season::factory()->active()->create();
    $workspace = Workspace::factory()->create();

    $joined = Section::factory()->forSeason($season)->create(['workspace_id' => $workspace->id]);
    $other = Section::factory()->forSeason($season)->create(['workspace_id' => $workspace->id]);

    $student = User::factory()->create();
    $student->sections()->attach($joined->id, ['season_id' => $season->id]);

    $outsider = User::factory()->create();
    $outsider->sections()->attach($other->id, ['season_id' => $season->id]);

    // Mirrors the workspace backfill the sections migration performs for
    // enrolled students.
    $student->joinWorkspace($workspace->id);

    actingAs($student);

    $boards = app(LeaderboardService::class)->forViewer($student, $season);

    expect($boards)->toHaveCount(1)
        ->and($boards[0]['sectionId'])->toBe($joined->id)
        ->and($boards[0])->not->toHaveKey('workspaceName')
        ->and($boards[0]['totalPlayers'])->toBe(1);
});

it('still returns nothing for a super admin without a season', function () {
    $superAdmin = User::factory()->superAdmin()->create();
    actingAs($superAdmin);

    expect(app(LeaderboardService::class)->forViewer($superAdmin, null))->toBe([]);
});
