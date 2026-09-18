<?php

namespace App\Services;

use App\Models\Setting;
use Laravel\Ai\AiManager;

/**
 * Runtime configuration for the Google Gemini provider.
 *
 * The admin pastes their Gemini API key (and picks models) in the Platform
 * Settings page. These values live in the Setting model, not in config/ai.php,
 * so they can be changed without touching .env. This service is the single
 * place that translates Setting values into the Laravel AI SDK's provider
 * configuration.
 *
 * Because the AI SDK caches resolved provider instances in the AiManager
 * singleton, changing config() at runtime is not enough on its own — the
 * cached "gemini" instance must also be forgotten so the next resolution
 * rebuilds the provider with the new key/model.
 */
class GeminiAIService
{
    /** Default model used by the chat widget. */
    public const DEFAULT_CHAT_MODEL = 'gemini-3.5-flash';

    /** Default model used for essay grading + question/source generation. */
    public const DEFAULT_GRADING_MODEL = 'gemini-3.5-flash';

    protected ?string $apiKey;

    protected string $chatModel;

    protected string $gradingModel;

    public function __construct()
    {
        // Prefer the key pasted in Platform Settings; fall back to the
        // GEMINI_API_KEY env var surfaced through config/ai.php.
        //
        // ⚠️ The fallback MUST be the pristine `env_key` config, not `key`:
        // applyToSdk() mutates the runtime `key` for the current request, and
        // under Octane that mutation persists across requests in the same
        // worker. Falling back to `key` would leak one workspace's pasted key
        // into every other workspace that hasn't pasted its own.
        $this->apiKey = Setting::get('gemini_api_key') ?: config('ai.providers.gemini.env_key');
        $this->chatModel = Setting::get('gemini_chat_model', self::DEFAULT_CHAT_MODEL);
        $this->gradingModel = Setting::get('gemini_grading_model', self::DEFAULT_GRADING_MODEL);
    }

    /**
     * The configured Gemini API key (or null when neither the Setting nor the
     * env fallback are present).
     */
    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * The model used by the chat widget.
     */
    public function chatModel(): string
    {
        return $this->chatModel;
    }

    /**
     * The model used for essay grading and question/source generation.
     */
    public function gradingModel(): string
    {
        return $this->gradingModel;
    }

    /**
     * Point the Laravel AI SDK's "gemini" provider at the DB-stored key/model
     * and drop the cached provider instance so the next prompt resolves with
     * the current credentials.
     *
     * The `env_key` config is left untouched, so the env fallback stays
     * pristine across Octane workers even after this mutates the runtime `key`.
     */
    public function applyToSdk(?string $model = null): void
    {
        config([
            'ai.providers.gemini.key' => $this->apiKey,
            'ai.providers.gemini.models.text.default' => $model ?? $this->chatModel,
        ]);

        app(AiManager::class)->forgetInstance('gemini');
    }
}
