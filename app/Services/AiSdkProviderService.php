<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Support\Arr;
use Laravel\Ai\AiManager;

/**
 * Runtime configuration for the Laravel AI SDK providers managed from
 * Platform Settings.
 *
 * Gemini, Groq, and Cloudflare keep their dedicated integrations
 * (GeminiAIService / GroqAIService / CloudflareAIService). This service
 * covers every other text-capable SDK provider: it reads the credentials,
 * base URL, and model the admin saved in Platform Settings (Setting model)
 * and points the SDK's cached provider instance at them at runtime.
 *
 * Like GeminiAIService, the env fallback MUST come from the pristine
 * `env_key` / `env_url` config entries — applyToSdk() mutates the runtime
 * `key` / `url`, and under Octane that mutation persists across requests in
 * the same worker, so reading `key` directly would leak one workspace's
 * credentials into another.
 */
class AiSdkProviderService
{
    public const OPENAI_COMPATIBLE_SETTINGS_KEY = 'openai_compatible_providers';

    public const HEADER_AWARE_OPENAI_COMPATIBLE_DRIVER = 'header-aware-openai-compatible';

    /**
     * Providers routed through the Laravel AI SDK, mapped to the model used
     * when the admin has not picked one in Platform Settings.
     *
     * Gemini/Groq/Cloudflare are deliberately absent: they are served by
     * their own services. `azure` prompts against a deployment name.
     *
     * @var array<string, string>
     */
    public const DEFAULT_MODELS = [
        'openai' => 'gpt-4o-mini',
        'anthropic' => 'claude-haiku-4-5-20251001',
        'mistral' => 'mistral-small-latest',
        'deepseek' => 'deepseek-chat',
        'xai' => 'grok-3-mini',
        'openrouter' => 'openai/gpt-4o-mini',
        'azure' => 'gpt-4o',
        'ollama' => 'llama3.2:1b',
    ];

    /** Providers that accept a custom base URL. */
    private const URL_PROVIDERS = ['openai', 'anthropic', 'mistral', 'xai', 'azure', 'ollama'];

    /**
     * Labels for every provider that can serve text generation in the app
     * (the SDK-routed providers plus the dedicated Gemini/Groq/Cloudflare
     * integrations), in display order.
     *
     * @var array<string, string>
     */
    public const TEXT_PROVIDER_LABELS = [
        'gemini' => 'Gemini (Google)',
        'openai' => 'OpenAI',
        'anthropic' => 'Anthropic (Claude)',
        'groq' => 'Groq',
        'mistral' => 'Mistral',
        'deepseek' => 'DeepSeek',
        'xai' => 'xAI (Grok)',
        'openrouter' => 'OpenRouter',
        'azure' => 'Azure OpenAI',
        'ollama' => 'Ollama (Local)',
        'cloudflare' => 'Cloudflare Workers AI',
    ];

    public function __construct(public readonly string $provider) {}

    public static function for(string $provider): self
    {
        return new self($provider);
    }

    /**
     * Text-capable providers that have credentials configured — a key saved
     * in Platform Settings or an env fallback — ready to be offered as
     * one-off generation overrides. Ollama needs no key and is always
     * listed.
     *
     * @return array<string, string> provider key => label
     */
    public static function configuredProviders(): array
    {
        $configured = [
            'gemini' => filled(app(GeminiAIService::class)->apiKey()),
            'cloudflare' => filled(Setting::get('cloudflare_account_id')) && filled(Setting::get('cloudflare_api_token')),
            'groq' => filled(Setting::get('groq_api_key') ?: config('ai.providers.groq.env_key')),
        ];

        foreach (array_keys(self::DEFAULT_MODELS) as $provider) {
            $configured[$provider] = self::for($provider)->isConfigured();
        }

        foreach (self::compatibleProviders() as $provider) {
            $configured[$provider['provider']] = self::for($provider['provider'])->isConfigured();
        }

        return collect(self::textProviderLabels())
            ->filter(fn (string $label, string $key): bool => $configured[$key] ?? false)
            ->all();
    }

