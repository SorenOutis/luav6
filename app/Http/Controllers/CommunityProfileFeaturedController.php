<?php

namespace App\Http\Controllers;

use App\Models\CommunityProfileFeatured;
use App\Models\CommunityStory;
use App\Support\PublicFileUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunityProfileFeaturedController extends Controller
{
    /**
     * Store a pinned featured media item on the student's lounge profile (max 6 items).
     */
    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);

        $currentCount = CommunityProfileFeatured::where('user_id', $viewer->id)->count();
        if ($currentCount >= 6) {
            throw ValidationException::withMessages([
                'media' => ['You can only pin up to 6 featured media highlights.'],
            ]);
        }

        $request->validate([
            'media' => ['nullable', 'file', 'max:30720', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm,mov'],
            'story_id' => ['nullable', 'integer', 'exists:community_stories,id'],
            'title' => ['nullable', 'string', 'max:100'],
        ]);

        if (! $request->hasFile('media') && ! $request->input('story_id')) {
            throw ValidationException::withMessages([
                'media' => ['Please upload a file or select a story from your archive.'],
            ]);
        }

        if ($storyId = $request->input('story_id')) {
            $story = CommunityStory::where('id', $storyId)
                ->where('user_id', $viewer->id)
                ->firstOrFail();

            $extension = pathinfo($story->media_url, PATHINFO_EXTENSION);
            $newPath = 'community-featured/'.Str::random(40).($extension ? '.'.$extension : '');
            if (Storage::disk('public')->exists($story->media_url)) {
                Storage::disk('public')->copy($story->media_url, $newPath);
            } else {
                $newPath = $story->media_url;
            }

            $mediaPath = $newPath;
            $mediaType = $story->media_type;
            $title = $request->input('title') ?: $story->caption;
        } else {
            $file = $request->file('media');
            $mime = $file->getClientMimeType() ?: '';
            $isImage = Str::startsWith($mime, 'image/');

            if ($isImage && $file->getSize() > 10 * 1024 * 1024) {
                throw ValidationException::withMessages([
                    'media' => ['Image files cannot exceed 10MB.'],
                ]);
            }

            $mediaPath = Storage::disk('public')->putFile('community-featured', $file);
            $mediaType = $isImage ? 'image' : 'video';
            $title = $request->input('title');
        }

        $item = CommunityProfileFeatured::create([
            'user_id' => $viewer->id,
            'media_url' => $mediaPath,
            'media_type' => $mediaType,
            'title' => $title,
            'sort_order' => $currentCount,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'id' => $item->id,
                'media_url' => PublicFileUrl::resolve($item->media_url),
                'media_type' => $item->media_type,
                'title' => $item->title,
                'sort_order' => $item->sort_order,
                'created_at' => $item->created_at->diffForHumans(short: true),
            ], 201);
        }

        return back();
    }

    /**
     * Delete an owned featured media item.
     */
    public function destroy(Request $request, CommunityProfileFeatured $featured): JsonResponse|RedirectResponse
    {
        $viewer = $request->user();
        abort_unless($viewer, 401);
        abort_unless($viewer->id === $featured->user_id || $viewer->is_admin, 403);

        $featured->delete();

        if ($request->wantsJson()) {
            return response()->json(['status' => 'deleted']);
        }

        return back();
    }
}
