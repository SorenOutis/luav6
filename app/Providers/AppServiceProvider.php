<?php

namespace App\Providers;

use App\Ai\Providers\HeaderAwareOpenAiCompatibleProvider;
use App\Services\AiSdkProviderService;
use App\Support\RequestCache;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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
}
