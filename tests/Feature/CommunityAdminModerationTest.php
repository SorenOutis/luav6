<?php

use App\Filament\Resources\CommunityFeatured\Pages\ListCommunityFeatured;
use App\Filament\Resources\CommunityPosts\Pages\EditCommunityPost;
use App\Filament\Resources\CommunityPosts\Pages\ListCommunityPosts;
use App\Filament\Resources\CommunityPosts\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\CommunityPosts\RelationManagers\LikesRelationManager;
use App\Filament\Resources\CommunityStories\Pages\ListCommunityStories;
use App\Models\CommunityComment;
use App\Models\CommunityCommentLike;
use App\Models\CommunityPost;
use App\Models\CommunityPostLike;
use App\Models\CommunityProfileFeatured;
use App\Models\CommunityStory;
use App\Models\User;
use App\Models\Workspace;
use Filament\Actions\DeleteAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    $this->workspace = Workspace::factory()->create();
});

it('forbids non-admin students from accessing community moderation resources', function () {
    $student = User::factory()->create();

    actingAs($student)
        ->get('/admin/community-posts')
        ->assertForbidden();

    actingAs($student)
        ->get('/admin/community-stories')
        ->assertForbidden();

    actingAs($student)
        ->get('/admin/community-featured')
        ->assertForbidden();
});

it('allows admin to access community posts, stories, and featured media list pages', function () {
    $admin = User::factory()->superAdmin()->create();

    actingAs($admin)
        ->get('/admin/community-posts')
        ->assertSuccessful()
        ->assertSee('Lounge Posts');

    actingAs($admin)
        ->get('/admin/community-stories')
        ->assertSuccessful()
        ->assertSee('Stories (MyDay)');

    actingAs($admin)
        ->get('/admin/community-featured')
        ->assertSuccessful()
        ->assertSee('Featured Media');
});

it('allows admin to access community post edit page', function () {
    $admin = User::factory()->superAdmin()->create();
    $post = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'content' => 'Sample post for moderation review',
    ]);

    actingAs($admin)
        ->get("/admin/community-posts/{$post->id}/edit")
        ->assertSuccessful();
});

it('cleans up storage attachment when post is deleted', function () {
    Storage::fake('public');

    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $file = UploadedFile::fake()->image('test_attachment.png');
    $path = Storage::disk('public')->putFile('community-attachments', $file);

    $post = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'content' => 'Post with file to delete',
        'attachment_url' => $path,
        'attachment_type' => 'image',
    ]);

    expect(Storage::disk('public')->exists($path))->toBeTrue();

    Livewire::test(ListCommunityPosts::class)
        ->callTableAction(DeleteAction::class, $post);

    expect(CommunityPost::find($post->id))->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

it('cleans up storage media when community story is deleted', function () {
    Storage::fake('public');

    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $file = UploadedFile::fake()->image('story_to_delete.jpg');
    $path = Storage::disk('public')->putFile('community-stories', $file);

    $story = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'media_url' => $path,
        'media_type' => 'image',
        'duration_seconds' => 5,
        'expires_at' => now()->addHours(24),
    ]);

    expect(Storage::disk('public')->exists($path))->toBeTrue();

    Livewire::test(ListCommunityStories::class)
        ->callTableAction(DeleteAction::class, $story);

    expect(CommunityStory::find($story->id))->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

it('cleans up storage media when featured profile highlight is deleted', function () {
    Storage::fake('public');

    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $file = UploadedFile::fake()->image('featured_to_delete.jpg');
    $path = Storage::disk('public')->putFile('community-featured', $file);

    $featured = CommunityProfileFeatured::create([
        'user_id' => $admin->id,
        'media_url' => $path,
        'media_type' => 'image',
        'title' => 'Admin test highlight',
        'sort_order' => 0,
    ]);

    expect(Storage::disk('public')->exists($path))->toBeTrue();

    Livewire::test(ListCommunityFeatured::class)
        ->callTableAction(DeleteAction::class, $featured);

    expect(CommunityProfileFeatured::find($featured->id))->toBeNull()
        ->and(Storage::disk('public')->exists($path))->toBeFalse();
});

it('lets admin remove a post reaction and keeps the like counter in sync', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $post = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'content' => 'Post with reactions',
        'likes_count' => 1,
    ]);

    $reaction = CommunityPostLike::create([
        'post_id' => $post->id,
        'user_id' => $admin->id,
        'created_at' => now(),
    ]);

    Livewire::test(LikesRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditCommunityPost::class,
    ])
        ->assertCanSeeTableRecords([$reaction])
        ->callTableAction(DeleteAction::class, $reaction);

    expect(CommunityPostLike::find($reaction->id))->toBeNull()
        ->and($post->fresh()->likes_count)->toBe(0);
});

it('lets admin remove a comment reaction and keeps the like counter in sync', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $post = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'content' => 'Post with a reacted comment',
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $admin->id,
        'content' => 'Comment with a reaction',
        'likes_count' => 1,
    ]);

    $reaction = CommunityCommentLike::create([
        'comment_id' => $comment->id,
        'user_id' => $admin->id,
        'created_at' => now(),
    ]);

    $reaction->delete();

    expect(CommunityCommentLike::find($reaction->id))->toBeNull()
        ->and($comment->fresh()->likes_count)->toBe(0);
});

