<?php

use App\Models\Assignment;
use App\Models\AssignmentGroup;
use App\Models\AssignmentGroupInvite;
use App\Models\Season;
use App\Models\Section;
use App\Models\Submission;
use App\Models\User;
use App\Services\AssignmentRosterService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->season = Season::factory()->active()->create();
    $this->section = Section::factory()->forSeason($this->season)->create(['name' => 'Alpha']);

    $this->creator = User::factory()->create(['name' => 'Mina Cruz']);
    $this->creator->sections()->attach($this->section->id, ['season_id' => $this->season->id]);
    $this->member = User::factory()->create(['name' => 'Jose Santos']);
    $this->member->sections()->attach($this->section->id, ['season_id' => $this->season->id]);
    $this->other = User::factory()->create(['name' => 'Ana Reyes']);
    $this->other->sections()->attach($this->section->id, ['season_id' => $this->season->id]);
});

function makeInviteAssignment(array $attributes = []): Assignment
{
    $assignment = Assignment::create(array_merge([
        'title' => 'Group activity task',
        'due_date' => now()->addWeek(),
    ], $attributes));

    $assignment->sections()->sync([test()->section->id]);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    return $assignment;
}

function sendInvites(User $inviter, Assignment $assignment, array $userIds)
{
    return test()->actingAs($inviter)
        ->post(route('assignments.invites.store', $assignment), ['user_ids' => $userIds]);
}

function pendingInviteFor(User $invitee, Assignment $assignment): AssignmentGroupInvite
{
    return AssignmentGroupInvite::query()
        ->where('assignment_id', $assignment->id)
        ->where('invitee_id', $invitee->id)
        ->where('status', 'pending')
        ->firstOrFail();
}

function respondToInvite(User $invitee, Assignment $assignment, string $action)
{
    $invite = pendingInviteFor($invitee, $assignment);

    return test()->actingAs($invitee)
        ->post(route('assignments.invites.respond', [$assignment, $invite]), ['action' => $action]);
}

it('creates the group on first send and notifies each invitee', function () {
    $assignment = makeInviteAssignment();

    sendInvites($this->creator, $assignment, [$this->member->id, $this->other->id])
        ->assertRedirect();

    $group = AssignmentGroup::where('assignment_id', $assignment->id)->firstOrFail();
    expect($group->created_by)->toBe($this->creator->id);

    foreach ([$this->member, $this->other] as $invitee) {
        // Pending invite + bell notification, but NOT membership yet.
        expect(
            DB::table('assignment_user')
                ->where('assignment_id', $assignment->id)
                ->where('user_id', $invitee->id)
                ->value('group_id')
        )->toBeNull()
            ->and($invitee->notifications()->count())->toBe(1)
            ->and($invitee->notifications()->first()->data['type'])->toBe('assignment_invite');
    }
});

it('adds the invitee to the group when they accept and tells the creator', function () {
    $assignment = makeInviteAssignment();
    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();

    respondToInvite($this->member, $assignment, 'accept')->assertRedirect();

    $group = AssignmentGroup::where('assignment_id', $assignment->id)->firstOrFail();

    expect(
        DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $this->member->id)
            ->value('group_id')
    )->toBe($group->id)
        ->and(
            AssignmentGroupInvite::query()
                ->where('assignment_id', $assignment->id)
                ->where('invitee_id', $this->member->id)
                ->value('status')
        )->toBe('accepted')
        ->and($this->creator->notifications()->first()->data['type'])->toBe('invite_accepted');
});

it('lets the invitee decline, frees the slot, and allows a re-invite', function () {
    $assignment = makeInviteAssignment();
    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();

    respondToInvite($this->member, $assignment, 'decline')->assertRedirect();

    expect(
        DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $this->member->id)
            ->value('group_id')
    )->toBeNull();

    // Declining answers one invitation; it does not block the next one.
    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();
    expect(pendingInviteFor($this->member, $assignment))->toBeInstanceOf(AssignmentGroupInvite::class);
});

it('lets the creator cancel a pending invite, returning the classmate to the candidate list', function () {
    $assignment = makeInviteAssignment();
    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();
    $invite = pendingInviteFor($this->member, $assignment);

    $this->actingAs($this->creator)
        ->delete(route('assignments.invites.destroy', [$assignment, $invite]))
        ->assertRedirect();

    expect($invite->fresh()->status)->toBe('cancelled');

    $this->actingAs($this->creator)
        ->get(route('assignments.groups.candidates', $assignment))
        ->assertOk()
        ->assertJsonFragment(['id' => $this->member->id]);
});

it('allows only one pending invite per student per assignment', function () {
    $assignment = makeInviteAssignment();

    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();

    // A second group courts the same student while the first invite is
    // still pending — the service invariant rejects it and the 422 rolls
    // back the second group's creation.
    sendInvites($this->other, $assignment, [$this->member->id])->assertStatus(422);

    // The original invite is untouched.
    expect(pendingInviteFor($this->member, $assignment))->toBeInstanceOf(AssignmentGroupInvite::class);
});

