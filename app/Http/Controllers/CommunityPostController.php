<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\CommunityPostBookmark;
use App\Models\CommunityPostLike;
use App\Models\ProfileMusicTrack;
use App\Models\Section;
use App\Support\CommunitySettings;
use App\Support\WorkspaceContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CommunityPostController extends Controller
{
    /**
     * Store a newly created community lounge post.
     */
    public function store(Request $request): RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $validated = $request->validate([
            'content' => ['nullable', 'string', 'max:2000'],
            'is_anonymous' => ['sometimes', 'boolean'],
            'section_id' => ['nullable', 'integer'],
            'profile_music_track_id' => ['nullable', 'integer'],
            'attachment' => ['nullable', 'file', 'max:51200'],
            'attachments' => ['nullable', 'array', 'max:10'],
            'attachments.*' => ['file', 'max:51200'],
        ]);

        $content = trim($validated['content'] ?? '');
        $hasAttachments = $request->hasFile('attachment') || $request->hasFile('attachments');
        if ($content === '' && ! $hasAttachments) {
            abort(422, 'Post must contain either text or media.');
        }

        $workspaceId = app(WorkspaceContext::class)->id()
            ?? $viewer->current_workspace_id
            ?? $viewer->currentWorkspace?->id
            ?? $viewer->workspaces()->first()?->id;

        $section = null;
        if (! empty($validated['section_id'])) {
            $section = Section::query()
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->find($validated['section_id']);
        }

        if (! $section) {
            $section = $viewer->sections()
                ->when($workspaceId, fn ($q) => $q->where('sections.workspace_id', $workspaceId))
                ->first();
        }

        if (! $section && $viewer->is_admin) {
            $section = Section::query()
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->first();
        }

        if (CommunitySettings::isRestrictedToSection() && ! $section && ! $viewer->is_admin) {
            abort(422, 'You must be enrolled in a section to post in section-only mode.');
        }

        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);
        if (! CommunitySettings::allowsAnonymous()) {
            $isAnonymous = false;
        }

        $musicTrackId = null;
        if (! empty($validated['profile_music_track_id'])) {
            $track = ProfileMusicTrack::query()
                ->where('id', $validated['profile_music_track_id'])
                ->where('is_active', true)
                ->availableForWorkspace($workspaceId)
                ->first();
            $musicTrackId = $track?->id;
        }

        $attachmentUrl = null;
        $attachmentType = null;
        $attachmentName = null;
        $attachmentSize = null;
        $mediaToCreate = [];

        if ($request->hasFile('attachments')) {
            $files = $request->file('attachments');
            if (! is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $index => $file) {
                if (! $file || ! $file->isValid()) {
                    continue;
                }

                $path = Storage::disk('public')->putFile('community-attachments', $file);
                $mime = $file->getClientMimeType() ?: '';
                $ext = strtolower($file->getClientOriginalExtension());

                if (Str::startsWith($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'webm', 'm4v'], true)) {
                    $mediaType = 'video';
                } else {
                    $mediaType = 'image';
                }

                $mediaToCreate[] = [
                    'media_url' => $path,
                    'media_type' => $mediaType,
                    'file_name' => $file->getClientOriginalName(),
                    'file_size' => $file->getSize(),
                    'sort_order' => $index,
                ];

                if ($index === 0 && ! $request->hasFile('attachment')) {
                    $attachmentUrl = $path;
                    $attachmentType = $mediaType;
                    $attachmentName = $file->getClientOriginalName();
                    $attachmentSize = $file->getSize();
                }
            }
        }

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $path = Storage::disk('public')->putFile('community-attachments', $file);
            $attachmentUrl = $path;
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();

            $mime = $file->getClientMimeType() ?: '';
            $ext = strtolower($file->getClientOriginalExtension());

            if (Str::startsWith($mime, 'image/')) {
                $attachmentType = 'image';
            } elseif (Str::startsWith($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'webm', 'm4v'], true)) {
                $attachmentType = 'video';
            } elseif (in_array($ext, ['pdf', 'doc', 'docx', 'txt', 'rtf', 'odt', 'xls', 'xlsx', 'csv', 'ppt', 'pptx'], true)) {
                $attachmentType = 'document';
            } elseif (in_array($ext, ['py', 'js', 'ts', 'jsx', 'tsx', 'html', 'css', 'php', 'c', 'cpp', 'java', 'sql', 'json', 'sh', 'vue'], true)) {
                $attachmentType = 'code';
            } else {
                $attachmentType = 'file';
            }

            if (empty($mediaToCreate) && in_array($attachmentType, ['image', 'video'], true)) {
                $mediaToCreate[] = [
                    'media_url' => $path,
                    'media_type' => $attachmentType,
                    'file_name' => $attachmentName,
                    'file_size' => $attachmentSize,
                    'sort_order' => 0,
                ];
            }
        }

        $post = CommunityPost::create([
            'workspace_id' => $workspaceId,
            'user_id' => $viewer->id,
            'section_id' => $section?->id,
            'profile_music_track_id' => $musicTrackId,
            'content' => $content,
            'attachment_url' => $attachmentUrl,
            'attachment_type' => $attachmentType,
            'attachment_name' => $attachmentName,
            'attachment_size' => $attachmentSize,
            'is_anonymous' => $isAnonymous,
            'likes_count' => 0,
            'comments_count' => 0,
        ]);

        foreach ($mediaToCreate as $mediaItem) {
            $post->media()->create($mediaItem);
        }

        if ($workspaceId) {
            Cache::forget("workspace:{$workspaceId}:trending_topics");
        }

        return back();
    }

    /**
     * Update an owned community lounge post.
     */
    public function update(Request $request, CommunityPost $post): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);
        abort_unless($viewer->id === $post->user_id || $viewer->is_admin, 403);

        $validated = $request->validate([
            'content' => ['required', 'string', 'max:2000'],
            'is_anonymous' => ['sometimes', 'boolean'],
        ]);

        $post->update([
            'content' => $validated['content'],
            'is_anonymous' => (bool) ($validated['is_anonymous'] ?? $post->is_anonymous),
        ]);

        $workspaceId = $post->workspace_id;
        if ($workspaceId) {
            Cache::forget("workspace:{$workspaceId}:trending_topics");
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => 'updated',
                'post' => [
                    'id' => $post->id,
                    'content' => $post->content,
                    'is_anonymous' => $post->is_anonymous,
                ],
            ]);
        }

        return back();
    }

    /**
     * Delete an owned community lounge post.
     */
    public function destroy(Request $request, CommunityPost $post): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);
        abort_unless($viewer->id === $post->user_id || $viewer->is_admin, 403);

        $workspaceId = $post->workspace_id;
        $post->delete();

        if ($workspaceId) {
            Cache::forget("workspace:{$workspaceId}:trending_topics");
        }

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return back();
    }

    /**
     * Toggle like status on a post.
     */
    public function toggleLike(Request $request, CommunityPost $post): JsonResponse|RedirectResponse
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

        $isLiked = false;
        DB::transaction(function () use ($viewer, $post, &$isLiked) {
            $existing = CommunityPostLike::where('post_id', $post->id)
                ->where('user_id', $viewer->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
                $isLiked = false;
            } else {
                CommunityPostLike::create([
                    'post_id' => $post->id,
                    'user_id' => $viewer->id,
                    'created_at' => now(),
                ]);
                $post->increment('likes_count');
                $isLiked = true;
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'is_liked' => $isLiked,
                'likes_count' => (int) $post->fresh()->likes_count,
            ]);
        }

        return back();
    }

    /**
     * Toggle bookmark status on a post.
     */
    public function toggleBookmark(Request $request, CommunityPost $post): JsonResponse|RedirectResponse
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

        $isBookmarked = false;
        DB::transaction(function () use ($viewer, $post, &$isBookmarked) {
            $existing = CommunityPostBookmark::where('post_id', $post->id)
                ->where('user_id', $viewer->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
                $isBookmarked = false;
            } else {
                CommunityPostBookmark::create([
                    'post_id' => $post->id,
                    'user_id' => $viewer->id,
                    'created_at' => now(),
                ]);
                $isBookmarked = true;
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'is_bookmarked' => $isBookmarked,
            ]);
        }

        return back();
    }
}