    /**
     * Every text provider that may be selected in Platform Settings.
     *
     * @return array<string, string>
     */
    public static function textProviderLabels(): array
    {
        return self::TEXT_PROVIDER_LABELS + collect(self::compatibleProviders())
            ->mapWithKeys(fn (array $provider): array => [
                $provider['provider'] => $provider['name'].' (OpenAI-compatible)',
            ])
            ->all();
    }

    public static function compatibleProviderNameForId(string $id): string
    {
        return self::compatibleProviderName($id);
    }

    /**
     * Read the administrator-managed OpenAI-compatible provider records.
     *
     * @return array<int, array{id: string, provider: string, name: string, url: ?string, model: ?string, api_key: ?string, headers: array<int, array{name: string, value: string}>}>
     */
    public static function compatibleProviders(): array
    {
        $stored = Setting::get(self::OPENAI_COMPATIBLE_SETTINGS_KEY, '[]');
        $providers = is_string($stored) ? json_decode($stored, true) : $stored;

        if (! is_array($providers)) {
            return [];
        }

        return collect($providers)
            ->filter(fn (mixed $provider): bool => is_array($provider) && self::isCompatibleId($provider['id'] ?? null))
            ->map(function (array $provider): array {
                $id = (string) $provider['id'];

                return [
                    'id' => $id,
                    'provider' => self::compatibleProviderName($id),
                    'name' => trim((string) ($provider['name'] ?? '')),
                    'url' => self::nullableString($provider['url'] ?? null),
                    'model' => self::nullableString($provider['model'] ?? null),
                    'api_key' => self::nullableString($provider['api_key'] ?? null),
                    'headers' => self::normalizeHeaders($provider['headers'] ?? []),
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return array{id: string, provider: string, name: string, url: ?string, model: ?string, api_key: ?string, headers: array<int, array{name: string, value: string}>}|null
     */
    public static function findCompatibleProvider(?string $provider): ?array
    {
        return collect(self::compatibleProviders())
            ->firstWhere('provider', $provider);
    }

    /**
     * Whether the app routes this provider through the Laravel AI SDK.
     */
    public static function isSdkRouted(?string $provider): bool
    {
        return $provider !== null
            && (array_key_exists($provider, self::DEFAULT_MODELS) || self::findCompatibleProvider($provider) !== null);
    }

    /**
     * A saved draft may retain a compatible provider after an administrator
     * deletes it. Never reinterpret that explicit selection as another
     * provider or an Ollama fallback.
     */
    public static function isRemovedCompatibleProvider(?string $provider): bool
    {
        return is_string($provider)
            && str_starts_with($provider, 'openai-compatible-')
            && ! self::isSdkRouted($provider);
    }

    /**
     * Whether the provider needs an API key (Ollama runs locally without one).
     */
    public function requiresApiKey(): bool
    {
        if ($this->isOpenAiCompatible()) {
            return false;
        }

        return $this->provider !== 'ollama';
    }

    /**
     * The API key saved in Platform Settings, falling back to the pristine
     * env config value.
     */
    public function apiKey(): ?string
    {
        if ($compatible = $this->compatibleProvider()) {
            return $compatible['api_key'];
        }

        return Setting::get("{$this->provider}_api_key")
            ?: config("ai.providers.{$this->provider}.env_key");
    }

    /**
     * The custom base URL saved in Platform Settings (when the provider
     * supports one), falling back to the pristine env config value.
     */
    public function url(): ?string
    {
        if ($compatible = $this->compatibleProvider()) {
            return $compatible['url'];
        }

        if (! in_array($this->provider, self::URL_PROVIDERS, true)) {
            return null;
        }

        if ($this->provider === 'ollama') {
            // Shared with the Ollama fallback integration — one URL field.
            return Setting::get('ollama_url')
                ?: config('ai.providers.ollama.env_url');
        }

        return Setting::get("{$this->provider}_url")
            ?: config("ai.providers.{$this->provider}.env_url");
    }

    /**
     * The text model to prompt with. Azure prompts against a deployment
     * name instead of a plain model.
     */
    public function model(): string
    {
        if ($compatible = $this->compatibleProvider()) {
            return $compatible['model'] ?? '';
        }

        if ($this->provider === 'azure') {
            return Setting::get('azure_deployment')
                ?: config('ai.providers.azure.deployment')
                ?: self::DEFAULT_MODELS['azure'];
        }

        if ($this->provider === 'ollama') {
            return Setting::get('ollama_model', self::DEFAULT_MODELS['ollama']);
        }

        return Setting::get("{$this->provider}_model")
            ?: self::DEFAULT_MODELS[$this->provider];
    }

    /**
     * Whether the provider has the credentials needed to serve prompts.
     */
    public function isConfigured(): bool
    {
        if ($this->isOpenAiCompatible()) {
            $compatible = $this->compatibleProvider();

            return $compatible !== null && filled($compatible['url']) && filled($compatible['model']);
        }

        return ! $this->requiresApiKey() || filled($this->apiKey());
    }

    /**
     * Point the SDK's provider instance at the DB-stored credentials/model
     * and drop the cached instance so the next prompt resolves with the
     * current configuration. The `env_key`/`env_url` entries stay pristine.
     */
    public function applyToSdk(?string $model = null): void
    {
        if ($compatible = $this->compatibleProvider()) {
            config([
                "ai.providers.{$this->provider}" => [
                    'driver' => self::HEADER_AWARE_OPENAI_COMPATIBLE_DRIVER,
                    'key' => $compatible['api_key'],
                    'url' => $compatible['url'],
                    'headers' => self::headerMap($compatible['headers']),
                    'models' => [
                        'text' => [
                            'default' => $model ?? $compatible['model'],
                        ],
                    ],
                ],
            ]);

            app(AiManager::class)->forgetInstance($this->provider);

            return;
        }

        $config = [
            "ai.providers.{$this->provider}.models.text.default" => $model ?? $this->model(),
        ];

        if ($this->requiresApiKey()) {
            $config["ai.providers.{$this->provider}.key"] = $this->apiKey();
        }

        if ($url = $this->url()) {
            $config["ai.providers.{$this->provider}.url"] = $url;
        }

        if ($this->provider === 'azure') {
            $config['ai.providers.azure.deployment'] = $this->model();
            $config['ai.providers.azure.api_version'] = Setting::get('azure_api_version')
                ?: config('ai.providers.azure.api_version');
            $config['ai.providers.azure.embedding_deployment'] = Setting::get('azure_embedding_deployment')
                ?: config('ai.providers.azure.embedding_deployment');
        }

        config($config);

        app(AiManager::class)->forgetInstance($this->provider);
    }

    private function isOpenAiCompatible(): bool
    {
        return $this->compatibleProvider() !== null;
    }

    /**
     * @return array{id: string, provider: string, name: string, url: ?string, model: ?string, api_key: ?string, headers: array<int, array{name: string, value: string}>}|null
     */
    private function compatibleProvider(): ?array
    {
        return self::findCompatibleProvider($this->provider);
    }

    private static function compatibleProviderName(string $id): string
    {
        return "openai-compatible-{$id}";
    }

    private static function isCompatibleId(mixed $id): bool
    {
        return is_string($id) && preg_match(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i',
            $id,
        ) === 1;
    }

    private static function nullableString(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /**
     * @return array<int, array{name: string, value: string}>
     */
    private static function normalizeHeaders(mixed $headers): array
    {
        if (! is_array($headers)) {
            return [];
        }

        return collect(Arr::isAssoc($headers)
            ? collect($headers)->map(fn (mixed $value, string $name): array => ['name' => $name, 'value' => $value])->all()
            : $headers)
            ->filter(fn (mixed $header): bool => is_array($header))
            ->map(fn (array $header): array => [
                'name' => trim((string) ($header['name'] ?? '')),
                'value' => trim((string) ($header['value'] ?? '')),
            ])
            ->filter(fn (array $header): bool => $header['name'] !== '' && $header['value'] !== '')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array{name: string, value: string}>  $headers
     * @return array<string, string>
     */
    private static function headerMap(array $headers): array
    {
        return collect($headers)
            ->mapWithKeys(fn (array $header): array => [$header['name'] => $header['value']])
            ->all();
    }
}
