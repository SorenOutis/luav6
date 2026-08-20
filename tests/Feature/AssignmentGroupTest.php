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
    $this->sectionA = Section::factory()->forSeason($this->season)->create(['name' => 'Alpha']);
    $this->sectionB = Section::factory()->forSeason($this->season)->create(['name' => 'Beta']);

    $this->creator = User::factory()->create(['name' => 'Mina Cruz']);
    $this->creator->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);
    $this->member = User::factory()->create(['name' => 'Jose Santos']);
    $this->member->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);
    $this->outsider = User::factory()->create(['name' => 'Lena Park']);
    $this->outsider->sections()->attach($this->sectionB->id, ['season_id' => $this->season->id]);
});

function makeGroupAssignment(array $sectionIds): Assignment
{
    $assignment = Assignment::create([
        'title' => 'Group activity task',
        'due_date' => now()->addWeek(),
    ]);

    $assignment->sections()->sync($sectionIds);
    app(AssignmentRosterService::class)->syncAssignment($assignment);

    return $assignment;
}

function inviteMember(User $inviter, Assignment $assignment, User $invitee): AssignmentGroupInvite
{
    test()->actingAs($inviter)
        ->post(route('assignments.invites.store', $assignment), ['user_ids' => [$invitee->id]])
        ->assertRedirect();

    return AssignmentGroupInvite::query()
        ->where('assignment_id', $assignment->id)
        ->where('invitee_id', $invitee->id)
        ->where('status', 'pending')
        ->firstOrFail();
}

function acceptInvite(User $invitee, Assignment $assignment): void
{
    $invite = AssignmentGroupInvite::query()
        ->where('assignment_id', $assignment->id)
        ->where('invitee_id', $invitee->id)
        ->where('status', 'pending')
        ->firstOrFail();

    test()->actingAs($invitee)
        ->post(route('assignments.invites.respond', [$assignment, $invite]), ['action' => 'accept'])
        ->assertRedirect();
}

it('lets a student form a group with a classmate through invites', function () {
    $assignment = makeGroupAssignment([$this->sectionA->id]);

    // Nobody is grouped until the classmate accepts.
    inviteMember($this->creator, $assignment, $this->member);

    $group = AssignmentGroup::where('assignment_id', $assignment->id)->first();
    expect($group)->not->toBeNull()
        ->and($group->created_by)->toBe($this->creator->id)
        ->and(DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $this->member->id)
            ->value('group_id'))->toBeNull();

    acceptInvite($this->member, $assignment);

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->creator->id)
        ->value('group_id'))->toBe($group->id)
        ->and(DB::table('assignment_user')
            ->where('assignment_id', $assignment->id)
            ->where('user_id', $this->member->id)
            ->value('group_id'))->toBe($group->id);
});

it('rejects inviting a student who is not assigned to the activity', function () {
    $assignment = makeGroupAssignment([$this->sectionA->id]);

    $this->actingAs($this->creator)
        ->post(route('assignments.invites.store', $assignment), ['user_ids' => [$this->outsider->id]])
        ->assertStatus(422);

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->outsider->id)
        ->value('group_id'))->toBeNull();
});

it('rejects inviting a student who already accepted another group', function () {
    $assignment = makeGroupAssignment([$this->sectionA->id]);

    $secondCreator = User::factory()->create(['name' => 'Ana Reyes']);
    $secondCreator->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    inviteMember($this->creator, $assignment, $this->member);
    acceptInvite($this->member, $assignment);

    $this->actingAs($secondCreator)
        ->post(route('assignments.invites.store', $assignment), ['user_ids' => [$this->member->id]])
        ->assertStatus(422);
});

it('only allows the group creator to invite members', function () {
    $assignment = makeGroupAssignment([$this->sectionA->id]);

    inviteMember($this->creator, $assignment, $this->member);
    acceptInvite($this->member, $assignment);

    // An accepted member (not the creator) cannot invite anyone.
    $this->actingAs($this->member)
        ->post(route('assignments.invites.store', $assignment), ['user_ids' => [$this->outsider->id]])
        ->assertForbidden();
});

