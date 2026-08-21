<?php

use App\Models\Assignment;
use App\Models\AssignmentGroupInvite;
use App\Models\Season;
use App\Models\Section;
use App\Models\User;
use App\Services\AssignmentRosterService;
use Illuminate\Support\Facades\DB;

it('diagnoses invite visibility on ci', function () {
    $season = Season::factory()->active()->create();
    $section = Section::factory()->forSeason($season)->create(['name' => 'Alpha']);

    $creator = User::factory()->create(['name' => 'Mina Cruz']);
    $creator->sections()->attach($section->id, ['season_id' => $season->id]);
    $member = User::factory()->create(['name' => 'Jose Santos']);
    $member->sections()->attach($section->id, ['season_id' => $season->id]);

    $assignment = Assignment::create([
        'title' => 'Group activity task',
        'due_date' => now()->addWeek(),
    ]);
    $assignment->sections()->sync([$section->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    $response = $this->actingAs($creator)
        ->post("/assignments/{$assignment->id}/invites", ['user_ids' => [$member->id]]);

    $diag = [
        'status' => $response->getStatusCode(),
        'target' => $response->headers->get('Location'),
        'body' => substr(strip_tags($response->getContent()), 0, 300),
        'raw_rows' => DB::table('assignment_group_invites')->count(),
        'raw_first' => (array) DB::table('assignment_group_invites')->first(),
        'scoped_as_creator' => AssignmentGroupInvite::query()->count(),
        'creator_ws' => $creator->workspaces()->pluck('workspaces.id')->all(),
        'creator_current_ws' => $creator->current_workspace_id,
        'context_id' => app(\App\Support\WorkspaceContext::class)->id(),
        'session_errors' => session('errors')?->keys() ?? [],
    ];

    expect(true)->toBeFalse('DIAG '.json_encode($diag));
});
