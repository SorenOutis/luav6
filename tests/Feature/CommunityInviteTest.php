<?php

use App\Filament\Pages\CommunityInvites;
use App\Models\CommunityInvite;
use App\Models\CommunityPost;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Support\StudentPageRegistry;
use Inertia\Testing\AssertableInertia as Assert;
use Livewire\Livewire;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->student = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);
});

it('renders locked gate when student has no invite', function () {
    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CommunityLocked')
            ->where('studentName', $this->student->name)
            ->where('hasPendingInvite', false)
        );
});

it('renders locked gate when student has an unaccepted invite', function () {
    CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'pending-token-123',
        'accepted_at' => null,
    ]);

    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CommunityLocked')
            ->where('hasPendingInvite', true)
        );
});

it('renders community lounge feed when student has accepted invite', function () {
    CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'active-token-123',
        'accepted_at' => now(),
    ]);

    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
            ->has('posts')
        );
});

it('allows admin and super admin to bypass locked gate without an invite', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'current_workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($admin)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
        );

    $superAdmin = User::factory()->create([
        'is_admin' => true,
        'is_super_admin' => true,
        'current_workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($superAdmin)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
        );
});

it('redirects unauthenticated guest to login and remembers intended join url', function () {
    $invite = CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'secure-link-xyz',
        'accepted_at' => null,
    ]);

    $response = $this->get(route('community.join', ['token' => $invite->token]));

    $response->assertRedirect(route('login'));
    expect(session('url.intended'))->toBe(route('community.join', ['token' => $invite->token]));
});

it('flashes error when student attempts to use an invalid or expired join token', function () {
    $this->actingAs($this->student)
        ->get(route('community.join', ['token' => 'nonexistent-token']))
        ->assertRedirect(route('community.index'))
        ->assertSessionHas('error');
});

it('blocks student from using an invite generated for another student', function () {
    $otherStudent = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    $invite = CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $otherStudent->id,
        'token' => 'other-student-token',
        'accepted_at' => null,
    ]);

    $this->actingAs($this->student)
        ->get(route('community.join', ['token' => $invite->token]))
        ->assertRedirect(route('community.index'))
        ->assertSessionHas('error');

    expect($invite->fresh()->isAccepted())->toBeFalse();
    expect($this->student->fresh()->hasLoungeAccess())->toBeFalse();
});

it('unlocks lounge access when student opens their personal join link', function () {
    $invite = CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'student-valid-token',
        'accepted_at' => null,
    ]);

    $this->actingAs($this->student)
        ->get(route('community.join', ['token' => $invite->token]))
        ->assertRedirect(route('community.index'))
        ->assertSessionHas('success');

    expect($invite->fresh()->isAccepted())->toBeTrue();
    expect($this->student->fresh()->hasLoungeAccess())->toBeTrue();
});

it('redeems invite code via join-by-code and unlocks lounge', function () {
    $invite = CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'CODE-ABC-123',
        'accepted_at' => null,
    ]);

    $this->actingAs($this->student)
        ->post(route('community.join-by-code'), [
            'code' => 'CODE-ABC-123',
        ])
        ->assertRedirect(route('community.index'))
        ->assertSessionHas('success');

    expect($invite->fresh()->isAccepted())->toBeTrue();
    expect($this->student->fresh()->hasLoungeAccess())->toBeTrue();
});

it('extracts token when student pastes entire join url into code input', function () {
    $invite = CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'URL-TOKEN-999',
        'accepted_at' => null,
    ]);

    $fullUrl = "https://luav6.test/community/join/{$invite->token}";

    $this->actingAs($this->student)
        ->postJson(route('community.join-by-code'), [
            'code' => $fullUrl,
        ])
        ->assertOk()
        ->assertJson([
            'status' => 'unlocked',
        ]);

    expect($invite->fresh()->isAccepted())->toBeTrue();
});

it('rejects invalid invite code on join-by-code', function () {
    $this->actingAs($this->student)
        ->post(route('community.join-by-code'), [
            'code' => 'WRONG-CODE',
        ])
        ->assertSessionHasErrors(['code']);
});

