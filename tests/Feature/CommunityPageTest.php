<?php

use App\Models\CommunityComment;
use App\Models\CommunityInvite;
use App\Models\CommunityPost;
use App\Models\CommunityProfileFeatured;
use App\Models\CommunityStory;
use App\Models\Section;
use App\Models\User;
use App\Models\Workspace;
use App\Support\StudentPageRegistry;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
    $this->student = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'token' => 'initial-token',
        'accepted_at' => now(),
    ]);
});

it('redirects unauthenticated guests to the login page', function () {
    $this->get(route('community.index'))->assertRedirect(route('login'));
});

it('allows authenticated students to visit the community lounge', function () {
    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
            ->has('workspace')
        );
});

it('renders community lounge feed with nested comments and replies', function () {
    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->student->sections()->attach($section);

    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'section_id' => $section->id,
        'user_id' => $this->student->id,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Top level comment',
    ]);

    $reply = CommunityComment::create([
        'post_id' => $post->id,
        'parent_id' => $comment->id,
        'user_id' => $this->student->id,
        'content' => 'Threaded child reply',
    ]);

    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
            ->has('posts.0.comments', 1)
            ->where('posts.0.comments.0.content', 'Top level comment')
            ->has('posts.0.comments.0.replies', 1)
            ->where('posts.0.comments.0.replies.0.content', 'Threaded child reply')
        );
});

it('returns a locked response when community page is disabled in registry', function () {
    StudentPageRegistry::setControls([
        'community' => [
            'mode' => StudentPageRegistry::MODE_DISABLED,
            'message' => 'Community is temporarily paused.',
        ],
    ]);

    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertStatus(423)
        ->assertInertia(fn (Assert $page) => $page
            ->component('StudentPageUnavailable')
            ->where('pageTitle', 'Community Lounge')
            ->where('message', 'Community is temporarily paused.')
        );
});

it('allows admins to access community even when disabled', function () {
    StudentPageRegistry::setControls([
        'community' => [
            'mode' => StudentPageRegistry::MODE_DISABLED,
            'message' => 'Community is temporarily paused.',
        ],
    ]);

    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('Community'));
});

it('returns cached json presence data from presence polling endpoint', function () {
    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->student->sections()->attach($section);

    $this->actingAs($this->student)
        ->get(route('community.presence'))
        ->assertOk()
        ->assertJsonStructure([
            'sectionName',
            'onlineClassmates',
            'onlineCount',
            'totalEnrolledCount',
        ]);
});

it('allows authenticated student to create a community post', function () {
    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->student->sections()->attach($section);

    $this->actingAs($this->student)
        ->post(route('community.posts.store'), [
            'content' => 'Studying for the network design quiz! #NetworkingLab',
            'is_anonymous' => false,
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('community_posts', [
        'user_id' => $this->student->id,
        'workspace_id' => $this->workspace->id,
        'section_id' => $section->id,
        'content' => 'Studying for the network design quiz! #NetworkingLab',
        'is_anonymous' => false,
    ]);
});

it('allows creating a post with file attachment', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->create('study_notes.pdf', 500, 'application/pdf');

    $this->actingAs($this->student)
        ->post(route('community.posts.store'), [
            'content' => 'Here are my notes for today.',
            'attachment' => $file,
        ])
        ->assertRedirect();

    $post = CommunityPost::where('user_id', $this->student->id)->latest()->first();
    expect($post)->not->toBeNull();
    expect($post->attachment_type)->toBe('document');
    expect($post->attachment_name)->toBe('study_notes.pdf');
    Storage::disk('public')->assertExists($post->attachment_url);
});

it('allows toggling likes on a community post', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    // Like
    $this->actingAs($this->student)
        ->post(route('community.posts.like', $post))
        ->assertRedirect();

    $this->assertDatabaseHas('community_post_likes', [
        'post_id' => $post->id,
        'user_id' => $this->student->id,
    ]);
    expect($post->fresh()->likes_count)->toBe(1);

    // Unlike
    $this->actingAs($this->student)
        ->post(route('community.posts.like', $post))
        ->assertRedirect();

    $this->assertDatabaseMissing('community_post_likes', [
        'post_id' => $post->id,
        'user_id' => $this->student->id,
    ]);
    expect($post->fresh()->likes_count)->toBe(0);
});

