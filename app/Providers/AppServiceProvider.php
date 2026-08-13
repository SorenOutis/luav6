<?php

namespace App\Providers;

use App\Ai\Providers\HeaderAwareOpenAiCompatibleProvider;
use App\Services\AiSdkProviderService;
use App\Support\RequestCache;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;
use Laravel\Ai\AiManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Per-request memo store for values that cannot change mid-request
        // (the active season, etc). `scoped` — not `singleton` — so Octane
        // flushes it between requests and one user's data never leaks into
        // the next response served by the same worker.
        $this->app->scoped(RequestCache::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();

        app(AiManager::class)->extend(
            AiSdkProviderService::HEADER_AWARE_OPENAI_COMPATIBLE_DRIVER,
            fn ($app, array $config): HeaderAwareOpenAiCompatibleProvider => new HeaderAwareOpenAiCompatibleProvider(
                $config,
                $app->make('events'),
            ),
        );

        // Laravel Horizon is installed only inside the Docker image (never in
        // the repo's composer.json), so its dashboard gate is defined with a
        // plain Laravel API — this boots fine whether or not the package is
        // present. Only super admins may inspect queue status.
        Gate::define('viewHorizon', fn ($user = null): bool => (bool) $user?->isSuperAdmin());

        // Register the /broadcasting/auth endpoint. Without this, the
        // frontend's private-channel subscription (ExamAnswersSaved on
        // `exam.{id}.student.{userId}`) can never authenticate — pusher-js
        // posts to /broadcasting/auth, gets a 404, and the realtime "saved"
        // acknowledgement silently never arrives.
        Broadcast::routes();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Named per-route rate limiters for the exam flow.
     *
     * Previously every exam route used the string form `throttle:N,1`.
     * Laravel keys that form by the authenticated user only — the route is
     * NOT part of the key — so autosave, the 5-second progress heartbeat,
     * the 2-second essay-grading poll and the final submit all shared ONE
     * per-user counter. Typing an essay drove that shared counter past the
     * submit route's 10-per-minute allowance, so clicking submit returned
     * 429 "Too many requests. Please try again later." Named limiters hash
     * the limiter name into the key, giving each route its own bucket.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('exams.progress', fn (Request $request) => Limit::perMinute(60)->by($this->rateLimitKey($request)));
        RateLimiter::for('exams.start', fn (Request $request) => Limit::perMinute(60)->by($this->rateLimitKey($request)));
        RateLimiter::for('exams.answers', fn (Request $request) => Limit::perMinute(240)->by($this->rateLimitKey($request)));
        RateLimiter::for('exams.submit', fn (Request $request) => Limit::perMinute(10)->by($this->rateLimitKey($request)));
        RateLimiter::for('exams.status', fn (Request $request) => Limit::perMinute(120)->by($this->rateLimitKey($request)));
    }

    /**
     * Bucket key shared by every exam limiter: the authenticated user,
     * falling back to the IP for unauthenticated requests.
     */
    private function rateLimitKey(Request $request): string
    {
        return (string) ($request->user()?->getAuthIdentifier() ?? $request->ip());
    }
}
