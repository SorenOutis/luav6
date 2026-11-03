<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Setting;
use Laravel\Ai\AiManager;
use RuntimeException;

/**
 * Applies the platform's stored provider credentials/model to the Laravel AI
 * SDK and returns the resolved model name. Extracted from ChatService so queued
 * bot work can configure a provider without booting an HTTP request.
 */
class AiProviderConfigurator
{
    public function prepare(string $provider): ?string
    {
        if (AiSdkProviderService::isRemovedCompatibleProvider($provider)) {
            throw new RuntimeException('The selected OpenAI-compatible provider was removed. Choose another provider in Platform Settings.');
        }

        if ($provider === 'cloudflare') {
            return Setting::get('cloudflare_model', '@cf/zai-org/glm-4.7-flash');
        }

        if ($provider === 'groq') {
            $apiKey = Setting::get('groq_api_key') ?: config('ai.providers.groq.env_key');
            $model = Setting::get('groq_model', 'llama-3.1-8b-instant');
            if (! $apiKey) {
                throw new RuntimeException('Groq is not configured. Paste your API key in Platform Settings.');
            }
            config([
                'ai.providers.groq.key' => $apiKey,
                'ai.providers.groq.models.text.default' => $model,
            ]);
            app(AiManager::class)->forgetInstance('groq');

            return $model;
        }

        if ($provider === 'gemini') {
            $gemini = app(GeminiAIService::class);
            if (! $gemini->apiKey()) {
                throw new RuntimeException('Gemini is not configured. Paste your API key in Platform Settings.');
            }
            $gemini->applyToSdk();

            return $gemini->chatModel();
        }

        if (AiSdkProviderService::isSdkRouted($provider)) {
            $sdkProvider = AiSdkProviderService::for($provider);
            if (! $sdkProvider->isConfigured()) {
                throw new RuntimeException("{$provider} is not configured. Paste its credentials in Platform Settings.");
            }
            $sdkProvider->applyToSdk();

            return $sdkProvider->model();
        }

        throw new RuntimeException("Unsupported AI provider [{$provider}].");
    }

    public function currentProvider(): string
    {
        return (string) Setting::get('ai_provider', 'gemini');
    }

    public function supportsTools(string $provider): bool
    {
        return $provider !== 'cloudflare';
    }
}