it('allows toggling bookmarks on a community post', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    // Bookmark
    $this->actingAs($this->student)
        ->post(route('community.posts.bookmark', $post))
        ->assertRedirect();

    $this->assertDatabaseHas('community_post_bookmarks', [
        'post_id' => $post->id,
        'user_id' => $this->student->id,
    ]);

    // Unbookmark
    $this->actingAs($this->student)
        ->post(route('community.posts.bookmark', $post))
        ->assertRedirect();

    $this->assertDatabaseMissing('community_post_bookmarks', [
        'post_id' => $post->id,
        'user_id' => $this->student->id,
    ]);
});

it('allows adding a comment to a community post', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    $this->actingAs($this->student)
        ->post(route('community.posts.comments.store', $post), [
            'content' => 'Thanks for sharing these tips!',
        ])
        ->assertRedirect();

    $this->assertDatabaseHas('community_comments', [
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Thanks for sharing these tips!',
    ]);
    expect($post->fresh()->comments_count)->toBe(1);
});

it('allows adding a threaded reply to a comment with JSON response', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    $parentComment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Parent comment',
    ]);
    $post->increment('comments_count');

    $response = $this->actingAs($this->student)
        ->postJson(route('community.posts.comments.store', $post), [
            'content' => 'This is a threaded reply!',
            'parent_id' => $parentComment->id,
        ])
        ->assertOk()
        ->assertJsonPath('comment.parent_id', $parentComment->id)
        ->assertJsonPath('comment.content', 'This is a threaded reply!')
        ->assertJsonPath('comments_count', 2);

    $this->assertDatabaseHas('community_comments', [
        'post_id' => $post->id,
        'parent_id' => $parentComment->id,
        'user_id' => $this->student->id,
        'content' => 'This is a threaded reply!',
    ]);

    expect($post->fresh()->comments_count)->toBe(2);
});

it('validates that parent comment must belong to the same post', function () {
    $postA = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);
    $postB = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    $commentOnPostA = CommunityComment::create([
        'post_id' => $postA->id,
        'user_id' => $this->student->id,
        'content' => 'Comment on Post A',
    ]);

    $this->actingAs($this->student)
        ->postJson(route('community.posts.comments.store', $postB), [
            'content' => 'Attempt reply to post A comment on post B',
            'parent_id' => $commentOnPostA->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['parent_id']);
});

it('allows toggling likes on a comment or threaded reply', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Insightful post!',
        'likes_count' => 0,
    ]);

    // Like comment via JSON
    $this->actingAs($this->student)
        ->postJson(route('community.comments.like', $comment))
        ->assertOk()
        ->assertJson([
            'is_liked' => true,
            'likes_count' => 1,
        ]);

    $this->assertDatabaseHas('community_comment_likes', [
        'comment_id' => $comment->id,
        'user_id' => $this->student->id,
    ]);
    expect($comment->fresh()->likes_count)->toBe(1);

    // Unlike comment via JSON
    $this->actingAs($this->student)
        ->postJson(route('community.comments.like', $comment))
        ->assertOk()
        ->assertJson([
            'is_liked' => false,
            'likes_count' => 0,
        ]);

    $this->assertDatabaseMissing('community_comment_likes', [
        'comment_id' => $comment->id,
        'user_id' => $this->student->id,
    ]);
    expect($comment->fresh()->likes_count)->toBe(0);
});

it('returns full profile preview for classmate in same section', function () {
    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $classmate = User::factory()->create([
        'name' => 'Sarah Chen',
        'current_workspace_id' => $this->workspace->id,
    ]);
    $this->student->sections()->attach($section);
    $classmate->sections()->attach($section);

    $this->actingAs($this->student)
        ->get(route('community.users.preview', $classmate))
        ->assertOk()
        ->assertJson([
            'is_masked' => false,
            'name' => 'Sarah Chen',
            'can_follow' => true,
        ]);
});

it('returns masked profile preview for student in another section', function () {
    $otherSection = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $otherStudent = User::factory()->create([
        'name' => 'Secret Student',
        'current_workspace_id' => $this->workspace->id,
    ]);
    $otherStudent->sections()->attach($otherSection);

    $this->actingAs($this->student)
        ->get(route('community.users.preview', $otherStudent))
        ->assertOk()
        ->assertJson([
            'is_masked' => true,
            'can_follow' => false,
            'likes_received' => 0,
        ]);
});

