<?php

namespace App\Http\Controllers;

use App\Models\CommunityStory;
use App\Models\CommunityStoryReaction;
use App\Models\CommunityStoryView;
use App\Support\PublicFileUrl;
use App\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunityStoryController extends Controller
{
    /**
     * Store a 24-hour expiring community story.
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $request->validate([
            'media' => ['required', 'file', 'max:30720', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov'],
            'duration_seconds' => ['nullable', 'integer', 'min:1', 'max:35'],
            'caption' => ['nullable', 'string', 'max:255'],
        ]);

        $file = $request->file('media');
        $mime = $file->getClientMimeType() ?: '';
        $isImage = Str::startsWith($mime, 'image/');

        // Enforce 10MB limit for images, 30MB for videos
        if ($isImage && $file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages([
                'media' => ['Image files cannot exceed 10MB.'],
            ]);
        }

        $workspaceId = app(WorkspaceContext::class)->id() ?? $viewer->current_workspace_id;
        $section = $viewer->sections()
            ->when($workspaceId, fn ($q) => $q->where('sections.workspace_id', $workspaceId))
            ->first();

        $path = Storage::disk('public')->putFile('community-stories', $file);
        $duration = (int) ($request->input('duration_seconds') ?: ($isImage ? 5 : 15));

        $story = CommunityStory::create([
            'workspace_id' => $workspaceId,
            'section_id' => $section?->id,
            'user_id' => $viewer->id,
            'media_url' => $path,
            'media_type' => $isImage ? 'image' : 'video',
            'duration_seconds' => min(35, max(1, $duration)),
            'caption' => $request->input('caption'),
            'views_count' => 0,
            'expires_at' => now()->addHours(24),
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $story->id,
                'media_url' => PublicFileUrl::resolve($story->media_url),
                'media_type' => $story->media_type,
                'duration_seconds' => $story->duration_seconds,
                'caption' => $story->caption,
                'views_count' => 0,
                'created_at' => $story->created_at->diffForHumans(short: true),
                'expires_at' => $story->expires_at->toIso8601String(),
            ], 201);
        }

        return back();
    }

    /**
     * Record a unique story view.
     */
    public function view(Request $request, CommunityStory $story): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $view = CommunityStoryView::firstOrCreate(
            [
                'story_id' => $story->id,
                'user_id' => $viewer->id,
            ],
            [
                'created_at' => now(),
            ],
        );

        if ($view->wasRecentlyCreated) {
            $story->increment('views_count');
        }

        return response()->json([
            'status' => 'ok',
            'views_count' => (int) $story->fresh()->views_count,
        ]);
    }

    /**
     * Store or toggle a reaction to a community story.
     */
    public function react(Request $request, CommunityStory $story): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $validated = $request->validate([
            'reaction' => ['nullable', 'string', 'max:32'],
        ]);

        $reactionEmoji = $validated['reaction'] ?? '❤️';

        $existing = CommunityStoryReaction::where('story_id', $story->id)
            ->where('user_id', $viewer->id)
            ->first();

        if ($existing && $existing->reaction === $reactionEmoji) {
            $existing->delete();
            $userReaction = null;
            $isReacted = false;
        } else {
            if ($existing) {
                $existing->update(['reaction' => $reactionEmoji]);
            } else {
                CommunityStoryReaction::create([
                    'story_id' => $story->id,
                    'user_id' => $viewer->id,
                    'reaction' => $reactionEmoji,
                ]);
            }
            $userReaction = $reactionEmoji;
            $isReacted = true;
        }

        $reactionsCount = CommunityStoryReaction::where('story_id', $story->id)->count();

        return response()->json([
            'success' => true,
            'story_id' => $story->id,
            'user_reaction' => $userReaction,
            'is_reacted' => $isReacted,
            'reactions_count' => $reactionsCount,
        ]);
    }

    /**
     * Get the authenticated student's story archive (past and active stories).
     */
    public function archive(Request $request): JsonResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $stories = CommunityStory::where('user_id', $viewer->id)
            ->withCount('views')
            ->withCount('reactions')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (CommunityStory $s) => [
                'id' => $s->id,
                'media_url' => PublicFileUrl::resolve($s->media_url),
                'media_type' => $s->media_type,
                'duration_seconds' => (int) $s->duration_seconds,
                'caption' => $s->caption,
                'views_count' => (int) $s->views_count,
                'reactions_count' => (int) $s->reactions_count,
                'is_expired' => $s->expires_at->isPast(),
                'expires_at' => $s->expires_at->toIso8601String(),
                'created_at' => $s->created_at->diffForHumans(short: true),
                'formatted_date' => $s->created_at->format('M j, Y'),
            ]);

        return response()->json([
            'stories' => $stories,
        ]);
    }

    /**
     * Delete an owned story.
     */
    public function destroy(Request $request, CommunityStory $story): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);
        abort_unless($viewer->id === $story->user_id || $viewer->is_admin, 403);

        $story->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return back();
    }
}
