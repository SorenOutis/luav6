<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class FaviconController extends Controller
{
    /**
     * Serve the uploaded school logo as a square favicon.
     *
     * The logo is stored under the `school_logo_path` setting (managed in
     * AiSettings -> School Branding), so the favicon follows whatever logo the
     * admin uploads — including per-workspace logos — without any build step.
     *
     * - No logo uploaded: redirect to the bundled static icon (favicon.ico for
     *   small sizes, apple-touch-icon.png for the 180px touch icon).
     * - GD available: re-encode the logo as a transparent square PNG so
     *   horizontal logos render centered instead of squashed.
     * - GD unavailable: serve the original file with its own content type.
     */
    public function __invoke(Request $request): Response
    {
        $size = min(max((int) $request->query('size', 64), 16), 512);

        $fallback = $size >= 180 ? '/apple-touch-icon.png' : '/favicon.ico';

        $path = Setting::get('school_logo_path');

        if (! is_string($path) || $path === '') {
            return redirect($fallback);
        }

        // Storage::get() returns null (or throws when throwsExceptions() is
        // enabled) when the stored file has gone missing.
        try {
            $bytes = Storage::disk('public')->get($path);
        } catch (\Throwable) {
            return redirect($fallback);
        }

        if ($bytes === null || $bytes === '') {
            return redirect($fallback);
        }

        if (function_exists('imagecreatefromstring') && ! $this->isSvg($path)) {
            $source = @imagecreatefromstring($bytes);

            if ($source !== false) {
                $png = $this->squarePng($source, $size);
                imagedestroy($source);

                return $this->imageResponse($png, 'image/png');
            }
        }

        return $this->imageResponse($bytes, $this->mimeType($path));
    }

    /**
     * Re-encode the source image onto a transparent square canvas, scaling it
     * to fit (contain) and centering it.
     */
    private function squarePng(\GdImage $source, int $size): string
    {
        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);

        $scale = min($size / $sourceWidth, $size / $sourceHeight);
        $width = max(1, (int) round($sourceWidth * $scale));
        $height = max(1, (int) round($sourceHeight * $scale));
        $offsetX = (int) floor(($size - $width) / 2);
        $offsetY = (int) floor(($size - $height) / 2);

        $canvas = imagecreatetruecolor($size, $size);
        imagesavealpha($canvas, true);
        $transparent = imagecolorallocatealpha($canvas, 0, 0, 0, 127);
        imagefill($canvas, 0, 0, $transparent);

        imagecopyresampled($canvas, $source, $offsetX, $offsetY, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        return $png;
    }

    private function imageResponse(string $bytes, string $contentType): Response
    {
        return response($bytes, 200, [
            'Content-Type' => $contentType,
            'Cache-Control' => 'public, max-age=3600',
            'ETag' => '"'.md5($bytes).'"',
        ]);
    }

    private function isSvg(string $path): bool
    {
        return str_ends_with(strtolower($path), '.svg');
    }

    private function mimeType(string $path): string
    {
        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'webp' => 'image/webp',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            default => 'application/octet-stream',
        };
    }
}