it('renders dedicated community lounge profile page for classmate in same section', function () {
    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $classmate = User::factory()->create([
        'name' => 'Alex Rivera',
        'current_workspace_id' => $this->workspace->id,
    ]);
    $this->student->sections()->attach($section);
    $classmate->sections()->attach($section);

    // Create posts with likes
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $classmate->id,
        'section_id' => $section->id,
        'content' => 'Studying computer networks in the lounge!',
        'likes_count' => 5,
        'is_anonymous' => false,
    ]);

    $this->actingAs($this->student)
        ->get(route('community.users.show', $classmate))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CommunityProfile')
            ->where('profile.is_masked', false)
            ->where('profile.name', 'Alex Rivera')
            ->where('profile.likes_received', 5)
            ->where('profile.posts_count', 1)
            ->has('profile.posts', 1)
        );
});

it('shields community profile when student belongs to a different section', function () {
    $otherSection = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $otherStudent = User::factory()->create([
        'name' => 'Hidden Classmate',
        'current_workspace_id' => $this->workspace->id,
    ]);
    $otherStudent->sections()->attach($otherSection);

    $this->actingAs($this->student)
        ->get(route('community.users.show', $otherStudent))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CommunityProfile')
            ->where('profile.is_masked', true)
            ->where('profile.can_follow', false)
            ->has('profile.posts', 0)
        );
});

it('includes saved posts tab when viewing own community lounge profile', function () {
    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->student->sections()->attach($section);

    $bookmarkedPost = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'section_id' => $section->id,
        'content' => 'Resourceful network topology cheat sheet',
    ]);

    $this->student->communityBookmarks()->create([
        'post_id' => $bookmarkedPost->id,
    ]);

    $this->actingAs($this->student)
        ->get(route('community.users.show', $this->student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CommunityProfile')
            ->where('profile.is_self', true)
            ->where('profile.is_masked', false)
            ->has('profile.saved_posts', 1)
        );
});

it('allows authenticated student to upload a 24h photo story (MyDay)', function () {
    Storage::fake('public');

    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->student->sections()->attach($section);

    $file = UploadedFile::fake()->image('my_day.jpg', 600, 800)->size(2048);

    $response = $this->actingAs($this->student)
        ->post(route('community.stories.store'), [
            'media' => $file,
            'caption' => 'Morning study session in the lounge!',
            'duration_seconds' => 5,
        ]);

    $response->assertRedirect();

    $story = CommunityStory::where('user_id', $this->student->id)->latest()->first();
    expect($story)->not->toBeNull();
    expect($story->media_type)->toBe('image');
    expect($story->caption)->toBe('Morning study session in the lounge!');
    expect($story->duration_seconds)->toBe(5);
    expect($story->expires_at->isFuture())->toBeTrue();
    Storage::disk('public')->assertExists($story->media_url);
});

it('allows authenticated student to upload a video story up to 30MB and records duration', function () {
    Storage::fake('public');

    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->student->sections()->attach($section);

    $videoFile = UploadedFile::fake()->create('clip.mp4', 15000, 'video/mp4');

    $response = $this->actingAs($this->student)
        ->postJson(route('community.stories.store'), [
            'media' => $videoFile,
            'caption' => 'Network lab demo',
            'duration_seconds' => 20,
        ]);

    $response->assertCreated()
        ->assertJsonStructure([
            'id',
            'media_url',
            'media_type',
            'duration_seconds',
            'caption',
            'expires_at',
        ])
        ->assertJson([
            'media_type' => 'video',
            'duration_seconds' => 20,
            'caption' => 'Network lab demo',
        ]);

    $story = CommunityStory::find($response->json('id'));
    expect($story->media_type)->toBe('video');
    expect($story->duration_seconds)->toBe(20);
});

