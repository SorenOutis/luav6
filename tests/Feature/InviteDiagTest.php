<?php

use App\Models\Assignment;
use App\Models\AssignmentGroupInvite;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentRosterService;
use App\Support\WorkspaceContext;
use Illuminate\Support\Facades\DB;

function diag_probe(string $label, $assignment, $member): array
{
    return [
        'label' => $label,
        'raw_rows' => DB::table('assignment_group_invites')->count(),
        'scoped_pending_for_member' => AssignmentGroupInvite::query()
            ->where('assignment_id', $assignment->id)
            ->where('invitee_id', $member->id)
            ->where('status', 'pending')
            ->count(),
        'auth_id' => auth()->id(),
        'context' => app(WorkspaceContext::class)->id(),
    ];
}

it('diagnoses invite visibility on ci', function () {
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create(['name' => 'Alpha']);

    $creator = User::factory()->create(['name' => 'Mina Cruz']);
    $creator->sections()->attach($section->id, ['season_id' => $season->id]);
    $member = User::factory()->create(['name' => 'Jose Santos']);
    $member->sections()->attach($section->id, ['season_id' => $season->id]);
    $other = User::factory()->create(['name' => 'Ana Reyes']);
    $other->sections()->attach($section->id, ['season_id' => $season->id]);

    // ── T1-equivalent: two invites out ──
    $t1 = Assignment::create(['title' => 'T1 task', 'due_date' => now()->addWeek()]);
    $t1->sections()->sync([$section->id]);
    app(AssignmentRosterService::class)->syncAssignment($t1);

    $r1 = $this->actingAs($creator)->post("/assignments/{$t1->id}/invites", [
        'user_ids' => [$member->id, $other->id],
    ]);
    $t1Rows = DB::table('assignment_group_invites')->get();

    // ── T2-equivalent: new assignment, one invite, then respond helper query ──
    $t2 = Assignment::create(['title' => 'T2 task', 'due_date' => now()->addWeek()]);
    $t2->sections()->sync([$section->id]);
    app(AssignmentRosterService::class)->syncAssignment($t2);

    $r2 = $this->actingAs($creator)->post("/assignments/{$t2->id}/invites", [
        'user_ids' => [$member->id],
    ]);

    $probeAfterT2Post = diag_probe('after-t2-post', $t2, $member);

    // exact helper query from respondToInvite():
    try {
        $invite = AssignmentGroupInvite::query()
            ->where('assignment_id', $t2->id)
            ->where('invitee_id', $member->id)
            ->where('status', 'pending')
            ->firstOrFail();
        $helperResult = 'FOUND id='.$invite->id;
    } catch (Throwable $e) {
        $helperResult = 'MISS: '.substr($e->getMessage(), 0, 80);
    }

    $r3 = $this->actingAs($member)->post("/assignments/{$t2->id}/invites/{$probeAfterT2Post['scoped_pending_for_member']}/respond", ['action' => 'accept']);

    $diag = [
        't1_status' => $r1->getStatusCode(),
        't1_rows' => $t1Rows->map(fn ($r) => [$r->assignment_id, $r->invitee_id, $r->status, $r->workspace_id])->all(),
        't2_status' => $r2->getStatusCode(),
        't2_location' => $r2->headers->get('Location'),
        'probe' => $probeAfterT2Post,
        'helper' => $helperResult,
        'respond_used_invite_id' => $probeAfterT2Post['scoped_pending_for_member'],
        'r3_status' => $r3->getStatusCode(),
        'r3_location' => $r3->headers->get('Location'),
    ];

    expect(true)->toBeFalse('DIAG '.json_encode($diag));
});
