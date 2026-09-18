<?php

/**
 * Admin leaderboards page tests.
 *
 * The Filament "Leaderboards" page shows per-section season leaderboards for
 * every admin. Tenant admins see only their own workspace's sections; super
 * admins see everything platform-wide and can drill into a single workspace.
 * Sections collapse to a top-5 preview and expand to the full ranked table.
 */

use App\Filament\Pages\Leaderboards;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Services\LeaderboardService;
use App\Support\WorkspaceContext;
use Livewire\Livewire;

it('is accessible to tenant admins and super admins only', function () {
    $this->actingAs(User::factory()->admin()->create());
    expect(Leaderboards::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->superAdmin()->create());
    expect(Leaderboards::canAccess())->toBeTrue();

    $this->actingAs(User::factory()->create());
    expect(Leaderboards::canAccess())->toBeFalse();
});

it('scopes service leaderboards to the tenant admin workspace', function () {
    $admin = User::factory()->admin()->create();
    $workspace = $admin->currentWorkspace;
    $season = Season::factory()->active()->create(['workspace_id' => $workspace->id]);

    $own = Section::factory()->forSeason($season)->create([
        'name' => 'Section Own',
        'workspace_id' => $workspace->id,
    ]);

    $otherWorkspace = Workspace::factory()->create();
    $otherSeason = Season::factory()->create(['workspace_id' => $otherWorkspace->id]);
    $other = Section::factory()->forSeason($otherSeason)->create([
        'name' => 'Section Other',
        'workspace_id' => $otherWorkspace->id,
    ]);

    $student = User::factory()->create(['name' => 'Alice Santos']);
    $student->sections()->attach($own->id, ['season_id' => $season->id]);

    $outsider = User::factory()->create(['name' => 'Bob Cruz']);
    $outsider->sections()->attach($other->id, ['season_id' => $otherSeason->id]);

    $this->actingAs($admin);

    $boards = app(LeaderboardService::class)->forAdminSections($admin, $season);

    expect($boards)->toHaveCount(1)
        ->and($boards[0]['sectionId'])->toBe($own->id)
        ->and($boards[0]['workspaceName'])->toBe($workspace->name)
        ->and($boards[0]['users'])->toHaveCount(1)
        ->and($boards[0]['users'][0]['name'])->toBe('Alice Santos')
        ->and(app(LeaderboardService::class)->countAdminSections($admin, $season))->toBe(1);
});

it('shows super admins every workspace and can narrow to one workspace', function () {
    $workspaceA = Workspace::factory()->create();
    $workspaceB = Workspace::factory()->create();

    $seasonA = Season::factory()->active()->create(['workspace_id' => $workspaceA->id]);
    $seasonB = Season::factory()->create(['workspace_id' => $workspaceB->id]);

    $sectionA = Section::factory()->forSeason($seasonA)->create([
        'name' => 'Section Alpha',
        'workspace_id' => $workspaceA->id,
    ]);
    $sectionB = Section::factory()->forSeason($seasonB)->create([
        'name' => 'Section Beta',
        'workspace_id' => $workspaceB->id,
    ]);

    $studentA = User::factory()->create(['name' => 'Charlie Delta']);
    $studentA->sections()->attach($sectionA->id, ['season_id' => $seasonA->id]);
    $studentB = User::factory()->create(['name' => 'Dana Echo']);
    $studentB->sections()->attach($sectionB->id, ['season_id' => $seasonB->id]);

    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    $service = app(LeaderboardService::class);

    $all = $service->forAdminSections($superAdmin, $seasonA);
    expect($all)->toHaveCount(1);

    // A single season can span several workspaces.
    $seasonShared = Season::factory()->create();
    $sharedA = Section::factory()->forSeason($seasonShared)->create([
        'name' => 'Shared A',
        'workspace_id' => $workspaceA->id,
    ]);
    $sharedB = Section::factory()->forSeason($seasonShared)->create([
        'name' => 'Shared B',
        'workspace_id' => $workspaceB->id,
    ]);
    $studentC = User::factory()->create(['name' => 'Eli Fox']);
    $studentC->sections()->attach($sharedA->id, ['season_id' => $seasonShared->id]);
    $studentD = User::factory()->create(['name' => 'Fay Gale']);
    $studentD->sections()->attach($sharedB->id, ['season_id' => $seasonShared->id]);

    $platformWide = $service->forAdminSections($superAdmin, $seasonShared);
    expect($platformWide)->toHaveCount(2)
        ->and(collect($platformWide)->pluck('workspaceName')->all())
        ->toEqualCanonicalizing([$workspaceA->name, $workspaceB->name]);

    $narrowed = $service->forAdminSections($superAdmin, $seasonShared, $workspaceA->id);
    expect($narrowed)->toHaveCount(1)
        ->and($narrowed[0]['sectionId'])->toBe($sharedA->id)
        ->and($service->countAdminSections($superAdmin, $seasonShared, $workspaceA->id))->toBe(1);
});