it('validates video duration and rejects duration exceeding 35s', function () {
    Storage::fake('public');

    $videoFile = UploadedFile::fake()->create('long_video.mp4', 5000, 'video/mp4');

    $this->actingAs($this->student)
        ->postJson(route('community.stories.store'), [
            'media' => $videoFile,
            'duration_seconds' => 45,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['duration_seconds']);
});

it('only includes non-expired active stories in community feed tray', function () {
    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->student->sections()->attach($section);

    // Active story (expires in 20 hours)
    $activeStory = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'section_id' => $section->id,
        'user_id' => $this->student->id,
        'media_url' => 'community-stories/active.jpg',
        'media_type' => 'image',
        'duration_seconds' => 5,
        'expires_at' => now()->addHours(20),
    ]);

    // Expired story (expired 2 hours ago)
    $expiredStory = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'section_id' => $section->id,
        'user_id' => $this->student->id,
        'media_url' => 'community-stories/expired.jpg',
        'media_type' => 'image',
        'duration_seconds' => 5,
        'expires_at' => now()->subHours(2),
    ]);

    $this->actingAs($this->student)
        ->get(route('community.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Community')
            ->has('stories', 1)
            ->where('stories.0.stories.0.id', $activeStory->id)
            ->has('stories.0.stories', 1)
        );
});

it('records unique views on community story', function () {
    $story = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'media_url' => 'community-stories/story.jpg',
        'media_type' => 'image',
        'duration_seconds' => 5,
        'views_count' => 0,
        'expires_at' => now()->addHours(24),
    ]);

    $viewer = User::factory()->create(['current_workspace_id' => $this->workspace->id]);
    CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $viewer->id,
        'token' => 'viewer-token-1',
        'accepted_at' => now(),
    ]);

    // First view: increments views_count
    $this->actingAs($viewer)
        ->postJson(route('community.stories.view', $story))
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'views_count' => 1,
        ]);

    expect($story->fresh()->views_count)->toBe(1);

    // Duplicate view by same viewer: does not double count
    $this->actingAs($viewer)
        ->postJson(route('community.stories.view', $story))
        ->assertOk()
        ->assertJson([
            'status' => 'ok',
            'views_count' => 1,
        ]);

    expect($story->fresh()->views_count)->toBe(1);
});

it('allows owner to delete their community story', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('delete_me.jpg');
    $path = Storage::disk('public')->putFile('community-stories', $file);

    $story = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'media_url' => $path,
        'media_type' => 'image',
        'duration_seconds' => 5,
        'expires_at' => now()->addHours(24),
    ]);

    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->student)
        ->deleteJson(route('community.stories.destroy', $story))
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    $this->assertDatabaseMissing('community_stories', ['id' => $story->id]);
    Storage::disk('public')->assertMissing($path);
});

it('allows authenticated student to react to a community story and toggles on repeated tap', function () {
    $viewer = User::factory()->create();
    CommunityInvite::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $viewer->id,
        'token' => 'viewer-token-2',
        'accepted_at' => now(),
    ]);
    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $viewer->sections()->attach($section);
    $this->student->sections()->attach($section);

    $story = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'section_id' => $section->id,
        'user_id' => $this->student->id,
        'media_url' => 'community-stories/story.jpg',
        'media_type' => 'image',
        'duration_seconds' => 5,
        'expires_at' => now()->addHours(24),
    ]);

    // First reaction
    $response = $this->actingAs($viewer)
        ->postJson(route('community.stories.react', $story), [
            'reaction' => '🔥',
        ]);

    $response->assertOk()
        ->assertJson([
            'success' => true,
            'story_id' => $story->id,
            'user_reaction' => '🔥',
            'is_reacted' => true,
            'reactions_count' => 1,
        ]);

    $this->assertDatabaseHas('community_story_reactions', [
        'story_id' => $story->id,
        'user_id' => $viewer->id,
        'reaction' => '🔥',
    ]);

    // Second tap on same emoji toggles it off
    $toggleResponse = $this->actingAs($viewer)
        ->postJson(route('community.stories.react', $story), [
            'reaction' => '🔥',
        ]);

    $toggleResponse->assertOk()
        ->assertJson([
            'success' => true,
            'story_id' => $story->id,
            'user_reaction' => null,
            'is_reacted' => false,
            'reactions_count' => 0,
        ]);

    $this->assertDatabaseMissing('community_story_reactions', [
        'story_id' => $story->id,
        'user_id' => $viewer->id,
    ]);
});

