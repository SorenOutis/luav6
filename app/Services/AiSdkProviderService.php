<?php

namespace App\Services;

use App\Models\Setting;
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

    public function __construct(public readonly string $provider) {}

    public static function for(string $provider): self
    {
        return new self($provider);
    }

    /**
     * Whether the app routes this provider through the Laravel AI SDK.
     */
    public static function isSdkRouted(?string $provider): bool
    {
        return $provider !== null && array_key_exists($provider, self::DEFAULT_MODELS);
    }

    /**
     * Whether the provider needs an API key (Ollama runs locally without one).
     */
    public function requiresApiKey(): bool
    {
        return $this->provider !== 'ollama';
    }

    /**
     * The API key saved in Platform Settings, falling back to the pristine
     * env config value.
     */
    public function apiKey(): ?string
    {
        return Setting::get("{$this->provider}_api_key")
            ?: config("ai.providers.{$this->provider}.env_key");
    }

    /**
     * The custom base URL saved in Platform Settings (when the provider
     * supports one), falling back to the pristine env config value.
     */
    public function url(): ?string
    {
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
        return ! $this->requiresApiKey() || filled($this->apiKey());
    }

    /**
     * Point the SDK's provider instance at the DB-stored credentials/model
     * and drop the cached instance so the next prompt resolves with the
     * current configuration. The `env_key`/`env_url` entries stay pristine.
     */
    public function applyToSdk(?string $model = null): void
    {
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
}