it('blocks student without access from posting, commenting, and polling presence', function () {
    // Attempt to post without invite
    $this->actingAs($this->student)
        ->post(route('community.posts.store'), [
            'content' => 'Hello locked world',
        ])
        ->assertForbidden();

    // Attempt to poll presence without invite
    $this->actingAs($this->student)
        ->getJson(route('community.presence'))
        ->assertForbidden();

    // Create a post as admin
    $admin = User::factory()->create(['is_admin' => true, 'current_workspace_id' => $this->workspace->id]);
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
    ]);

    // Attempt to comment without invite
    $this->actingAs($this->student)
        ->post(route('community.posts.comments.store', $post), [
            'content' => 'Sneaking in a comment',
        ])
        ->assertForbidden();
});

it('allows admin to manage, generate, and regenerate invite links on filament page', function () {
    $admin = User::factory()->create([
        'is_admin' => true,
        'current_workspace_id' => $this->workspace->id,
    ]);

    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->student->sections()->attach($section);

    $this->actingAs($admin);

    // Initial state: no invite exists
    expect(CommunityInvite::where('user_id', $this->student->id)->exists())->toBeFalse();

    // Livewire: select student -> provisions invite
    $component = Livewire::test(CommunityInvites::class)
        ->call('selectStudent', $this->student->id)
        ->assertSet('selectedUserId', $this->student->id);

    $invite = CommunityInvite::where('user_id', $this->student->id)->first();
    expect($invite)->not->toBeNull();
    $firstToken = $invite->token;

    // Regenerate invite -> updates token and invalidates old
    $component->call('regenerateInvite', $this->student->id);
    $freshInvite = $invite->fresh();
    expect($freshInvite->token)->not->toBe($firstToken);
    expect($freshInvite->accepted_at)->toBeNull();

    // Grant immediate access
    $component->call('grantAccess', $this->student->id);
    expect($freshInvite->fresh()->isAccepted())->toBeTrue();

    // Revoke access
    $component->call('revokeAccess', $this->student->id);
    expect($freshInvite->fresh()->isAccepted())->toBeFalse();
});

it('flashes first_time_lounge_join on initial invite acceptance and delivers isFirstTimeJoin to Inertia', function () {
    $invite = CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'first-join-token-111',
        'accepted_at' => null,
    ]);

    $this->actingAs($this->student)
        ->get(route('community.join', ['token' => $invite->token]))
        ->assertRedirect(route('community.index'))
        ->assertSessionHas('first_time_lounge_join', true);

    // Follow redirect with session
    $this->actingAs($this->student)
        ->withSession(['first_time_lounge_join' => true])
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
            ->where('isFirstTimeJoin', true)
        );

    // Subsequent visit without flash has isFirstTimeJoin false
    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
            ->where('isFirstTimeJoin', false)
        );
});

it('does not flash first_time_lounge_join when re-opening an already accepted invite link', function () {
    $invite = CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'already-accepted-token',
        'accepted_at' => now()->subDay(),
    ]);

    $this->actingAs($this->student)
        ->get(route('community.join', ['token' => $invite->token]))
        ->assertRedirect(route('community.index'))
        ->assertSessionHas('first_time_lounge_join', false);
});

it('blocks access with 423 unavailable when community page is disabled in student page controls', function () {
    CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'active-token-999',
        'accepted_at' => now(),
    ]);

    // Set community page control to disabled
    $controls = StudentPageRegistry::controls();
    $controls['community'] = [
        'mode' => StudentPageRegistry::MODE_DISABLED,
        'message' => 'Lounge is closed for exams maintenance.',
    ];
    StudentPageRegistry::setControls($controls);

    // Student GET /community -> 423 StudentPageUnavailable
    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertStatus(423)
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentPageUnavailable')
            ->where('pageTitle', 'Community Lounge')
            ->where('message', 'Lounge is closed for exams maintenance.')
        );

    // Student GET /community/join/{token} -> 423 StudentPageUnavailable
    $this->actingAs($this->student)
        ->get(route('community.join', ['token' => 'active-token-999']))
        ->assertStatus(423);

    // Student POST /community/posts -> 403 Forbidden
    $this->actingAs($this->student)
        ->post(route('community.posts.store'), ['content' => 'Blocked post'])
        ->assertForbidden();

    // Admin bypasses disabled community control
    $admin = User::factory()->create(['is_admin' => true, 'current_workspace_id' => $this->workspace->id]);
    $this->actingAs($admin)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
        );

    // Reset controls
    $controls['community'] = [
        'mode' => StudentPageRegistry::MODE_ENABLED,
        'message' => null,
    ];
    StudentPageRegistry::setControls($controls);
});