it('returns story archive containing both active and expired stories', function () {
    $section = Section::factory()->create(['workspace_id' => $this->workspace->id]);
    $this->student->sections()->attach($section);

    $activeStory = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'section_id' => $section->id,
        'user_id' => $this->student->id,
        'media_url' => 'community-stories/active.jpg',
        'media_type' => 'image',
        'duration_seconds' => 5,
        'expires_at' => now()->addHours(12),
    ]);

    $expiredStory = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'section_id' => $section->id,
        'user_id' => $this->student->id,
        'media_url' => 'community-stories/expired.jpg',
        'media_type' => 'image',
        'duration_seconds' => 5,
        'expires_at' => now()->subHours(5),
    ]);

    $response = $this->actingAs($this->student)
        ->getJson(route('community.stories.archive'));

    $response->assertOk()
        ->assertJsonCount(2, 'stories')
        ->assertJsonFragment(['id' => $activeStory->id, 'is_expired' => false])
        ->assertJsonFragment(['id' => $expiredStory->id, 'is_expired' => true]);
});

it('allows student to pin an active or expired story to their profile featured highlights', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('story_to_pin.jpg');
    $path = Storage::disk('public')->putFile('community-stories', $file);

    $story = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'media_url' => $path,
        'media_type' => 'image',
        'caption' => 'Memorable hackathon moment',
        'duration_seconds' => 5,
        'expires_at' => now()->subHours(2),
    ]);

    $response = $this->actingAs($this->student)
        ->postJson(route('community.profile.featured.store'), [
            'story_id' => $story->id,
            'title' => 'Hackathon Win',
        ]);

    $response->assertCreated();

    $featured = CommunityProfileFeatured::where('user_id', $this->student->id)->first();
    expect($featured)->not->toBeNull();
    expect($featured->title)->toBe('Hackathon Win');
    expect($featured->media_type)->toBe('image');
    Storage::disk('public')->assertExists($featured->media_url);
    Storage::disk('public')->assertExists($path);
});

it('allows student to add and display featured media highlights on lounge profile', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('highlight1.jpg', 800, 600)->size(1500);

    $this->actingAs($this->student)
        ->post(route('community.profile.featured.store'), [
            'media' => $file,
            'title' => 'Campus Lab Setup',
        ])
        ->assertRedirect();

    $featured = CommunityProfileFeatured::where('user_id', $this->student->id)->first();
    expect($featured)->not->toBeNull();
    expect($featured->title)->toBe('Campus Lab Setup');
    expect($featured->media_type)->toBe('image');
    Storage::disk('public')->assertExists($featured->media_url);

    // Verify it is loaded on user profile
    $this->actingAs($this->student)
        ->get(route('community.users.show', $this->student))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('CommunityProfile')
            ->has('profile.featured_media', 1)
            ->where('profile.featured_media.0.title', 'Campus Lab Setup')
        );
});

it('enforces maximum of 6 featured media items per profile', function () {
    Storage::fake('public');

    // Create 6 items
    for ($i = 0; $i < 6; $i++) {
        CommunityProfileFeatured::create([
            'user_id' => $this->student->id,
            'media_url' => "community-featured/item_{$i}.jpg",
            'media_type' => 'image',
            'title' => "Highlight {$i}",
            'sort_order' => $i,
        ]);
    }

    $file = UploadedFile::fake()->image('item_7.jpg');

    $this->actingAs($this->student)
        ->post(route('community.profile.featured.store'), [
            'media' => $file,
            'title' => '7th item attempt',
        ])
        ->assertSessionHasErrors(['media']);

    expect(CommunityProfileFeatured::where('user_id', $this->student->id)->count())->toBe(6);
});

it('allows owner to delete a featured media item', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('remove_feat.jpg');
    $path = Storage::disk('public')->putFile('community-featured', $file);

    $featured = CommunityProfileFeatured::create([
        'user_id' => $this->student->id,
        'media_url' => $path,
        'media_type' => 'image',
        'title' => 'Delete Me',
        'sort_order' => 0,
    ]);

    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->student)
        ->deleteJson(route('community.profile.featured.destroy', $featured))
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    $this->assertDatabaseMissing('community_profile_featured', ['id' => $featured->id]);
    Storage::disk('public')->assertMissing($path);
});

it('allows post owner to update their post with json response', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'content' => 'Original post content',
        'is_anonymous' => false,
    ]);

    $this->actingAs($this->student)
        ->putJson(route('community.posts.update', $post), [
            'content' => 'Updated post content by owner',
            'is_anonymous' => true,
        ])
        ->assertOk()
        ->assertJson([
            'status' => 'updated',
            'post' => [
                'id' => $post->id,
                'content' => 'Updated post content by owner',
                'is_anonymous' => true,
            ],
        ]);

    $this->assertDatabaseHas('community_posts', [
        'id' => $post->id,
        'content' => 'Updated post content by owner',
        'is_anonymous' => true,
    ]);
});

