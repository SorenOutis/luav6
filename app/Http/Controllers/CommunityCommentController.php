<?php

namespace App\Http\Controllers;

use App\Models\CommunityComment;
use App\Models\CommunityCommentLike;
use App\Models\CommunityPost;
use App\Models\CommunityPostMedia;
use App\Support\CommunitySettings;
use App\Support\PublicFileUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class CommunityCommentController extends Controller
{
    /**
     * Store a comment on a community post.
     */
    public function store(Request $request, CommunityPost $post): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        if (CommunitySettings::isRestrictedToSection() && ! $viewer->is_admin) {
            $viewerSectionIds = $viewer->sections()->pluck('sections.id')->all();
            if ($post->section_id && ! in_array($post->section_id, $viewerSectionIds, true)) {
                abort(403, 'Cross-section interactions are restricted.');
            }
        }

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
            'post_media_id' => [
                'nullable',
                'integer',
                Rule::exists('community_post_media', 'id')->where('post_id', $post->id),
            ],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('community_comments', 'id')->where('post_id', $post->id),
            ],
        ]);

        $comment = DB::transaction(function () use ($viewer, $post, $validated) {
            $created = CommunityComment::create([
                'post_id' => $post->id,
                'post_media_id' => $validated['post_media_id'] ?? null,
                'parent_id' => $validated['parent_id'] ?? null,
                'user_id' => $viewer->id,
                'content' => $validated['content'],
            ]);

            $post->increment('comments_count');

            if (! empty($validated['post_media_id'])) {
                $media = CommunityPostMedia::find($validated['post_media_id']);
                $media?->increment('comments_count');
            }

            return $created;
        });

        if ($request->wantsJson()) {
            return response()->json([
                'comment' => [
                    'id' => $comment->id,
                    'post_id' => $comment->post_id,
                    'post_media_id' => $comment->post_media_id,
                    'parent_id' => $comment->parent_id,
                    'content' => $comment->content,
                    'created_at' => 'just now',
                    'author_id' => (string) $viewer->id,
                    'author_name' => $viewer->name,
                    'author_handle' => strtolower(preg_replace('/[^a-zA-Z0-9]/', '.', $viewer->name)),
                    'author_avatar' => $viewer->avatar ? PublicFileUrl::resolve($viewer->avatar) : null,
                    'author_public_id' => (string) $viewer->public_id,
                    'is_masked' => false,
                    'likes_count' => 0,
                    'is_liked' => false,
                    'replies' => [],
                ],
                'comments_count' => (int) $post->fresh()->comments_count,
            ]);
        }

        return back();
    }

    /**
     * Update an owned comment or threaded reply.
     */
    public function update(Request $request, CommunityComment $comment): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);
        abort_unless($viewer->id === $comment->user_id || $viewer->is_admin, 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:1000'],
        ]);

        $comment->update([
            'content' => $validated['content'],
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'updated',
                'comment' => [
                    'id' => $comment->id,
                    'content' => $comment->content,
                ],
            ]);
        }

        return back();
    }

    /**
     * Delete an owned comment or threaded reply, or any comment/reply on an owned post.
     */
    public function destroy(Request $request, CommunityComment $comment): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $post = $comment->post ?: CommunityPost::withoutGlobalScopes()->find($comment->post_id);
        $isPostOwner = $post && (int) $post->user_id === (int) $viewer->id;
        abort_unless($viewer->id === $comment->user_id || $isPostOwner || $viewer->is_admin, 403);

        $comment->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return back();
    }

    /**
     * Toggle like status on a comment or reply.
     */
    public function toggleLike(Request $request, CommunityComment $comment): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $isLiked = false;
        DB::transaction(function () use ($viewer, $comment, &$isLiked) {
            $existing = CommunityCommentLike::where('comment_id', $comment->id)
                ->where('user_id', $viewer->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
                $isLiked = false;
            } else {
                CommunityCommentLike::create([
                    'comment_id' => $comment->id,
                    'user_id' => $viewer->id,
                    'created_at' => now(),
                ]);
                $comment->increment('likes_count');
                $isLiked = true;
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'is_liked' => $isLiked,
                'likes_count' => (int) $comment->fresh()->likes_count,
            ]);
        }

        return back();
    }
}
