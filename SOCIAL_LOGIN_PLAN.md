# Social Login (Google + GitHub) — Study & Setup Guide

> Status: **implemented** (see "What was built" below).
> Source: https://socialiteproviders.com/usage/ + Laravel Socialite docs.
>
> ⚠️ This sandbox has no PHP or Composer and cannot reach packagist, so
> `composer.json` **and `composer.lock`** were updated by hand (lock entries
> built from the GitHub tags, `content-hash` recomputed with Composer's own
> algorithm). `composer install` therefore works in CI and in Docker.
> Locked: `laravel/socialite v5.30.0` + its deps `firebase/php-jwt v7.1.0`,
> `league/oauth1-client v1.11.0`, `phpseclib/phpseclib 3.0.56`,
> `paragonie/random_compat v9.99.100`.
> Re-running `composer update laravel/socialite` locally is still recommended.

---

## 0. Key finding first: you probably don't need Socialite Providers

The `socialiteproviders/*` packages exist for providers that are **not** shipped with
Laravel Socialite (Zoho, Discord, Steam, Keycloak, ...).

**Google and GitHub are both first-party drivers built into `laravel/socialite`.**

So for your two providers the whole 5-step flow from socialiteproviders.com collapses to:

| socialiteproviders.com step | Needed for Google/GitHub? |
| --- | --- |
| 1. `composer require socialiteproviders/x` | ❌ — just `composer require laravel/socialite` |
| 2. Swap `SocialiteServiceProvider` for `SocialiteProviders\Manager\ServiceProvider` | ❌ — keep the default auto-discovered provider |
| 3. `Event::listen(SocialiteWasCalled ...)` in `AppServiceProvider` | ❌ — only for custom/extended drivers |
| 4. Add config to `config/services.php` | ✅ — same as documented |
| 5. `Socialite::driver('google')->redirect()` | ✅ — identical usage |

The docs are still worth following for steps 4 & 5, and the extra sections
(`stateless()`, `setConfig()` overrides, `$user->accessTokenResponseBody`) apply
to the built-in drivers too.

If you later want, say, Discord or Microsoft, *then* you add
`SocialiteProviders\Manager\ServiceProvider::class` to `bootstrap/providers.php`
and register the listener — that's the only time steps 2 & 3 matter.

---

## 1. What your codebase looks like today (facts, not guesses)

- Laravel 12 + Inertia + Vue 3 starter kit, **auth handled by Laravel Fortify**
  (`app/Providers/FortifyServiceProvider.php`), not Breeze.
- Login page: `resources/js/pages/auth/Login.vue`, rendered via
  `Fortify::loginView(...)` with props
  `canResetPassword`, `canRegister`, `loginEnabled`, `loginDisabledMessage`, `status`.
- Register page: `resources/js/pages/auth/Register.vue`, user creation goes through
  `app/Actions/Fortify/CreateNewUser.php`.
- `routes/web.php` has no auth routes (Fortify registers them); there is
  `routes/settings.php`, `routes/channels.php`, `routes/console.php`.
- `config/services.php` currently only has postmark / resend / ses / slack / openrouter —
  **no `google` or `github` block yet.**
- `users` table: `password` is **NOT NULL**, `email` unique, plus app-specific columns:
  `first_name`, `middle_name`, `last_name`, `avatar`, `section_id`, `is_admin`,
  `is_super_admin`, `is_banned`, `public_id`, streak/exp/level, etc.
- `CreateNewUser` enforces `Setting::get('registration_enabled')`, requires
  `terms` accepted, and requires first/last name.
- Users can be **banned** (`is_banned`) and there is a `login_enabled` platform setting —
  social login must respect both, otherwise it becomes a bypass door.
- `laravel/socialite` is **not** in `composer.json` yet.
- Frontend uses Wayfinder (`@/routes/...` generated helpers) — new routes will need
  `php artisan wayfinder:generate` (or a `npm run build`/dev restart) to be importable.

---

## 2. What was built

Decisions locked in with you:

- **Same email = same account.** A Google/GitHub sign-in whose email already exists
  logs into that account and links the provider — never a duplicate user.
- **Sign-up allowed.** Unknown email + registration enabled ⇒ new account.
- **No onboarding screen.** Name comes from the provider, email is marked verified,
  and `section_id` stays **null** — a new student picks their class section in the app.
- **Full scope**: login page, register page, and a Settings → Connected accounts page.

### Files

