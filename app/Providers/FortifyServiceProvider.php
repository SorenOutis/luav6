<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use App\Http\Responses\LogoutResponse;
use App\Models\Setting;
use App\Support\CommunitySettings;
use App\Support\SocialProviders;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse as LogoutResponseContract;
use Laravel\Fortify\Features;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(LogoutResponseContract::class, LogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureActions();
        $this->configureViews();
        $this->configureRateLimiting();
    }

    /**
     * Configure Fortify actions.
     */
    private function configureActions(): void
    {
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::createUsersUsing(CreateNewUser::class);
    }

    /**
     * Configure Fortify views.
     */
    private function configureViews(): void
    {
        Fortify::loginView(fn (Request $request) => Inertia::render('auth/Login', [
            'canResetPassword' => Features::enabled(Features::resetPasswords()),
            'canRegister' => Features::enabled(Features::registration()) && (bool) Setting::get('registration_enabled', true),
            'loginEnabled' => (bool) Setting::get('login_enabled', true),
            'loginDisabledMessage' => Setting::get('login_disabled_message', 'Login is currently disabled.'),
            'socialProviders' => SocialProviders::forInertia(),
            'status' => $request->session()->get('status'),
            'pwaApp' => $this->rememberPwaApp($request),
        ]));

        Fortify::resetPasswordView(fn (Request $request) => Inertia::render('auth/ResetPassword', [
            'email' => $request->email,
            'token' => $request->route('token'),
        ]));

        Fortify::requestPasswordResetLinkView(fn (Request $request) => Inertia::render('auth/ForgotPassword', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::verifyEmailView(fn (Request $request) => Inertia::render('auth/VerifyEmail', [
            'status' => $request->session()->get('status'),
        ]));

        Fortify::registerView(fn (Request $request) => Inertia::render('auth/Register', [
            'registrationEnabled' => (bool) Setting::get('registration_enabled', true),
            'registrationDisabledMessage' => Setting::get('registration_disabled_message', 'Registration is currently disabled.'),
            'socialProviders' => SocialProviders::forInertia(),
            'pwaApp' => $this->rememberPwaApp($request),
        ]));

        Fortify::twoFactorChallengeView(fn () => Inertia::render('auth/TwoFactorChallenge'));

        Fortify::confirmPasswordView(fn () => Inertia::render('auth/ConfirmPassword'));
    }

    /**
     * Detect a sign-in screen opened from the installed Community Lounge PWA
     * (?app=community) and make sure a successful login, registration, 2FA
     * challenge or social sign-in returns the student to the Lounge.
     *
     * Fortify's login/register/2FA responses and SocialAuthController all
     * honour the session's intended URL, so storing it here covers every flow.
     * An intended URL that already points inside the Lounge (e.g. a classmate
     * profile the student was viewing when their session expired) is kept.
     */
    private function rememberPwaApp(Request $request): ?string
    {
        if (! CommunitySettings::pwaEnabled()) {
            return null;
        }

        if ($request->query('app') !== 'community') {
            return null;
        }

        $loungeUrl = route('community.index');
        $intended = (string) $request->session()->get('url.intended', '');

        if (! str_starts_with($intended, $loungeUrl)) {
            $request->session()->put('url.intended', $loungeUrl);
        }

        return 'community';
    }

    /**
     * Configure rate limiting.
     */
    private function configureRateLimiting(): void
    {
        RateLimiter::for('two-factor', function (Request $request) {
            return Limit::perMinute(10)->by($request->session()->get('login.id'));
        });

        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(10)->by($throttleKey);
        });

        RateLimiter::for('registration', function (Request $request) {
            return Limit::perMinute(10)->by($request->ip());
        });
    }
}
