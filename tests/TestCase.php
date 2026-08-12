<?php

namespace Tests;

use App\Support\RequestCache;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);

        // Flush the cache to prevent rate limiter state from bleeding between tests
        Cache::flush();

        // RequestCache is a scoped singleton. PHPUnit reuses the same
        // application instance across tests, so memoized Season::current()
        // (and any other request-scoped values) would otherwise leak from
        // one test into the next after RefreshDatabase rolls back.
        if ($this->app->bound(RequestCache::class)) {
            $this->app->make(RequestCache::class)->forget();
        }
    }
}
