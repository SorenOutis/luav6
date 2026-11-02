<?php

use App\Filament\Pages\LoungeSettings;
use App\Models\CommunityInvite;
use App\Models\CommunityPost;
use App\Models\CommunityStory;
use App\Models\ProfileMusicTrack;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Support\CommunitySettings;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
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
        ->assertSet('data.music_enabled', true)
        ->assertSet('data.media_uploads_enabled', true)
        ->assertSet('data.file_attachments_enabled', true)
        ->assertSet('data.pwa_enabled', true)
        ->fillForm([
            'feed_scope' => CommunitySettings::SCOPE_SECTION,
            'allow_anonymous' => false,
            'stories_enabled' => true,
            'music_enabled' => false,
            'media_uploads_enabled' => false,
            'file_attachments_enabled' => false,
            'pwa_enabled' => false,
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect(CommunitySettings::feedScope())->toBe(CommunitySettings::SCOPE_SECTION);
    expect(CommunitySettings::isRestrictedToSection())->toBeTrue();
    expect(CommunitySettings::allowsAnonymous())->toBeFalse();
    expect(CommunitySettings::musicEnabled())->toBeFalse();
    expect(CommunitySettings::mediaUploadsEnabled())->toBeFalse();
    expect(CommunitySettings::fileAttachmentsEnabled())->toBeFalse();
    expect(CommunitySettings::pwaEnabled())->toBeFalse();
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

it('disables music tracks on community page and ignores music attachments when music is disabled', function () {
    $track = ProfileMusicTrack::factory()->create([
        'workspace_id' => $this->workspace->id,
        'title' => 'Lofi Study Beats',
        'artist' => 'Chill Artist',
        'is_active' => true,
    ]);

    CommunitySettings::save(['music_enabled' => false]);

    $response = actingAs($this->studentA)->get('/community');
    $response->assertSuccessful();

    $response->assertInertia(fn ($page) => $page
        ->component('Community')
        ->where('musicEnabled', false)
        ->has('musicTracks', 0)
    );

    // Posting with profile_music_track_id should not attach the track
    actingAs($this->studentA)
        ->post(route('community.posts.store'), [
            'content' => 'Post with music while music is disabled',
            'profile_music_track_id' => $track->id,
        ])
        ->assertRedirect();

    $createdPost = CommunityPost::latest('id')->first();
    expect($createdPost->content)->toBe('Post with music while music is disabled');
    expect($createdPost->profile_music_track_id)->toBeNull();
});

it('blocks media uploads when media uploads are disabled', function () {
    Storage::fake('public');
    CommunitySettings::save(['media_uploads_enabled' => false]);

    $response = actingAs($this->studentA)->get('/community');
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Community')
        ->where('mediaUploadsEnabled', false)
    );

    actingAs($this->studentA)
        ->post(route('community.posts.store'), [
            'content' => 'Attempting photo upload',
            'attachment' => UploadedFile::fake()->image('photo.jpg'),
        ])
        ->assertStatus(422);

    actingAs($this->studentA)
        ->post(route('community.posts.store'), [
            'content' => 'Attempting multi media upload',
            'attachments' => [UploadedFile::fake()->image('photo1.jpg')],
        ])
        ->assertStatus(422);
});

it('blocks file attachments when document and code attachments are disabled', function () {
    Storage::fake('public');
    CommunitySettings::save(['file_attachments_enabled' => false]);

    $response = actingAs($this->studentA)->get('/community');
    $response->assertSuccessful();
    $response->assertInertia(fn ($page) => $page
        ->component('Community')
        ->where('fileAttachmentsEnabled', false)
    );

    actingAs($this->studentA)
        ->post(route('community.posts.store'), [
            'content' => 'Attempting snippet upload',
            'attachment' => UploadedFile::fake()->create('script.py', 100, 'text/x-python'),
        ])
        ->assertStatus(422);
});

it('only computes trending topics from visible feed posts and excludes hidden cross-section posts', function () {
    CommunitySettings::save(['feed_scope' => CommunitySettings::SCOPE_SECTION]);

    // Section B post with #NoClass and #NoPasok (hidden from Student A in Section Alpha)
    CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentB->id,
        'section_id' => $this->sectionB->id,
        'content' => 'Walang pasok bukas #NoClass #NoPasok',
    ]);

    // Student A in Section Alpha has no posts yet -> trendingTopics must be empty
    $responseEmpty = actingAs($this->studentA)->get('/community');
    $responseEmpty->assertSuccessful();
    $responseEmpty->assertInertia(fn ($page) => $page
        ->component('Community')
        ->has('posts', 0)
        ->has('trendingTopics', 0)
    );

    // Student A creates a post in Section Alpha with #Midterms
    CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentA->id,
        'section_id' => $this->sectionA->id,
        'content' => 'Studying for #Midterms right now!',
    ]);

    $responseWithPost = actingAs($this->studentA)->get('/community');
    $responseWithPost->assertSuccessful();
    $responseWithPost->assertInertia(fn ($page) => $page
        ->component('Community')
        ->has('posts', 1)
        ->has('trendingTopics', 1)
        ->where('trendingTopics.0.tag', 'midterms')
        ->where('trendingTopics.0.name', 'Midterms')
        ->where('trendingTopics.0.count', 1)
    );
});

