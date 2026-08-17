<?php

namespace App\Providers;

use App\Ai\Providers\HeaderAwareOpenAiCompatibleProvider;
use App\Models\User;
use App\Policies\UserPolicy;
use App\Services\AiSdkProviderService;
use App\Support\GamificationSyncContext;
use App\Support\RequestCache;
use App\Support\WorkspaceContext;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Queue;
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

        // Observer suppression must never be process-static under Octane.
        // Scoped lifetime gives each HTTP request / queue job fresh counters.
        $this->app->scoped(GamificationSyncContext::class);
        $this->app->scoped(WorkspaceContext::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureRateLimiting();
        $this->configureQueueWorkspacePropagation();

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
        Gate::policy(User::class, UserPolicy::class);
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

    /** Carry the active tenant through every queued job payload. */
    protected function configureQueueWorkspacePropagation(): void
    {
        Queue::createPayloadUsing(fn (): array => [
            'workspace_id' => app(WorkspaceContext::class)->id(),
        ]);

        Queue::before(function (JobProcessing $event): void {
            app(WorkspaceContext::class)->set(
                $event->job->payload()['workspace_id'] ?? null,
            );
        });

        $clear = fn (): mixed => app(WorkspaceContext::class)->clear();
        Queue::after(fn (JobProcessed $event) => $clear());
        Queue::exceptionOccurred(fn (JobExceptionOccurred $event) => $clear());
    }

    /**
     * Named rate limiters that give independent buckets to each
     * request-heavy feature.
     *
     * Previously these routes used the string form `throttle:N,1`, which
     * Laravel keys by the authenticated user only — the route is NOT part
     * of the key — so every throttled route shared ONE per-user counter.
     * That caused two real bugs:
     *
     *  1. Exams: autosave, the 5-second monitor-progress heartbeat, the
     *     2-second essay-grading poll and the final submit all shared one
     *     bucket. Typing an essay drove that counter past the submit
     *     route's 10-per-minute allowance, so submitting returned 429
     *     "Too many requests. Please try again later."
     *
     *  2. Chat + daily XP: the floating chat widget's traffic (and the
     *     persisted-chats endpoints) shared the same bucket as the daily
     *     XP claim, so a chatty session made the 10-per-minute claim
     *     intermittently return 429.
     *
     * Named limiters hash the limiter name into the key, so each group
     * below gets its own bucket. Routes within a group still share one
     * combined allowance (e.g. all chat endpoints together are capped at
     * 60/minute), preserving the original per-user limits.
     */
    protected function configureRateLimiting(): void
    {
        RateLimiter::for('exams.progress', fn (Request $request) => Limit::perMinute(60)->by($this->rateLimitKey($request)));
        RateLimiter::for('exams.start', fn (Request $request) => Limit::perMinute(60)->by($this->rateLimitKey($request)));
        RateLimiter::for('exams.answers', fn (Request $request) => Limit::perMinute(240)->by($this->rateLimitKey($request)));
        RateLimiter::for('exams.submit', fn (Request $request) => Limit::perMinute(10)->by($this->rateLimitKey($request)));
        RateLimiter::for('exams.status', fn (Request $request) => Limit::perMinute(120)->by($this->rateLimitKey($request)));

        RateLimiter::for('chat', fn (Request $request) => Limit::perMinute(60)->by($this->rateLimitKey($request)));
        RateLimiter::for('chats', fn (Request $request) => Limit::perMinute(60)->by($this->rateLimitKey($request)));
        RateLimiter::for('ai-actions', fn (Request $request) => Limit::perMinute(30)->by($this->rateLimitKey($request)));
        RateLimiter::for('csp-reports', fn (Request $request) => Limit::perMinute(60)->by((string) $request->ip()));
        RateLimiter::for('claim-xp', fn (Request $request) => Limit::perMinute(10)->by($this->rateLimitKey($request)));
        RateLimiter::for('claim-bonus-xp', fn (Request $request) => Limit::perMinute(10)->by($this->rateLimitKey($request)));
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