it('removes stored files when posts are bulk deleted', function () {
    Storage::fake('public');

    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $paths = [];

    foreach (range(1, 2) as $index) {
        $file = UploadedFile::fake()->image("bulk_{$index}.png");
        $path = Storage::disk('public')->putFile('community-attachments', $file);
        $paths[] = $path;

        CommunityPost::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $admin->id,
            'content' => "Bulk post {$index}",
            'attachment_url' => $path,
            'attachment_type' => 'image',
        ]);
    }

    $posts = CommunityPost::whereIn('content', ['Bulk post 1', 'Bulk post 2'])->get();

    Livewire::test(ListCommunityPosts::class)
        ->callTableBulkAction('delete', $posts);

    expect(CommunityPost::count())->toBe(0);

    foreach ($paths as $path) {
        expect(Storage::disk('public')->exists($path))->toBeFalse();
    }
});

it('scopes lounge posts to the admin workspace', function () {
    $admin = User::factory()->admin()->create();
    $adminWorkspaceId = (int) $admin->current_workspace_id;

    $otherWorkspace = Workspace::factory()->create();

    $ownPost = CommunityPost::create([
        'workspace_id' => $adminWorkspaceId,
        'user_id' => $admin->id,
        'content' => 'Visible in own workspace',
    ]);

    $foreignPost = CommunityPost::create([
        'workspace_id' => $otherWorkspace->id,
        'user_id' => $admin->id,
        'content' => 'Hidden from other workspace',
    ]);

    actingAs($admin);

    Livewire::test(ListCommunityPosts::class)
        ->assertCanSeeTableRecords([$ownPost])
        ->assertCanNotSeeTableRecords([$foreignPost]);
});

it('decrements the post comment counter for a whole deleted thread', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $post = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'content' => 'Post with a thread',
        'comments_count' => 3,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $admin->id,
        'content' => 'Parent comment',
    ]);

    CommunityComment::create([
        'post_id' => $post->id,
        'parent_id' => $comment->id,
        'user_id' => $admin->id,
        'content' => 'First reply',
    ]);

    CommunityComment::create([
        'post_id' => $post->id,
        'parent_id' => $comment->id,
        'user_id' => $admin->id,
        'content' => 'Second reply',
    ]);

    $comment->delete();

    expect($post->fresh()->comments_count)->toBe(0)
        ->and(CommunityComment::where('post_id', $post->id)->count())->toBe(0);
});

it('renders story and featured tables for video media', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'media_url' => 'community-stories/clip.mp4',
        'media_type' => 'video',
        'duration_seconds' => 20,
        'expires_at' => now()->addHours(6),
    ]);

    CommunityProfileFeatured::create([
        'user_id' => $admin->id,
        'media_url' => 'community-featured/clip.mp4',
        'media_type' => 'video',
        'title' => 'Highlight clip',
        'sort_order' => 0,
    ]);

    actingAs($admin)
        ->get('/admin/community-stories')
        ->assertSuccessful()
        ->assertSee('Active');

    actingAs($admin)
        ->get('/admin/community-featured')
        ->assertSuccessful()
        ->assertSee('Highlight clip');
});

it('allows admin to manage comments and replies via relation manager', function () {
    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $post = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'content' => 'Post with comment thread',
        'comments_count' => 2,
    ]);

    $comment = CommunityComment::create([
        'post_id' => $post->id,
        'user_id' => $admin->id,
        'content' => 'Top level comment',
    ]);

    $reply = CommunityComment::create([
        'post_id' => $post->id,
        'parent_id' => $comment->id,
        'user_id' => $admin->id,
        'content' => 'Nested reply',
    ]);

    Livewire::test(CommentsRelationManager::class, [
        'ownerRecord' => $post,
        'pageClass' => EditCommunityPost::class,
    ])
        ->assertCanSeeTableRecords([$comment, $reply])
        ->callTableAction(DeleteAction::class, $reply);

    expect(CommunityComment::find($reply->id))->toBeNull()
        ->and($post->fresh()->comments_count)->toBe(1);
});

it('supports bulk deleting posts, stories, and featured items', function () {
    Storage::fake('public');

    $admin = User::factory()->superAdmin()->create();
    actingAs($admin);

    $post1 = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'content' => 'Post 1',
    ]);
    $post2 = CommunityPost::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'content' => 'Post 2',
    ]);

    Livewire::test(ListCommunityPosts::class)
        ->callTableBulkAction('delete', [$post1, $post2]);

    expect(CommunityPost::find($post1->id))->toBeNull()
        ->and(CommunityPost::find($post2->id))->toBeNull();

    $story1 = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'media_url' => 'story1.jpg',
        'media_type' => 'image',
        'expires_at' => now()->addDay(),
    ]);
    $story2 = CommunityStory::create([
        'workspace_id' => $this->workspace->id,
        'user_id' => $admin->id,
        'media_url' => 'story2.jpg',
        'media_type' => 'image',
        'expires_at' => now()->addDay(),
    ]);

    Livewire::test(ListCommunityStories::class)
        ->callTableBulkAction('delete', [$story1, $story2]);

    expect(CommunityStory::find($story1->id))->toBeNull()
        ->and(CommunityStory::find($story2->id))->toBeNull();
});