it('allows admin to update another student post', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'content' => 'Student post content',
    ]);

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->putJson(route('community.posts.update', $post), [
            'content' => 'Moderated by admin',
        ])
        ->assertOk()
        ->assertJson([
            'status' => 'updated',
            'post' => [
                'id' => $post->id,
                'content' => 'Moderated by admin',
            ],
        ]);

    $this->assertDatabaseHas('community_posts', [
        'id' => $post->id,
        'content' => 'Moderated by admin',
    ]);
});

it('forbids another student from updating a post they do not own', function () {
    $otherStudent = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'content' => 'Student post',
    ]);

    $this->actingAs($otherStudent)
        ->putJson(route('community.posts.update', $post), [
            'content' => 'Malicious update attempt',
        ])
        ->assertForbidden();

    expect($post->fresh()->content)->toBe('Student post');
});

it('allows post owner to delete their post and cleans up attachment and cascaded comments', function () {
    Storage::fake('public');

    $file = UploadedFile::fake()->image('test_doc.jpg');
    $path = Storage::disk('public')->putFile('community-attachments', $file);

    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'content' => 'Post with attachment and comments to delete',
        'attachment_url' => $path,
        'attachment_type' => 'image',
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Comment on post to be deleted',
    ]);

    Storage::disk('public')->assertExists($path);

    $this->actingAs($this->student)
        ->deleteJson(route('community.posts.destroy', $post))
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    $this->assertDatabaseMissing('community_posts', ['id' => $post->id]);
    $this->assertDatabaseMissing('community_comments', ['id' => $comment->id]);
    Storage::disk('public')->assertMissing($path);
});

it('allows admin to delete another student post', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'content' => 'Post to be removed by admin',
    ]);

    $admin = User::factory()->superAdmin()->create();

    $this->actingAs($admin)
        ->deleteJson(route('community.posts.destroy', $post))
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    $this->assertDatabaseMissing('community_posts', ['id' => $post->id]);
});

it('forbids another student from deleting a post they do not own', function () {
    $otherStudent = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'content' => 'Post to remain intact',
    ]);

    $this->actingAs($otherStudent)
        ->deleteJson(route('community.posts.destroy', $post))
        ->assertForbidden();

    $this->assertDatabaseHas('community_posts', ['id' => $post->id]);
});

it('allows comment owner to update their comment with json response', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Original comment',
    ]);

    $this->actingAs($this->student)
        ->putJson(route('community.comments.update', $comment), [
            'content' => 'Updated comment content',
        ])
        ->assertOk()
        ->assertJson([
            'status' => 'updated',
            'comment' => [
                'id' => $comment->id,
                'content' => 'Updated comment content',
            ],
        ]);

    $this->assertDatabaseHas('community_comments', [
        'id' => $comment->id,
        'content' => 'Updated comment content',
    ]);
});

it('allows admin to update another student comment', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Student comment to moderate',
    ]);

    $admin = User::factory()->admin()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($admin)
        ->putJson(route('community.comments.update', $comment), [
            'content' => 'Moderated comment content',
        ])
        ->assertOk()
        ->assertJson([
            'status' => 'updated',
            'comment' => [
                'id' => $comment->id,
                'content' => 'Moderated comment content',
            ],
        ]);

    $this->assertDatabaseHas('community_comments', [
        'id' => $comment->id,
        'content' => 'Moderated comment content',
    ]);
});

it('forbids another student from updating a comment they do not own', function () {
    $otherStudent = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Protected comment',
    ]);

    $this->actingAs($otherStudent)
        ->putJson(route('community.comments.update', $comment), [
            'content' => 'Hacked content',
        ])
        ->assertForbidden();

    expect($comment->fresh()->content)->toBe('Protected comment');
});

