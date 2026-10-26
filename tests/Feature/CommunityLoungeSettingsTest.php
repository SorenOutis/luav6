<?php

use App\Filament\Pages\LoungeSettings;
use App\Models\CommunityInvite;
use App\Models\CommunityPost;
use App\Models\CommunityStory;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CommunitySettings;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->admin = User::factory()->superAdmin()->create(['current_workspace_id' => $this->workspace->id]);

    $this->sectionA = Section::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Section Alpha',
    ]);
    $this->sectionB = Section::factory()->create([
        'workspace_id' => $this->workspace->id,
        'name' => 'Section Beta',
    ]);

    $this->studentA = User::factory()->create(['current_workspace_id' => $this->workspace->id]);
    $this->studentA->sections()->attach($this->sectionA);
    CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentA->id,
        'token' => 'token-a-'.uniqid(),
        'accepted_at' => now(),
    ]);

    $this->studentB = User::factory()->create(['current_workspace_id' => $this->workspace->id]);
    $this->studentB->sections()->attach($this->sectionB);
    CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentB->id,
        'token' => 'token-b-'.uniqid(),
        'accepted_at' => now(),
    ]);
});

it('forbids non-admin students from accessing lounge settings filament page', function () {
    actingAs($this->studentA)
        ->get('/admin/community-settings')
        ->assertForbidden();
});

it('allows admin to view and update lounge settings via filament page', function () {
    actingAs($this->admin)
        ->get('/admin/community-settings')
        ->assertSuccessful()
        ->assertSee('Community Lounge Settings')
        ->assertSee('Post & Feed Visibility');

    Livewire::actingAs($this->admin)
        ->test(LoungeSettings::class)
        ->assertSet('data.feed_scope', CommunitySettings::SCOPE_WORKSPACE)
        ->fillForm([
            'feed_scope' => CommunitySettings::SCOPE_SECTION,
            'allow_anonymous' => false,
            'stories_enabled' => true,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(CommunitySettings::feedScope())->toBe(CommunitySettings::SCOPE_SECTION);
    expect(CommunitySettings::isRestrictedToSection())->toBeTrue();
    expect(CommunitySettings::allowsAnonymous())->toBeFalse();
});

it('shows posts across all sections in workspace feed when feed scope is workspace', function () {
    CommunitySettings::save(['feed_scope' => CommunitySettings::SCOPE_WORKSPACE]);

    $postA = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentA->id,
        'section_id' => $this->sectionA->id,
        'content' => 'Alpha section greeting',
    ]);

    $postB = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentB->id,
        'section_id' => $this->sectionB->id,
        'content' => 'Beta section announcement',
    ]);

    // Student A visits main community feed (all)
    $response = actingAs($this->studentA)->get('/community?tab=all');
    $response->assertSuccessful();

    $response->assertInertia(fn ($page) => $page
        ->component('Community')
        ->where('isSectionOnly', false)
        ->has('posts', 2)
    );
});

it('restricts feed to student section when section-only mode is active', function () {
    CommunitySettings::save(['feed_scope' => CommunitySettings::SCOPE_SECTION]);

    $postA = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentA->id,
        'section_id' => $this->sectionA->id,
        'content' => 'Alpha section exclusive discussion',
    ]);

    $postB = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentB->id,
        'section_id' => $this->sectionB->id,
        'content' => 'Beta section exclusive discussion',
    ]);

    // Student A should only see Post A, never Post B
    $responseA = actingAs($this->studentA)->get('/community?tab=all');
    $responseA->assertSuccessful();

    $responseA->assertInertia(fn ($page) => $page
        ->component('Community')
        ->where('isSectionOnly', true)
        ->has('posts', 1)
        ->where('posts.0.id', $postA->id)
    );

    // Student B should only see Post B, never Post A
    $responseB = actingAs($this->studentB)->get('/community?tab=all');
    $responseB->assertSuccessful();

    $responseB->assertInertia(fn ($page) => $page
        ->component('Community')
        ->where('isSectionOnly', true)
        ->has('posts', 1)
        ->where('posts.0.id', $postB->id)
    );
});

it('blocks cross-section comments, likes, and bookmarks when section-only mode is active', function () {
    CommunitySettings::save(['feed_scope' => CommunitySettings::SCOPE_SECTION]);

    $postA = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentA->id,
        'section_id' => $this->sectionA->id,
        'content' => 'Alpha post strictly for section A',
    ]);

    // Student B attempts to comment on Post A
    actingAs($this->studentB)
        ->postJson(route('community.posts.comments.store', $postA), [
            'content' => 'Cross-section comment attempt',
        ])
        ->assertForbidden();

    // Student B attempts to like Post A
    actingAs($this->studentB)
        ->postJson(route('community.posts.like', $postA))
        ->assertForbidden();

    // Student B attempts to bookmark Post A
    actingAs($this->studentB)
        ->postJson(route('community.posts.bookmark', $postA))
        ->assertForbidden();

    // Student A can comment and like normally on their own section post
    actingAs($this->studentA)
        ->postJson(route('community.posts.comments.store', $postA), [
            'content' => 'Valid section comment',
        ])
        ->assertSuccessful();

    actingAs($this->studentA)
        ->postJson(route('community.posts.like', $postA))
        ->assertSuccessful();
});

it('isolates 24h stories tray to student section in section-only mode', function () {
    CommunitySettings::save(['feed_scope' => CommunitySettings::SCOPE_SECTION]);

    $storyA = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentA->id,
        'media_url' => 'stories/alpha.jpg',
        'media_type' => 'image',
        'duration_seconds' => 5,
        'expires_at' => now()->addDay(),
    ]);

    $storyB = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentB->id,
        'media_url' => 'stories/beta.jpg',
        'media_type' => 'image',
        'duration_seconds' => 5,
        'expires_at' => now()->addDay(),
    ]);

    // Student A visits community: stories tray should only have Student A's story, not Student B's
    $response = actingAs($this->studentA)->get('/community');
    $response->assertSuccessful();

    $response->assertInertia(fn ($page) => $page
        ->component('Community')
        ->has('stories', 1)
        ->where('stories.0.user_id', $this->studentA->id)
    );
});