it('persists hidden post state across page refreshes and allows unhiding own post', function () {
    $postA = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentA->id,
        'section_id' => $this->sectionA->id,
        'content' => 'My post with #NoClass #NoPasok',
    ]);

    // Student A hides their own post
    actingAs($this->studentA)
        ->postJson(route('community.posts.hide', $postA))
        ->assertSuccessful()
        ->assertJson([
            'status' => 'hidden',
            'is_hidden' => true,
            'scope' => 'everyone',
        ]);

    expect($postA->fresh()->is_hidden)->toBeTrue();

    // Refreshing /community: post stays hidden from feed & trendingTopics, and appears in hiddenOwnPosts
    $responseAfterRefresh = actingAs($this->studentA)->get('/community');
    $responseAfterRefresh->assertSuccessful();
    $responseAfterRefresh->assertInertia(fn ($page) => $page
        ->component('Community')
        ->has('posts', 0)
        ->has('trendingTopics', 0)
        ->has('hiddenOwnPosts', 1)
        ->where('hiddenOwnPosts.0.id', $postA->id)
    );

    // Student A unhides their post
    actingAs($this->studentA)
        ->postJson(route('community.posts.hide', $postA))
        ->assertSuccessful()
        ->assertJson([
            'status' => 'unhidden',
            'is_hidden' => false,
            'scope' => 'everyone',
        ]);

    expect($postA->fresh()->is_hidden)->toBeFalse();

    // Refreshing /community again: post and trending hashtags are back in the feed
    $responseAfterUnhide = actingAs($this->studentA)->get('/community');
    $responseAfterUnhide->assertSuccessful();
    $responseAfterUnhide->assertInertia(fn ($page) => $page
        ->component('Community')
        ->has('posts', 1)
        ->has('trendingTopics', 2)
        ->has('hiddenOwnPosts', 0)
    );
});

it('refreshes feed posts, stories, and trending topics when switching active section', function () {
    CommunitySettings::save(['feed_scope' => CommunitySettings::SCOPE_SECTION]);

    // Student A is enrolled in both Section Alpha and Section Beta
    $this->studentA->sections()->syncWithoutDetaching([$this->sectionB->id]);

    $postA = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentA->id,
        'section_id' => $this->sectionA->id,
        'content' => 'Alpha lecture notes #AlphaQuiz',
    ]);

    $postB = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentB->id,
        'section_id' => $this->sectionB->id,
        'content' => 'Beta lab discussion #BetaLab',
    ]);

    // Initially on Section Alpha
    actingAs($this->studentA)
        ->withSession(['community_active_section_id' => $this->sectionA->id])
        ->get('/community')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Community')
            ->where('currentSectionId', $this->sectionA->id)
            ->has('posts', 1)
            ->where('posts.0.id', $postA->id)
            ->where('trendingTopics.0.tag', 'alphaquiz')
        );

    // Switch to Section Beta via sidebar section switcher endpoint and follow redirect
    actingAs($this->studentA)
        ->from('/community')
        ->followingRedirects()
        ->post(route('community.section.select'), ['section_id' => $this->sectionB->id])
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Community')
            ->where('currentSectionId', $this->sectionB->id)
            ->where('sectionName', 'Section Beta')
            ->has('posts', 1)
            ->where('posts.0.id', $postB->id)
            ->where('trendingTopics.0.tag', 'betalab')
        );
});

