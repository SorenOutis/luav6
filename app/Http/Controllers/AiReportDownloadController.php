<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AiReportDownloadController extends Controller
{
    public function __invoke(Request $request, int $workspace, string $filename): Response
    {
        abort_unless((bool) $request->user()?->is_admin, 403);
        abort_unless((int) $request->query('actor') === (int) $request->user()->id, 403);
        abort_unless((string) $request->query('report') !== '', 404);
        abort_unless($request->user()->workspaces()->whereKey($workspace)->exists(), 403);

        $safeFilename = basename($filename);
        abort_unless($safeFilename === $filename && Str::isMatch('/^[A-Za-z0-9._-]+$/', $safeFilename), 404);

        $path = 'ai-reports/'.$workspace.'/'.basename((string) $request->query('report')).'/'.$safeFilename;
        abort_unless(Storage::disk('local')->exists($path), 404);

        return response()->download(
            Storage::disk('local')->path($path),
            $safeFilename,
            ['Content-Type' => Storage::disk('local')->mimeType($path) ?: 'application/octet-stream'],
        );
    }
}