it('confines the super admin service view to the inspected workspace', function () {
    $workspaceA = Workspace::factory()->create();
    $workspaceB = Workspace::factory()->create();

    $seasonA = Season::factory()->active()->create(['workspace_id' => $workspaceA->id]);
    $seasonB = Season::factory()->create(['workspace_id' => $workspaceB->id]);

    $sectionA = Section::factory()->forSeason($seasonA)->create([
        'name' => 'Section A',
        'workspace_id' => $workspaceA->id,
    ]);
    $sectionB = Section::factory()->forSeason($seasonB)->create([
        'name' => 'Section B',
        'workspace_id' => $workspaceB->id,
    ]);

    $studentA = User::factory()->create();
    $studentA->sections()->attach($sectionA->id, ['season_id' => $seasonA->id]);
    $studentB = User::factory()->create();
    $studentB->sections()->attach($sectionB->id, ['season_id' => $seasonB->id]);

    $superAdmin = User::factory()->superAdmin()->create();
    $this->actingAs($superAdmin);

    app(WorkspaceContext::class)->inspect($workspaceA);

    $boards = app(LeaderboardService::class)->forAdminSections($superAdmin, $seasonA);

    expect($boards)->toHaveCount(1)
        ->and($boards[0]['sectionId'])->toBe($sectionA->id)
        ->and($boards[0]['workspaceName'])->toBe($workspaceA->name);
});

it('expands a section to its full leaderboard', function () {
    $admin = User::factory()->admin()->create();
    $workspace = $admin->currentWorkspace;
    $season = Season::factory()->active()->create(['workspace_id' => $workspace->id]);
    $section = Section::factory()->forSeason($season)->create([
        'name' => 'Big Section',
        'workspace_id' => $workspace->id,
    ]);

    foreach (range(1, 8) as $number) {
        $student = User::factory()->create(['name' => "Student Number {$number}"]);
        $student->sections()->attach($section->id, ['season_id' => $season->id]);
    }

    $this->actingAs($admin);

    $service = app(LeaderboardService::class);

    // The preview only hydrates the top five.
    $preview = $service->forAdminSections($admin, $season, maxVisibleUsers: 5);
    expect($preview[0]['users'])->toHaveCount(5)
        ->and($preview[0]['totalPlayers'])->toBe(8)
        ->and($preview[0]['isTruncated'])->toBeTrue();

    // Expanding hydrates the whole section.
    $expanded = $service->forAdminSection($admin, $season, $section->id);
    expect($expanded['users'])->toHaveCount(8)
        ->and($expanded['totalPlayers'])->toBe(8)
        ->and($expanded['isTruncated'])->toBeFalse()
        ->and(collect($expanded['users'])->pluck('name')->first())->toBe('Student Number 1');
});

it('returns an empty board for a section outside the admin scope', function () {
    $admin = User::factory()->admin()->create();
    $workspace = $admin->currentWorkspace;
    $season = Season::factory()->active()->create(['workspace_id' => $workspace->id]);

    $otherWorkspace = Workspace::factory()->create();
    $otherSeason = Season::factory()->create(['workspace_id' => $otherWorkspace->id]);
    $other = Section::factory()->forSeason($otherSeason)->create(['workspace_id' => $otherWorkspace->id]);

    $this->actingAs($admin);

    expect(app(LeaderboardService::class)->forAdminSection($admin, $season, $other->id))->toBe([]);
});