| File | Purpose |
| --- | --- |
| `composer.json` | added `laravel/socialite` |
| `config/services.php` | `google` + `github` credential blocks |
| `database/migrations/..._create_social_accounts_table.php` | one row per linked identity, unique on (`provider`,`provider_id`) and (`user_id`,`provider`) |
| `database/migrations/..._make_password_nullable_on_users_table.php` | social-only accounts have no password |
| `app/Models/SocialAccount.php` | the linked identity |
| `app/Models/User.php` | `socialAccounts()` relation + `hasPassword()` |
| `app/Support/SocialProviders.php` | supported providers, scopes, labels, "is it configured" |
| `app/Http/Controllers/Auth/SocialAuthController.php` | redirect / callback: link, log in, or register |
| `app/Http/Controllers/Settings/ConnectedAccountsController.php` | list + disconnect |
| `app/Http/Controllers/Settings/PasswordController.php`, `PasswordUpdateRequest` | let a social-only user set a first password (no `current_password`) |
| `routes/web.php` | `/auth/{provider}/redirect` and `/auth/{provider}/callback` (throttled 10/min) |
| `routes/settings.php` | `settings/connected-accounts` (+ DELETE) |
| `app/Providers/FortifyServiceProvider.php` | passes `socialProviders` to the login & register pages |
| `resources/js/components/SocialAuthButtons.vue` | divider + provider buttons (plain `<a>`, real navigation) |
| `resources/js/pages/auth/Login.vue`, `Register.vue` | render the buttons |
| `resources/js/pages/settings/ConnectedAccounts.vue` + settings nav | connect / disconnect UI |
| `tests/Feature/Auth/SocialLoginTest.php`, `tests/Feature/Settings/ConnectedAccountsTest.php` | mocked-Socialite coverage |

### Guardrails

- Buttons only render when both the client id and secret are set; the routes 404 otherwise.
- `login_enabled` and `registration_enabled` platform settings are honoured
  (an existing user can still sign in while registration is off).
- A provider already linked to another user cannot be stolen.
- You cannot disconnect your **last** sign-in method — set a password first.
- Providers that return no email are refused with a clear message.
- Callback failures (cancelled consent, invalid state) are logged and redirect back with an error.

### Verification status — CI green ✅

All three GitHub Actions checks pass on PR #86:

| Check | Result |
| --- | --- |
| `quality` (composer install → pint → prettier → eslint) | ✅ pass |
| `ci (8.4)` — `composer ci:check`: eslint, prettier, vue-tsc, vitest, pest | ✅ pass |
| `ci (8.5)` — same matrix on PHP 8.5 | ✅ pass |

Pest: 510 passed, 1 skipped (pre-existing), 2225 assertions.

### After pulling this

```bash
composer update laravel/socialite   # installs the package + updates the lock file
php artisan migrate
npm run build                       # regenerates wayfinder routes
```

---

## 2b. Original implementation plan (reference)

**a. Package**
```bash
composer require laravel/socialite
```

**b. Migration** — `add_social_login_columns_to_users_table`
```php
$table->string('provider')->nullable()->index();      // 'google' | 'github'
$table->string('provider_id')->nullable();
$table->string('provider_avatar')->nullable();        // optional
$table->unique(['provider', 'provider_id']);
$table->string('password')->nullable()->change();     // SQLite-safe on Laravel 12
```
(Add `provider`, `provider_id` to `User::$fillable` / hidden as appropriate.)

**c. Config** — `config/services.php`
```php
'google' => [
    'client_id'     => env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GOOGLE_CLIENT_SECRET'),
    'redirect'      => env('GOOGLE_REDIRECT_URI', '/auth/google/callback'),
],

'github' => [
    'client_id'     => env('GITHUB_CLIENT_ID'),
    'client_secret' => env('GITHUB_CLIENT_SECRET'),
    'redirect'      => env('GITHUB_REDIRECT_URI', '/auth/github/callback'),
],
```

**d. Routes** — `routes/web.php` (guest middleware)
```php
Route::middleware('guest')->group(function () {
    Route::get('/auth/{provider}/redirect', [SocialAuthController::class, 'redirect'])
        ->whereIn('provider', ['google', 'github'])
        ->name('social.redirect');

    Route::get('/auth/{provider}/callback', [SocialAuthController::class, 'callback'])
        ->whereIn('provider', ['google', 'github'])
        ->name('social.callback');
});
```

**e. Controller** — `app/Http/Controllers/Auth/SocialAuthController.php`
- `redirect()`: block if `login_enabled` is false → back with error; else
  `Socialite::driver($provider)->redirect()`.
  (GitHub: `->scopes(['user:email'])`; Google: `->scopes(['openid','profile','email'])`.)
