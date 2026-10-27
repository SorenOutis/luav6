<?php

namespace App\Http\Controllers;

use App\Models\CommunityPost;
use App\Models\CommunityPostBookmark;
use App\Models\CommunityPostLike;
use App\Models\CommunityPostMedia;
use App\Models\CommunityPostMediaLike;
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
use Illuminate\Validation\ValidationException;

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
        $requestedSectionId = ! empty($validated['section_id'])
            ? (int) $validated['section_id']
            : (int) ($request->session()->get('community_active_section_id')
                ?? Cache::get('community:active_section:'.$viewer->id.':'.($workspaceId ?? 0))
                ?? 0);

        if ($requestedSectionId > 0) {
            $candidate = Section::query()
                ->when($workspaceId, fn ($q) => $q->where('workspace_id', $workspaceId))
                ->find($requestedSectionId);

            if ($candidate) {
                if ($viewer->is_admin || $viewer->sections()->where('sections.id', $candidate->id)->exists()) {
                    $section = $candidate;
                }
            }
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

        if ($section) {
            $request->session()->put('community_active_section_id', (int) $section->id);
            Cache::forever('community:active_section:'.$viewer->id.':'.($workspaceId ?? 0), (int) $section->id);
        }

        $isAnonymous = (bool) ($validated['is_anonymous'] ?? false);
        if (! CommunitySettings::allowsAnonymous()) {
            $isAnonymous = false;
        }

        $musicTrackId = null;
        if (CommunitySettings::musicEnabled() && ! empty($validated['profile_music_track_id'])) {
            $track = ProfileMusicTrack::query()
                ->where('id', $validated['profile_music_track_id'])
                ->where('is_active', true)
                ->availableForWorkspace($workspaceId)
                ->first();
            $musicTrackId = $track?->id;
        }

        $mediaUploadsEnabled = CommunitySettings::mediaUploadsEnabled();
        $fileAttachmentsEnabled = CommunitySettings::fileAttachmentsEnabled();

        $attachmentUrl = null;
        $attachmentType = null;
        $attachmentName = null;
        $attachmentSize = null;
        $mediaToCreate = [];

        if ($request->hasFile('attachments')) {
            if (! $mediaUploadsEnabled) {
                abort(422, 'Media uploads (photos and videos) are currently disabled.');
            }

            $files = $request->file('attachments');
            if (! is_array($files)) {
                $files = [$files];
            }

            foreach ($files as $index => $file) {
                if (! $file || ! $file->isValid()) {
                    throw ValidationException::withMessages([
                        'attachments' => 'The uploaded file did not complete or exceeds the server upload limit. Please try again with a smaller file.',
                    ]);
                }

                $path = Storage::disk('public')->putFile('community-attachments', $file);
                if (! is_string($path) || $path === '') {
                    throw ValidationException::withMessages([
                        'attachments' => 'We could not save the uploaded media to storage. Please check your connection and try again.',
                    ]);
                }

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

                if ($index === 0) {
                    $attachmentUrl = $path;
                    $attachmentType = $mediaType;
                    $attachmentName = $file->getClientOriginalName();
                    $attachmentSize = $file->getSize();
                }
            }
        }

        if ($request->hasFile('attachment') && empty($mediaToCreate)) {
            $file = $request->file('attachment');
            if (! $file || ! $file->isValid()) {
                throw ValidationException::withMessages([
                    'attachment' => 'The uploaded file did not complete or exceeds the server upload limit. Please try again.',
                ]);
            }

            $mime = $file->getClientMimeType() ?: '';
            $ext = strtolower($file->getClientOriginalExtension());

            $isMedia = Str::startsWith($mime, 'image/') || Str::startsWith($mime, 'video/') || in_array($ext, ['mp4', 'mov', 'webm', 'm4v'], true);
            if ($isMedia && ! $mediaUploadsEnabled) {
                abort(422, 'Media uploads (photos and videos) are currently disabled.');
            }
            if (! $isMedia && ! $fileAttachmentsEnabled) {
                abort(422, 'Document and code attachments are currently disabled.');
            }

            $path = Storage::disk('public')->putFile('community-attachments', $file);
            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages([
                    'attachment' => 'We could not save the uploaded file to storage. Please try again.',
                ]);
            }

            $attachmentUrl = $path;
            $attachmentName = $file->getClientOriginalName();
            $attachmentSize = $file->getSize();

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
     * Toggle like status on a specific post media item (photo/video).
     */
    public function toggleMediaLike(Request $request, CommunityPostMedia $media): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $post = $media->post;
        abort_unless($post, 404);

        if (CommunitySettings::isRestrictedToSection() && ! $viewer->is_admin) {
            $viewerSectionIds = $viewer->sections()->pluck('sections.id')->all();
            if ($post->section_id && ! in_array($post->section_id, $viewerSectionIds, true)) {
                abort(403, 'Cross-section interactions are restricted.');
            }
        }

        $isLiked = false;
        DB::transaction(function () use ($viewer, $media, &$isLiked) {
            $existing = CommunityPostMediaLike::where('post_media_id', $media->id)
                ->where('user_id', $viewer->id)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                $existing->delete();
                $isLiked = false;
                $media->decrement('likes_count');
            } else {
                CommunityPostMediaLike::create([
                    'post_media_id' => $media->id,
                    'user_id' => $viewer->id,
                    'created_at' => now(),
                ]);
                $media->increment('likes_count');
                $isLiked = true;
            }
        });

        if ($request->wantsJson()) {
            return response()->json([
                'is_liked' => $isLiked,
                'media_id' => $media->id,
                'likes_count' => (int) $media->fresh()->likes_count,
                'post_id' => $post->id,
                'post_likes_count' => (int) $post->fresh()->likes_count,
                'post_is_liked' => CommunityPostLike::where('post_id', $post->id)->where('user_id', $viewer->id)->exists(),
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

    /**
     * Toggle hidden status on a post.
     * - If the viewer owns the post (or is an admin), toggles `is_hidden` on `community_posts` for everyone.
     * - Otherwise, toggles a per-viewer hide record in `community_post_hides`.
     */
    public function toggleHide(Request $request, CommunityPost $post): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->hasLoungeAccess(), 403);

        $isOwnerOrAdmin = $viewer->id === $post->user_id || $viewer->is_admin;

        if ($isOwnerOrAdmin) {
            $post->update([
                'is_hidden' => ! $post->is_hidden,
            ]);
            $isHidden = (bool) $post->is_hidden;
            $scope = 'everyone';
        } else {
            $existing = DB::table('community_post_hides')
                ->where('post_id', $post->id)
                ->where('user_id', $viewer->id)
                ->exists();

            if ($existing) {
                DB::table('community_post_hides')
                    ->where('post_id', $post->id)
                    ->where('user_id', $viewer->id)
                    ->delete();
                $isHidden = false;
            } else {
                DB::table('community_post_hides')->insert([
                    'post_id' => $post->id,
                    'user_id' => $viewer->id,
                    'created_at' => now(),
                ]);
                $isHidden = true;
            }
            $scope = 'personal';
        }

        $workspaceId = $post->workspace_id;
        if ($workspaceId) {
            Cache::forget("workspace:{$workspaceId}:trending_topics");
        }

        if ($request->wantsJson()) {
            return response()->json([
                'status' => $isHidden ? 'hidden' : 'unhidden',
                'is_hidden' => $isHidden,
                'scope' => $scope,
            ]);
        }

        return back();
    }
}
