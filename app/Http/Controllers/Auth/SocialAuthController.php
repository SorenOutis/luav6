<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Models\Setting;
use App\Models\User;
use App\Support\SocialProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * OAuth sign-in / sign-up / account linking for Google and GitHub.
 *
 * Both drivers ship with laravel/socialite, so there is no manager package and
 * no SocialiteWasCalled listener involved — only config/services.php entries.
 */
class SocialAuthController extends Controller
{
    /**
     * Send the user off to the provider's consent screen.
     */
    public function redirect(Request $request, string $provider): Response|RedirectResponse
    {
        $this->ensureProviderIsEnabled($provider);

        // Linking from the settings page is always allowed; a brand new sign-in
        // has to respect the platform "login enabled" switch.
        if (! $request->user() && ! $this->loginEnabled()) {
            return redirect()->route('login')->withErrors([
                'email' => Setting::get('login_disabled_message', 'Login is currently disabled.'),
            ]);
        }

        return Socialite::driver($provider)
            ->scopes(SocialProviders::scopes($provider))
            ->redirect();
    }

    /**
     * Handle the provider callback: link, log in, or register.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $this->ensureProviderIsEnabled($provider);

        $isLinking = (bool) $request->user();
        $failureRedirect = $isLinking
            ? redirect()->route('connected-accounts.edit')
            : redirect()->route('login');

        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (Throwable $e) {
            Log::warning('Social login callback failed', [
                'provider' => $provider,
                'message' => $e->getMessage(),
            ]);

            return $failureRedirect->withErrors([
                'email' => 'We could not complete the '.SocialProviders::label($provider).' sign-in. Please try again.',
            ]);
        }

        $email = $this->resolveEmail($socialUser);

        if (! $email) {
            return $failureRedirect->withErrors([
                'email' => SocialProviders::label($provider).' did not share an email address with us. Add a public email to your '.SocialProviders::label($provider).' account, or sign in with your password.',
            ]);
        }

        return $isLinking
            ? $this->linkToCurrentUser($request, $provider, $socialUser, $email)
            : $this->loginOrRegister($request, $provider, $socialUser, $email);
    }

    /**
     * Attach the provider identity to the account that is already signed in.
     */
    private function linkToCurrentUser(Request $request, string $provider, SocialiteUser $socialUser, string $email): RedirectResponse
    {
        $user = $request->user();
        $redirect = redirect()->route('connected-accounts.edit');

        $existing = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', (string) $socialUser->getId())
            ->first();

        if ($existing && $existing->user_id !== $user->id) {
            return $redirect->withErrors([
                'provider' => 'That '.SocialProviders::label($provider).' account is already connected to another user.',
            ]);
        }

        $this->rememberSocialAccount($user, $provider, $socialUser, $email);

        return $redirect->with('status', SocialProviders::label($provider).' account connected.');
    }

    /**
     * Log the matching user in, creating the account when it is a first visit.
     */
    private function loginOrRegister(Request $request, string $provider, SocialiteUser $socialUser, string $email): RedirectResponse
    {
        if (! $this->loginEnabled()) {
            return redirect()->route('login')->withErrors([
                'email' => Setting::get('login_disabled_message', 'Login is currently disabled.'),
            ]);
        }

        $providerId = (string) $socialUser->getId();

        $user = SocialAccount::query()
            ->where('provider', $provider)
            ->where('provider_id', $providerId)
            ->first()?->user;

        // Same email = same person: reuse the existing account instead of
        // creating a duplicate, and link the provider to it.
        $user ??= User::query()->whereRaw('lower(email) = ?', [Str::lower($email)])->first();

        if (! $user) {
            if (! $this->registrationEnabled()) {
                return redirect()->route('login')->withErrors([
                    'email' => Setting::get('registration_disabled_message', 'Registration is currently disabled.'),
                ]);
            }

            $user = $this->createUser($socialUser, $email);
        }

        $this->rememberSocialAccount($user, $provider, $socialUser, $email);

        // Google and GitHub only hand over verified addresses.
        if (! $user->email_verified_at) {
            $user->forceFill(['email_verified_at' => now()])->save();
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(config('fortify.home', '/dashboard'));
    }

    /**
     * Create a brand new account from the provider profile.
     *
     * Section assignment is intentionally left empty: a new student picks their
     * class section inside the app.
     */
    private function createUser(SocialiteUser $socialUser, string $email): User
    {
        $name = trim((string) ($socialUser->getName() ?: $socialUser->getNickname() ?: Str::before($email, '@')));

        return DB::transaction(function () use ($socialUser, $email, $name): User {
            $user = new User;

            // The `name` mutator splits this into first/middle/last.
            $user->name = $name;
            $user->email = $email;
            // No password: these accounts sign in through the provider until
            // they set one from the password settings page.
            $user->password = null;
            $user->email_verified_at = now();

            $avatar = $socialUser->getAvatar();

            if (filled($avatar)) {
                // PublicFileUrl passes absolute URLs straight through.
                $user->avatar = $avatar;
            }

            $user->save();

            return $user;
        });
    }

    /**
     * Create or refresh the stored social identity.
     */
    private function rememberSocialAccount(User $user, string $provider, SocialiteUser $socialUser, string $email): SocialAccount
    {
        return SocialAccount::updateOrCreate(
            [
                'provider' => $provider,
                'provider_id' => (string) $socialUser->getId(),
            ],
            [
                'user_id' => $user->id,
                'nickname' => $socialUser->getNickname(),
                'email' => $email,
                'avatar' => $socialUser->getAvatar(),
            ]
        );
    }

    private function resolveEmail(SocialiteUser $socialUser): ?string
    {
        $email = trim((string) $socialUser->getEmail());

        return $email === '' ? null : $email;
    }

    private function loginEnabled(): bool
    {
        return (bool) Setting::get('login_enabled', true);
    }

    private function registrationEnabled(): bool
    {
        return (bool) Setting::get('registration_enabled', true);
    }

    private function ensureProviderIsEnabled(string $provider): void
    {
        abort_unless(SocialProviders::enabled($provider), 404);
    }
}