it('only allows the group creator to invite members', function () {
    $assignment = makeInviteAssignment();
    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();
    respondToInvite($this->member, $assignment, 'accept')->assertRedirect();

    // An accepted member (not the creator) cannot invite anyone.
    sendInvites($this->member, $assignment, [$this->other->id])->assertForbidden();
});

it('rejects inviting a student who is not assigned to the activity', function () {
    $assignment = makeInviteAssignment();

    $outsider = User::factory()->create(['name' => 'Lena Park']);
    // Deliberately not attached to the targeted section.

    sendInvites($this->creator, $assignment, [$outsider->id])->assertStatus(422);

    expect(AssignmentGroupInvite::where('invitee_id', $outsider->id)->exists())->toBeFalse();
});

it('enforces the teacher cap on invites and acceptances', function () {
    $assignment = makeInviteAssignment(['max_group_size' => 2]);

    // Creator + member + other would exceed the cap of 2.
    sendInvites($this->creator, $assignment, [$this->member->id, $this->other->id])
        ->assertStatus(422);

    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();

    // Slot is held by the pending invite: no more room.
    sendInvites($this->creator, $assignment, [$this->other->id])->assertStatus(422);

    respondToInvite($this->member, $assignment, 'accept')->assertRedirect();

    // Full after acceptance too.
    sendInvites($this->creator, $assignment, [$this->other->id])->assertStatus(422);
});

it('expires pending invites at the due date', function () {
    $assignment = makeInviteAssignment(['due_date' => now()->subDay()]);

    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();

    // The invite is born past its expiry: responding flips it to expired.
    respondToInvite($this->member, $assignment, 'accept')->assertStatus(422);

    expect(
        AssignmentGroupInvite::query()
            ->where('assignment_id', $assignment->id)
            ->where('invitee_id', $this->member->id)
            ->value('status')
    )->toBe('expired');

    // The page load sweep marks stale pendings expired as well.
    sendInvites($this->creator, $assignment, [$this->other->id])->assertRedirect();
    $this->actingAs($this->other)->get(route('assignments.index'))->assertOk();

    expect(
        AssignmentGroupInvite::where('invitee_id', $this->other->id)
            ->where('status', 'expired')
            ->exists()
    )->toBeTrue();
});

it('locks invites and acceptances once the group is graded', function () {
    $assignment = makeInviteAssignment();
    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();

    Submission::query()
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->creator->id)
        ->update(['status' => 'Graded', 'grade' => 'A', 'points' => 5, 'xp_earned' => 2]);

    respondToInvite($this->member, $assignment, 'accept')->assertForbidden();

    sendInvites($this->creator, $assignment, [$this->other->id])->assertForbidden();
});

it('lets a late acceptance inherit the file the group already submitted', function () {
    Storage::fake('public');
    $assignment = makeInviteAssignment();

    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();

    // Submit while the invite is still pending — never blocked by group state.
    $this->actingAs($this->creator)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('group-work.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect();

    respondToInvite($this->member, $assignment, 'accept')->assertRedirect();

    $row = DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->member->id)
        ->first();

    expect($row->group_id)->not->toBeNull()
        ->and($row->submitted)->toBe(1)
        ->and($row->file_path)->not->toBeNull();
});

it('never blocks solo submission regardless of group minimum', function () {
    Storage::fake('public');
    $assignment = makeInviteAssignment(['min_group_size' => 3]);

    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();
    respondToInvite($this->member, $assignment, 'decline')->assertRedirect();

    // Below the advisory minimum, alone — submission still goes through.
    $this->actingAs($this->creator)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('solo.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect();

    expect(
        DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $this->creator->id)
            ->value('submitted')
    )->toBe(1);
});

it('exposes invites and size rules on the assignments page payload', function () {
    $assignment = makeInviteAssignment(['min_group_size' => 2, 'max_group_size' => 4]);
    sendInvites($this->creator, $assignment, [$this->member->id])->assertRedirect();

    // Invitee sees their incoming invite banner data.
    $this->actingAs($this->member)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.group_rules.min', 2)
            ->where('assignments.0.group_rules.max', 4)
            ->where('assignments.0.incoming_invite.inviter.name', 'Mina Cruz'));

    // Creator sees who is still pending on their group.
    $this->actingAs($this->creator)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.group.pending_invites.0.user.name', 'Jose Santos'));
});

it('disallows group formation and invites when max_group_size is set to 1', function () {
    $assignment = makeInviteAssignment(['max_group_size' => 1]);

    sendInvites($this->creator, $assignment, [$this->member->id])
        ->assertStatus(422);

    $this->actingAs($this->creator)
        ->get(route('assignments.groups.candidates', $assignment))
        ->assertOk()
        ->assertJsonCount(0, 'candidates');
});
