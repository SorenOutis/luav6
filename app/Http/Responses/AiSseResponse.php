<?php

namespace App\Http\Responses;

use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Render Laravel AI stream events as SSE without returning a generator from
 * the response callback.
 *
 * Laravel's response factory can consume generator callbacks in a traditional
 * PHP request, but Octane passes them through to Symfony unchanged. Symfony
 * invokes the callback without iterating its returned generator, producing an
 * empty SSE body. Writing frames directly works in both runtimes.
 */
class AiSseResponse
{
    /**
     * @param  iterable<int, object>  $events
     */
    public static function from(iterable $events): StreamedResponse
    {
        return response()->stream(function () use ($events): void {
            foreach ($events as $event) {
                echo 'data: '.((string) $event)."\n\n";
                self::flush();
            }

            echo "data: [DONE]\n\n";
            self::flush();
        }, 200, [
            'Cache-Control' => 'no-cache, no-transform',
            'Content-Type' => 'text/event-stream',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    private static function flush(): void
    {
        if (ob_get_level() > 0) {
            ob_flush();
        }

        flush();
    }
}