it('shares the submitted file with every group member', function () {
    Storage::fake('public');

    $assignment = makeGroupAssignment([$this->sectionA->id]);

    inviteMember($this->creator, $assignment, $this->member);
    acceptInvite($this->member, $assignment);

    $this->actingAs($this->creator)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('group-work.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect();

    $rows = DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->whereIn('user_id', [$this->creator->id, $this->member->id])
        ->get();

    expect($rows)->toHaveCount(2);

    foreach ($rows as $row) {
        expect($row->submitted)->toBe(1)
            ->and($row->status)->toBe('Submitted')
            ->and($row->file_path)->not->toBeNull()
            ->and($row->submitted_by)->toBe($this->creator->id);
    }

    expect($rows->pluck('file_path')->unique())->toHaveCount(1);

    // Both members see the same file on their assignments page.
    foreach ([$this->creator, $this->member] as $student) {
        $this->actingAs($student)
            ->get(route('assignments.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Assignments')
                ->has('assignments', 1)
                ->has('assignments.0.group.members', 2)
                ->where('assignments.0.group.members.0.name', 'Mina Cruz')
                ->where('assignments.0.group.members.1.name', 'Jose Santos')
                ->where('assignments.0.submission.submitted', true)
                ->where('assignments.0.submission.file_path', fn ($path) => is_string($path) && $path !== '')
                ->where('assignments.0.submission.submitted_by', $this->creator->id));
    }
});

it('lets a late joiner see the file the group already submitted', function () {
    Storage::fake('public');

    $assignment = makeGroupAssignment([$this->sectionA->id]);

    $latecomer = User::factory()->create(['name' => 'Tina Ocampo']);
    $latecomer->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    // Invite first, submit while it is still pending (never blocked), then
    // the latecomer accepts and inherits the shared file.
    inviteMember($this->creator, $assignment, $latecomer);

    $this->actingAs($this->creator)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect();

    acceptInvite($latecomer, $assignment);

    $row = DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $latecomer->id)
        ->first();

    expect($row->submitted)->toBe(1)
        ->and($row->status)->toBe('Submitted')
        ->and($row->file_path)->not->toBeNull();

    $this->actingAs($latecomer)
        ->get(route('assignments.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('assignments.0.submission.submitted', true)
            ->where('assignments.0.submission.file_url', fn ($url) => is_string($url) && $url !== ''));
});

it('resets a removed member to pending and hides the shared file', function () {
    Storage::fake('public');

    $assignment = makeGroupAssignment([$this->sectionA->id]);

    inviteMember($this->creator, $assignment, $this->member);
    acceptInvite($this->member, $assignment);

    $this->actingAs($this->creator)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect();

    $this->actingAs($this->creator)
        ->delete(route('assignments.groups.members.destroy', [$assignment, $this->member]))
        ->assertRedirect();

    $row = DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->member->id)
        ->first();

    expect($row->group_id)->toBeNull()
        ->and($row->submitted)->toBe(0)
        ->and($row->status)->toBe('Pending')
        ->and($row->file_path)->toBeNull();

    // The creator's row keeps the shared file.
    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->creator->id)
        ->value('file_path'))->not->toBeNull();
});

it('transfers creator role when the creator leaves the group', function () {
    $assignment = makeGroupAssignment([$this->sectionA->id]);

    inviteMember($this->creator, $assignment, $this->member);
    acceptInvite($this->member, $assignment);

    $group = AssignmentGroup::where('assignment_id', $assignment->id)->firstOrFail();

    $this->actingAs($this->creator)
        ->delete(route('assignments.groups.members.destroy', [$assignment, $this->creator]))
        ->assertRedirect();

    expect($group->fresh()->created_by)->toBe($this->member->id);
});

it('deletes the group when the last member leaves', function () {
    $assignment = makeGroupAssignment([$this->sectionA->id]);

    // A group forms only once an invite has been sent.
    $invite = inviteMember($this->creator, $assignment, $this->member);
    $group = $invite->group;

    $this->actingAs($this->creator)
        ->delete(route('assignments.groups.members.destroy', [$assignment, $this->creator]))
        ->assertRedirect();

    expect(AssignmentGroup::find($group->id))->toBeNull();
});

