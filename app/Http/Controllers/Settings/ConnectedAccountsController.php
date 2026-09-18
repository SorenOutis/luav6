<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\SocialAccount;
use App\Support\SocialProviders;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * "Connected accounts" — link or unlink Google / GitHub on an existing account.
 */
class ConnectedAccountsController extends Controller
{
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $linked = $user->socialAccounts()
            ->get()
            ->keyBy('provider');

        $providers = collect(SocialProviders::enabledProviders())
            ->map(function (string $provider) use ($linked): array {
                /** @var SocialAccount|null $account */
                $account = $linked->get($provider);

                return [
                    'name' => $provider,
                    'label' => SocialProviders::label($provider),
                    'connected' => $account !== null,
                    'email' => $account?->email,
                    'nickname' => $account?->nickname,
                    'connectedAt' => $account?->created_at?->toDayDateTimeString(),
                ];
            })
            ->values()
            ->all();

        return Inertia::render('settings/ConnectedAccounts', [
            'providers' => $providers,
            'hasPassword' => $user->hasPassword(),
            'linkedCount' => $linked->count(),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function destroy(Request $request, string $provider): RedirectResponse
    {
        abort_unless(SocialProviders::supports($provider), 404);

        $user = $request->user();

        $account = $user->socialAccounts()->where('provider', $provider)->first();

        if (! $account) {
            return back()->withErrors([
                'provider' => 'That account is not connected.',
            ]);
        }

        // Never let someone remove their last way of signing in.
        if (! $user->hasPassword() && $user->socialAccounts()->count() <= 1) {
            return back()->withErrors([
                'provider' => 'Set a password first — this is currently the only way you can sign in.',
            ]);
        }

        $account->delete();

        return back()->with('status', SocialProviders::label($provider).' account disconnected.');
    }
}