it('allows comment owner to delete top level comment and decrements post comments_count including replies', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'comments_count' => 3,
    ]);

    $topComment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Top comment to delete',
    ]);

    $reply1 = CommunityComment::create([
        'post_id' => $post->id,
        'parent_id' => $topComment->id,
        'user_id' => $this->student->id,
        'content' => 'Child reply 1',
    ]);

    $reply2 = CommunityComment::create([
        'post_id' => $post->id,
        'parent_id' => $topComment->id,
        'user_id' => $this->student->id,
        'content' => 'Child reply 2',
    ]);

    $this->actingAs($this->student)
        ->deleteJson(route('community.comments.destroy', $topComment))
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    $this->assertDatabaseMissing('community_comments', ['id' => $topComment->id]);
    $this->assertDatabaseMissing('community_comments', ['id' => $reply1->id]);
    $this->assertDatabaseMissing('community_comments', ['id' => $reply2->id]);
    expect($post->fresh()->comments_count)->toBe(0);
});

it('allows reply owner to delete threaded reply and decrements post comments_count by 1', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'comments_count' => 2,
    ]);

    $topComment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Parent comment to stay',
    ]);

    $reply = CommunityComment::create([
        'post_id' => $post->id,
        'parent_id' => $topComment->id,
        'user_id' => $this->student->id,
        'content' => 'Child reply to delete',
    ]);

    $this->actingAs($this->student)
        ->deleteJson(route('community.comments.destroy', $reply))
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    $this->assertDatabaseMissing('community_comments', ['id' => $reply->id]);
    $this->assertDatabaseHas('community_comments', ['id' => $topComment->id]);
    expect($post->fresh()->comments_count)->toBe(1);
});

it('allows admin to delete any comment or reply', function () {
    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
        'comments_count' => 1,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Inappropriate comment',
    ]);

    $admin = User::factory()->admin()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    $this->actingAs($admin)
        ->deleteJson(route('community.comments.destroy', $comment))
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    $this->assertDatabaseMissing('community_comments', ['id' => $comment->id]);
    expect($post->fresh()->comments_count)->toBe(0);
});

it('forbids another student from deleting a comment they do not own', function () {
    $otherStudent = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $this->student->id,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $this->student->id,
        'content' => 'Student comment',
    ]);

    $this->actingAs($otherStudent)
        ->deleteJson(route('community.comments.destroy', $comment))
        ->assertForbidden();

    $this->assertDatabaseHas('community_comments', ['id' => $comment->id]);
});

it('allows post owner to delete comments and replies posted by other users on their post', function () {
    $postOwner = $this->student;
    $commenter = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);
    $replier = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);
    $unrelatedStudent = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $postOwner->id,
        'comments_count' => 2,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $commenter->id,
        'content' => 'Top level comment by commenter',
    ]);

    $reply = CommunityComment::create([
        'post_id' => $post->id,
        'parent_id' => $comment->id,
        'user_id' => $replier->id,
        'content' => 'Reply by replier',
    ]);

    // Unrelated student cannot delete reply
    $this->actingAs($unrelatedStudent)
        ->deleteJson(route('community.comments.destroy', $reply))
        ->assertForbidden();

    // Post owner CAN delete the reply
    $this->actingAs($postOwner)
        ->deleteJson(route('community.comments.destroy', $reply))
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    $this->assertDatabaseMissing('community_comments', ['id' => $reply->id]);
    expect($post->fresh()->comments_count)->toBe(1);

    // Unrelated student cannot delete top comment
    $this->actingAs($unrelatedStudent)
        ->deleteJson(route('community.comments.destroy', $comment))
        ->assertForbidden();

    // Post owner CAN delete the top comment
    $this->actingAs($postOwner)
        ->deleteJson(route('community.comments.destroy', $comment))
        ->assertOk()
        ->assertJson(['status' => 'deleted']);

    $this->assertDatabaseMissing('community_comments', ['id' => $comment->id]);
    expect($post->fresh()->comments_count)->toBe(0);
});

it('forbids post owner from updating other users comments on their post', function () {
    $postOwner = $this->student;
    $commenter = User::factory()->create([
        'current_workspace_id' => $this->workspace->id,
    ]);

    $post = CommunityPost::factory()->create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $postOwner->id,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $commenter->id,
        'content' => 'Original text',
    ]);

    // Post owner cannot edit someone else's comment text
    $this->actingAs($postOwner)
        ->putJson(route('community.comments.update', $comment), [
            'content' => 'Tampered text by post owner',
        ])
        ->assertForbidden();

    $this->assertDatabaseHas('community_comments', [
        'id' => $comment->id,
        'content' => 'Original text',
    ]);
});
