<?php

namespace App\Support;

/**
 * The social login providers this application supports.
 *
 * Google and GitHub are built into Laravel Socialite, so a provider counts as
 * "available" purely on whether its credentials are configured. Nothing here
 * needs the socialiteproviders/* manager package.
 */
final class SocialProviders
{
    public const GOOGLE = 'google';

    public const GITHUB = 'github';

    /** @var array<string, array{label: string, scopes: list<string>}> */
    private const PROVIDERS = [
        self::GOOGLE => [
            'label' => 'Google',
            'scopes' => ['openid', 'profile', 'email'],
        ],
        self::GITHUB => [
            'label' => 'GitHub',
            // GitHub only exposes a private primary email with this scope.
            'scopes' => ['read:user', 'user:email'],
        ],
    ];

    /**
     * Every provider the app knows about, configured or not.
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return array_keys(self::PROVIDERS);
    }

    public static function supports(?string $provider): bool
    {
        return $provider !== null && array_key_exists($provider, self::PROVIDERS);
    }

    /**
     * A provider is enabled once its client id and secret are present.
     */
    public static function enabled(string $provider): bool
    {
        if (! self::supports($provider)) {
            return false;
        }

        return filled(config("services.{$provider}.client_id"))
            && filled(config("services.{$provider}.client_secret"));
    }

    /**
     * @return list<string>
     */
    public static function enabledProviders(): array
    {
        return array_values(array_filter(self::all(), self::enabled(...)));
    }

    /**
     * @return list<string>
     */
    public static function scopes(string $provider): array
    {
        return self::PROVIDERS[$provider]['scopes'] ?? [];
    }

    public static function label(string $provider): string
    {
        return self::PROVIDERS[$provider]['label'] ?? ucfirst($provider);
    }

    /**
     * Shape consumed by the Inertia auth pages / settings page.
     *
     * @return list<array{name: string, label: string}>
     */
    public static function forInertia(): array
    {
        return array_map(
            fn (string $provider): array => [
                'name' => $provider,
                'label' => self::label($provider),
            ],
            self::enabledProviders()
        );
    }
}