- `callback()`:
  1. `Socialite::driver($provider)->user()` inside try/catch → on failure redirect to
     login with a friendly error (user cancelled, invalid state, etc.).
  2. Reject if the provider gives no email (GitHub can hide it → that's why `user:email`).
  3. Match by `provider` + `provider_id` → else match by `email` (link account) →
     else create new user (only if `registration_enabled`).
  4. Refuse login for `is_banned` users, same as the normal login path.
  5. Name split into `first_name`/`last_name`, `email_verified_at = now()` (both
     providers verify email), random password if the column stays NOT NULL.
  6. `session()->regenerate()`, `Auth::login($user, remember: true)`,
     redirect to the same place normal login goes.

**f. UI** — `resources/js/pages/auth/Login.vue` (and optionally `Register.vue`)
- "or continue with" divider + two buttons (Google & GitHub SVG marks),
  plain `<a href>` full page loads — **must not** be Inertia visits, OAuth needs a
  real browser redirect.
- Pass a `socialLogin: ['google','github']` prop from `FortifyServiceProvider` so the
  buttons only render when creds are configured.
- Respect `loginEnabled` (same disabled modal behaviour as the password form).

**g. Tests** — Pest feature tests with a mocked Socialite driver: new user,
existing-email linking, banned user, registration disabled, provider error.

---

## 3. How to get the credentials — step by step

### Google (OAuth 2.0 Client ID)

1. Go to <https://console.cloud.google.com/> and sign in.
2. Top bar → project dropdown → **New Project** (e.g. `luav6`) → Create → select it.
3. Left menu → **APIs & Services → OAuth consent screen** (newer console: *Google Auth Platform → Branding*).
   - User type: **External** → Create.
   - App name, user support email, developer contact email → Save & Continue.
   - **Scopes**: Add `.../auth/userinfo.email`, `.../auth/userinfo.profile`, `openid` → Save.
   - **Test users**: while the app is in *Testing* mode only these emails can log in.
     Add your own Gmail(s). (Publish the app later for public access.)
4. Left menu → **Credentials → + Create Credentials → OAuth client ID**.
   - Application type: **Web application**.
   - Name: `luav6 web`.
   - **Authorised JavaScript origins**:
     `http://localhost:8000` (and your prod origin, e.g. `https://app.example.com`)
   - **Authorised redirect URIs** (must match `GOOGLE_REDIRECT_URI` character for character):
     `http://localhost:8000/auth/google/callback`
     `https://app.example.com/auth/google/callback`
   - Create → copy **Client ID** and **Client secret** (secret is shown once; you can
     always create a new one).
5. No API needs to be "enabled" for basic sign-in — the People/userinfo endpoint used by
   Socialite works out of the box.

Gotchas: `redirect_uri_mismatch` = the URI in `.env` doesn't exactly equal one in the
console (scheme, port, trailing slash all count). Changes can take a minute to propagate.

### GitHub (OAuth App)

1. Go to <https://github.com/settings/developers> → **OAuth Apps** → **New OAuth App**.
   (For an org-owned app: Organisation → Settings → Developer settings → OAuth Apps.)
2. Fill in:
   - **Application name**: `luav6` (shown to users on the consent screen)
   - **Homepage URL**: `http://localhost:8000` (prod: `https://app.example.com`)
   - **Authorization callback URL**: `http://localhost:8000/auth/github/callback`
3. **Register application**.
4. Copy the **Client ID**, then **Generate a new client secret** and copy it immediately
   (shown once only).
5. Optional: upload a logo under the app settings.

Gotchas: a GitHub OAuth App supports **one** callback URL, so create a **second OAuth App**
for production (dev app + prod app), each with its own credentials. Users with a private
email need the `user:email` scope — the controller will request it. GitHub can also return
a `noreply` address (`123456+user@users.noreply.github.com`) if the user hides their email.

---

## 4. `.env` setup

### `.env` (local development)
```dotenv
APP_URL=http://localhost:8000

# ── Google OAuth ──────────────────────────────────────────────
GOOGLE_CLIENT_ID=1234567890-abcdefghijklmnop.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=GOCSPX-xxxxxxxxxxxxxxxxxxxxxxxx
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

# ── GitHub OAuth ──────────────────────────────────────────────
GITHUB_CLIENT_ID=Ov23liXXXXXXXXXXXXXX
GITHUB_CLIENT_SECRET=xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx
GITHUB_REDIRECT_URI="${APP_URL}/auth/github/callback"
```

`"${APP_URL}/..."` interpolation works in Laravel's dotenv — the quotes are required.
You can also hardcode the full URL if you prefer being explicit.

### `.env.example` (committed — no real values)
```dotenv
# Social login (Laravel Socialite). Leave blank to hide the buttons on /login.
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI="${APP_URL}/auth/google/callback"

GITHUB_CLIENT_ID=
GITHUB_CLIENT_SECRET=
GITHUB_REDIRECT_URI="${APP_URL}/auth/github/callback"
```

### Production
Same four secret keys with the **prod** app credentials and
`https://your-domain.com/auth/{provider}/callback`. Also:

- `APP_URL` must be the real https URL.
- Behind a proxy/load balancer set `TRUSTED_PROXIES=*` so Laravel builds `https://`
  redirect URIs (otherwise Google rejects an `http://` mismatch).
- Run `php artisan config:cache` after changing env vars; Octane needs a restart
  (`php artisan octane:reload`).
- Never commit real secrets — `.env` is git-ignored, `.env.example` is not.

### Quick verification after setup
```bash
php artisan config:clear
php artisan tinker
>>> config('services.google.redirect')
>>> config('services.github.redirect')
```
Then hit `/auth/google/redirect` in the browser — you should land on Google's consent screen.