it('renders the page with the season switcher and top-five preview', function () {
    $admin = User::factory()->admin()->create();
    $workspace = $admin->currentWorkspace;
    $season = Season::factory()->active()->create([
        'name' => 'SY 2026-2027',
        'workspace_id' => $workspace->id,
    ]);
    $section = Section::factory()->forSeason($season)->create([
        'name' => 'STEM 11-A',
        'workspace_id' => $workspace->id,
    ]);

    foreach (range(1, 7) as $number) {
        $student = User::factory()->create(['name' => "Roster Member {$number}"]);
        $student->sections()->attach($section->id, ['season_id' => $season->id]);
    }

    $this->actingAs($admin);

    Livewire::test(Leaderboards::class)
        ->assertOk()
        ->assertSet('seasonId', $season->id)
        ->assertSee('SY 2026-2027')
        ->assertSee('STEM 11-A')
        ->assertSee('Roster Member 1')
        ->assertSee('Roster Member 5')
        ->assertDontSee('Roster Member 7')
        ->assertSee('View full leaderboard');
});

it('loads the full leaderboard when a section is expanded', function () {
    $admin = User::factory()->admin()->create();
    $workspace = $admin->currentWorkspace;
    $season = Season::factory()->active()->create(['workspace_id' => $workspace->id]);
    $section = Section::factory()->forSeason($season)->create([
        'name' => 'ABM 12-B',
        'workspace_id' => $workspace->id,
    ]);

    foreach (range(1, 7) as $number) {
        $student = User::factory()->create(['name' => "Expanded Student {$number}"]);
        $student->sections()->attach($section->id, ['season_id' => $season->id]);
    }

    $this->actingAs($admin);

    Livewire::test(Leaderboards::class)
        ->call('toggleSection', $section->id)
        ->assertSet('expandedSections', [$section->id => true])
        ->assertSee('Expanded Student 7')
        ->call('toggleSection', $section->id)
        ->assertSet('expandedSections', []);
});

it('shows the workspace filter to super admins only', function () {
    $workspace = Workspace::factory()->create();
    $season = Season::factory()->active()->create(['workspace_id' => $workspace->id]);
    $section = Section::factory()->forSeason($season)->create([
        'name' => 'Filtered Section',
        'workspace_id' => $workspace->id,
    ]);
    $student = User::factory()->create(['name' => 'Gina Hall']);
    $student->sections()->attach($section->id, ['season_id' => $season->id]);

    $this->actingAs(User::factory()->admin()->create());

    Livewire::test(Leaderboards::class)
        ->assertOk()
        ->assertDontSee('All workspaces');

    $this->actingAs(User::factory()->superAdmin()->create());

    Livewire::test(Leaderboards::class)
        ->assertOk()
        ->assertSee('All workspaces')
        ->assertSee('Filtered Section')
        ->set('workspaceId', $workspace->id)
        ->assertSet('workspaceId', $workspace->id)
        ->assertSee('Filtered Section');
});

it('resets the expanded state when the season changes', function () {
    $admin = User::factory()->admin()->create();
    $workspace = $admin->currentWorkspace;
    $past = Season::factory()->create([
        'name' => 'Past Season',
        'start_date' => now()->subMonths(6),
        'end_date' => now()->subMonths(3),
        'workspace_id' => $workspace->id,
    ]);
    $current = Season::factory()->active()->create([
        'name' => 'Current Season',
        'workspace_id' => $workspace->id,
    ]);

    $sectionPast = Section::factory()->forSeason($past)->create([
        'name' => 'Past Section',
        'workspace_id' => $workspace->id,
    ]);
    $sectionCurrent = Section::factory()->forSeason($current)->create([
        'name' => 'Current Section',
        'workspace_id' => $workspace->id,
    ]);

    foreach (range(1, 7) as $number) {
        $student = User::factory()->create(['name' => "Past Member {$number}"]);
        $student->sections()->attach($sectionPast->id, ['season_id' => $past->id]);
    }

    $student = User::factory()->create(['name' => 'Current Member']);
    $student->sections()->attach($sectionCurrent->id, ['season_id' => $current->id]);

    $this->actingAs($admin);

    Livewire::test(Leaderboards::class)
        ->assertSet('seasonId', $current->id)
        ->assertSee('Current Season')
        ->assertSee('Current Section')
        ->set('seasonId', $past->id)
        ->assertSet('seasonId', $past->id)
        ->assertSee('Past Section')
        ->call('toggleSection', $sectionPast->id)
        ->assertSet('expandedSections', [$sectionPast->id => true])
        ->assertSee('Past Member 7')
        ->set('seasonId', $current->id)
        ->assertSet('seasonId', $current->id)
        ->assertSet('expandedSections', [])
        ->assertSee('Current Section')
        ->assertDontSee('Past Section');
});
