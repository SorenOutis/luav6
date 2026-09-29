<?php

namespace App\Http\Controllers;

use App\Models\ProfileMusicTrack;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ProfileMusicStreamController extends Controller
{
    /**
     * Stream profile music audio with CORS and byte-range support.
     */
    public function stream(Request $request, ProfileMusicTrack $track): Response
    {
        abort_unless($track->is_active, 404);

        $disk = Storage::disk('public');
        abort_unless($track->audio_path && $disk->exists($track->audio_path), 404);

        $headers = [
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, HEAD, OPTIONS',
            'Access-Control-Allow-Headers' => 'Range, Accept, Content-Type',
            'Accept-Ranges' => 'bytes',
            'Cache-Control' => 'public, max-age=604800, immutable',
        ];

        return $disk->response($track->audio_path, null, $headers);
    }
}
