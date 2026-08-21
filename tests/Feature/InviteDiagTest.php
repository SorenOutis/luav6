<?php

use App\Models\Assignment;
use App\Models\AssignmentGroupInvite;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentRosterService;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\DB;

function diag_step_two(Assignment $t2, User $creator, User $member): array
{
    $r2 = test()->actingAs($creator)->post("/assignments/{$t2->id}/invites", [
        'user_ids' => [$member->id],
    ]);

    $rawRows = DB::table('assignment_group_invites')->get()->map(
        fn ($r) => [$r->id, $r->assignment_id, $r->invitee_id, $r->status, $r->workspace_id],
    )->all();

    $scopedAsCreator = AssignmentGroupInvite::query()
        ->where('assignment_id', $t2->id)
        ->where('invitee_id', $member->id)
        ->where('status', 'pending')
        ->count();

    try {
        $invite = AssignmentGroupInvite::query()
            ->where('assignment_id', $t2->id)
            ->where('invitee_id', $member->id)
            ->where('status', 'pending')
            ->firstOrFail();
        $helper = 'FOUND id='.$invite->id;
        $respond = test()->actingAs($member)
            ->post("/assignments/{$t2->id}/invites/{$invite->id}/respond", ['action' => 'accept']);
        $respondStatus = $respond->getStatusCode();
    } catch (Throwable $e) {
        $helper = 'MISS: '.substr($e->getMessage(), 0, 90);
        $respondStatus = null;
    }

    return [
        't2_post_status' => $r2->getStatusCode(),
        't2_location' => $r2->headers->get('Location'),
        't2_session_errors' => session('errors')?->keys() ?? [],
        'raw_rows' => $rawRows,
        'scoped_as_creator' => $scopedAsCreator,
        'helper' => $helper,
        'respond_status' => $respondStatus,
        'auth_id_at_probe' => auth()->id(),
        'context' => app(WorkspaceContext::class)->id(),
        'db_connection' => app('db')->getDefaultConnection(),
        'sqlite_db_list' => DB::select("select name from sqlite_master where type='table' and name='assignment_group_invites'"),
    ];
}

it('diag step one replicates t1', function () {
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create(['name' => 'Alpha']);
    $creator = User::factory()->create(['name' => 'Mina Cruz']);
    $creator->sections()->attach($section->id, ['season_id' => $season->id]);
    $member = User::factory()->create(['name' => 'Jose Santos']);
    $member->sections()->attach($section->id, ['season_id' => $season->id]);
    $other = User::factory()->create(['name' => 'Ana Reyes']);
    $other->sections()->attach($section->id, ['season_id' => $season->id]);

    $t1 = Assignment::create(['title' => 'T1 task', 'due_date' => now()->addWeek()]);
    $t1->sections()->sync([$section->id]);
    app(AssignmentRosterService::class)->syncAssignment($t1);

    $r1 = $this->actingAs($creator)->post("/assignments/{$t1->id}/invites", [
        'user_ids' => [$member->id, $other->id],
    ]);
    expect($r1->getStatusCode())->toBe(302);

    // stash ids for step two via test properties (pest binds them)
    $this->diag_section_id = $section->id;
    $this->diag_season_id = $season->id;
});

it('diag step two replicates t2 after t1', function () {
    // Fresh users/assignment like the real beforeEach + test do.
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create(['name' => 'Alpha']);
    $creator = User::factory()->create(['name' => 'Mina Cruz']);
    $creator->sections()->attach($section->id, ['season_id' => $season->id]);
    $member = User::factory()->create(['name' => 'Jose Santos']);
    $member->sections()->attach($section->id, ['season_id' => $season->id]);

    $t2 = Assignment::create(['title' => 'T2 task', 'due_date' => now()->addWeek()]);
    $t2->sections()->sync([$section->id]);
    app(AssignmentRosterService::class)->syncAssignment($t2);

    $diag = diag_step_two($t2, $creator, $member);
    $diag['prev_test_props_leaked'] = [
        'section' => $this->diag_section_id ?? null,
        'season' => $this->diag_season_id ?? null,
    ];

    expect(true)->toBeFalse('DIAG '.json_encode($diag));
});