it('publishes an image post to the public disk and rejects failed storage writes without creating an empty post', function () {
    Storage::fake('public');

    $photo = UploadedFile::fake()->image('campus-photo.jpg', 800, 600);

    actingAs($this->studentA)
        ->from('/community')
        ->followingRedirects()
        ->post(route('community.posts.store'), [
            'content' => 'Campus sunset photo',
            'section_id' => $this->sectionA->id,
            'attachments' => [$photo],
        ])
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Community')
            ->has('posts', 1)
            ->where('posts.0.content', 'Campus sunset photo')
            ->where('posts.0.attachment_type', 'image')
            ->has('posts.0.media', 1)
        );

    $post = CommunityPost::latest('id')->first();
    expect($post)->not->toBeNull();
    expect($post->attachment_url)->toBeString()->not->toBeEmpty();
    Storage::disk('public')->assertExists($post->attachment_url);
    expect($post->media()->count())->toBe(1);

    // Simulate S3/R2 putFile returning false (e.g. bucket ACL/region/endpoint failure when throw=false)
    $countBefore = CommunityPost::count();
    Storage::shouldReceive('disk')
        ->with('public')
        ->andReturnSelf();
    Storage::shouldReceive('putFile')
        ->once()
        ->andReturn(false);

    actingAs($this->studentA)
        ->from('/community')
        ->post(route('community.posts.store'), [
            'content' => 'Should not save when S3 fails',
            'attachments' => [UploadedFile::fake()->image('broken-s3.jpg')],
        ])
        ->assertSessionHasErrors(['attachments']);

    expect(CommunityPost::count())->toBe($countBefore);
});

it('allows a specific section to view, switch to, and interact with allowed other sections with unmasked identities', function () {
    CommunitySettings::save(['feed_scope' => CommunitySettings::SCOPE_SECTION]);
    CommunitySettings::setSectionViewable($this->sectionA->id, [$this->sectionB->id]);

    $postB = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->studentB->id,
        'section_id' => $this->sectionB->id,
        'content' => 'Beta project showcase #BetaDemo',
        'is_anonymous' => false,
    ]);

    // Student A (enrolled only in Section Alpha) can see Section Beta's post with unmasked author and section name,
    // and gets Section Beta in availableSections for the section switcher.
    actingAs($this->studentA)
        ->get('/community')
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Community')
            ->has('availableSections', 2)
            ->has('posts', 1)
            ->where('posts.0.id', $postB->id)
            ->where('posts.0.author.is_masked', false)
            ->where('posts.0.author.name', $this->studentB->name)
            ->where('posts.0.author.section_name', 'Section Beta')
        );

    // Student A can switch active section to Section Beta in the section switcher
    actingAs($this->studentA)
        ->from('/community')
        ->followingRedirects()
        ->post(route('community.section.select'), ['section_id' => $this->sectionB->id])
        ->assertSuccessful()
        ->assertInertia(fn ($page) => $page
            ->component('Community')
            ->where('currentSectionId', $this->sectionB->id)
            ->where('sectionName', 'Section Beta')
            ->has('posts', 1)
            ->where('posts.0.id', $postB->id)
        );

    // Student A can like and comment on Section Beta's post even in section-only mode
    actingAs($this->studentA)
        ->postJson(route('community.posts.like', $postB))
        ->assertSuccessful();

    actingAs($this->studentA)
        ->postJson(route('community.posts.comments.store', $postB), [
            'content' => 'Great work Section Beta!',
        ])
        ->assertSuccessful();
});
