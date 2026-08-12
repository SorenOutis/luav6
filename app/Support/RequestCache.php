<?php

namespace App\Support;

use Closure;

/**
 * A per-request memoization store.
 *
 * Several hot values are recomputed many times while rendering a single page —
 * `Season::current()` alone runs 3–5 times per dashboard request (controller,
 * User::activeSeasonProgress(), BadgeAwardService, ClaimXpService,
 * SectionProgress observers). None of them can change mid-request, so the
 * repeat queries are pure waste.
 *
 * ⚠️ Why a container-scoped object and not a `static` property:
 * under Octane a static survives between requests on the same worker, so one
 * user's season/workspace values would leak into the next user's response.
 * This class is registered with `$this->app->scoped()`, which Octane flushes at
 * every request boundary, so the memo lifetime is exactly one request.
 *
 * @see \App\Providers\AppServiceProvider::register()
 */
class RequestCache
{
    /** @var array<string, mixed> */
    protected array $values = [];

    /**
     * Resolve a value once per request.
     *
     * `null` results are cached too (a missing active season is a real answer
     * and re-querying for it on every call is the exact problem being solved),
     * so array_key_exists is used rather than `??=`.
     */
    public function remember(string $key, Closure $callback): mixed
    {
        if (! array_key_exists($key, $this->values)) {
            $this->values[$key] = $callback();
        }

        return $this->values[$key];
    }

    /**
     * Drop one key, or the whole store when no key is given.
     *
     * Call this after mutating something that a memoized value derives from —
     * e.g. activating a different season inside the same request.
     */
    public function forget(?string $key = null): void
    {
        if ($key === null) {
            $this->values = [];

            return;
        }

        unset($this->values[$key]);
    }

    /**
     * Drop every key beginning with the given prefix.
     *
     * Lets one subsystem invalidate only its own entries — clearing the whole
     * store would silently throw away unrelated memoized values and quietly
     * reintroduce the queries this class exists to remove.
     */
    public function forgetPrefix(string $prefix): void
    {
        foreach (array_keys($this->values) as $key) {
            if (str_starts_with((string) $key, $prefix)) {
                unset($this->values[$key]);
            }
        }
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->values);
    }
}