it('applies a group grade to every member and awards each one points and xp', function () {
    $assignment = makeGroupAssignment([$this->sectionA->id]);

    inviteMember($this->creator, $assignment, $this->member);
    acceptInvite($this->member, $assignment);

    $creatorRow = Submission::where('assignment_id', $assignment->id)->where('user_id', $this->creator->id)->firstOrFail();
    $memberRow = Submission::where('assignment_id', $assignment->id)->where('user_id', $this->member->id)->firstOrFail();

    $creatorRow->update([
        'status' => 'Graded',
        'grade' => '95',
        'points' => 10,
        'xp_earned' => 5,
        'feedback' => 'Excellent group work!',
    ]);

    foreach ([$creatorRow, $memberRow] as $row) {
        $fresh = Submission::findOrFail($row->id);
        expect($fresh->status)->toBe('Graded')
            ->and($fresh->grade)->toBe('95')
            ->and((float) $fresh->points)->toBe(10.0)
            ->and((float) $fresh->xp_earned)->toBe(5.0)
            ->and($fresh->feedback)->toBe('Excellent group work!');
    }

    // Each member earned the points/XP through the existing award path.
    foreach ([$this->creator, $this->member] as $student) {
        $progress = $student->activeSectionProgress($this->sectionA->id);
        expect((float) $progress->points)->toBe(10.0)
            ->and((float) $progress->exp)->toBe(5.0);
    }
});

it('locks a graded group against add, remove and resubmit', function () {
    Storage::fake('public');

    $assignment = makeGroupAssignment([$this->sectionA->id]);

    inviteMember($this->creator, $assignment, $this->member);
    acceptInvite($this->member, $assignment);

    $this->actingAs($this->creator)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('work.pdf', 10, 'application/pdf'),
        ])
        ->assertRedirect();

    Submission::where('assignment_id', $assignment->id)
        ->where('user_id', $this->creator->id)
        ->update(['status' => 'Graded', 'grade' => 'A', 'points' => 5, 'xp_earned' => 2]);

    $other = User::factory()->create(['name' => 'Other Kid']);
    $other->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $this->actingAs($this->creator)
        ->post(route('assignments.invites.store', $assignment), ['user_ids' => [$other->id]])
        ->assertForbidden();

    $this->actingAs($this->member)
        ->delete(route('assignments.groups.members.destroy', [$assignment, $this->member]))
        ->assertStatus(403);

    $this->actingAs($this->member)
        ->post(route('assignments.submit', $assignment), [
            'file' => UploadedFile::fake()->create('late.pdf', 10, 'application/pdf'),
        ])
        ->assertStatus(403);

    // The shared group file was not replaced by the rejected resubmit.
    $creatorPath = DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->creator->id)
        ->value('file_path');

    expect(DB::table('assignment_user')
        ->where('assignment_id', $assignment->id)
        ->where('user_id', $this->member->id)
        ->value('file_path'))->toBe($creatorPath);
});

it('excludes self, already-grouped students and out-of-section students from candidates', function () {
    $assignment = makeGroupAssignment([$this->sectionA->id]);

    inviteMember($this->creator, $assignment, $this->member);
    acceptInvite($this->member, $assignment);

    $this->actingAs($this->creator)
        ->getJson(route('assignments.groups.candidates', $assignment))
        ->assertOk()
        ->assertJsonPath('candidates', []);

    // Add a searchable, eligible classmate.
    $available = User::factory()->create(['name' => 'Zoe Dela Cruz']);
    $available->sections()->attach($this->sectionA->id, ['season_id' => $this->season->id]);

    $this->actingAs($this->creator)
        ->getJson(route('assignments.groups.candidates', $assignment).'?q=Zoe')
        ->assertOk()
        ->assertJsonPath('candidates.0.id', $available->id)
        ->assertJsonPath('candidates.0.sections.0', 'Alpha');

    // The only candidate is the available classmate: self, the already-grouped
    // member and the out-of-section student are all excluded.
    expect($this->actingAs($this->creator)
        ->getJson(route('assignments.groups.candidates', $assignment).'?q=Zoe')
        ->json('candidates'))->toHaveCount(1);
});
