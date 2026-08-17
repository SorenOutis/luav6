<?php

namespace App\Services;

use App\Models\Setting;

/**
 * Estimates provider spend in millionths of a US dollar.
 *
 * Provider prices change frequently and custom gateways have arbitrary rates,
 * so administrators may override these defaults with ai_budget_cost_rates.
 * Cost limits are intentionally labelled estimates throughout the UI.
 */
class AiCostEstimator
{
    public const MICROS_PER_DOLLAR = 1_000_000;

    /** @var array<int, array{provider: string, model: string, input: float, output: float}> */
    private const DEFAULT_RATES = [
        ['provider' => 'gemini', 'model' => 'gemini-2.5-pro', 'input' => 1.25, 'output' => 10.00],
        ['provider' => 'gemini', 'model' => 'flash-lite', 'input' => 0.10, 'output' => 0.40],
        ['provider' => 'gemini', 'model' => 'flash', 'input' => 0.30, 'output' => 2.50],
        ['provider' => 'openai', 'model' => 'gpt-4o-mini', 'input' => 0.15, 'output' => 0.60],
        ['provider' => 'anthropic', 'model' => 'haiku', 'input' => 1.00, 'output' => 5.00],
        ['provider' => 'groq', 'model' => '', 'input' => 0.05, 'output' => 0.08],
        ['provider' => 'mistral', 'model' => 'small', 'input' => 0.10, 'output' => 0.30],
        ['provider' => 'deepseek', 'model' => '', 'input' => 0.28, 'output' => 0.42],
        ['provider' => 'xai', 'model' => 'mini', 'input' => 0.30, 'output' => 0.50],
        ['provider' => 'azure', 'model' => 'gpt-4o-mini', 'input' => 0.15, 'output' => 0.60],
        ['provider' => 'ollama', 'model' => '', 'input' => 0.00, 'output' => 0.00],
        ['provider' => 'cloudflare', 'model' => '', 'input' => 0.25, 'output' => 0.75],
        ['provider' => 'openrouter', 'model' => '', 'input' => 1.00, 'output' => 3.00],
        ['provider' => 'openai-compatible-*', 'model' => '', 'input' => 1.00, 'output' => 3.00],
        ['provider' => '*', 'model' => '', 'input' => 1.00, 'output' => 3.00],
    ];

    public function estimateMicros(
        string $provider,
        ?string $model,
        int $inputTokens,
        int $outputTokens,
    ): int {
        $rate = $this->rateFor($provider, $model);

        // A rate in USD per million tokens converts directly to micro-dollars
        // per token (for example, $0.15/M = 0.15 micro-dollars/token).
        return max(0, (int) ceil(
            max(0, $inputTokens) * $rate['input']
            + max(0, $outputTokens) * $rate['output'],
        ));
    }

    /** @return array{input: float, output: float} */
    public function rateFor(string $provider, ?string $model): array
    {
        $provider = strtolower($provider);
        $model = strtolower((string) $model);

        foreach ($this->customRates() as $rate) {
            if ($this->matches($provider, $model, $rate['provider'], $rate['model'])) {
                return ['input' => $rate['input'], 'output' => $rate['output']];
            }
        }

        foreach (self::DEFAULT_RATES as $rate) {
            if ($this->matches($provider, $model, $rate['provider'], $rate['model'])) {
                return ['input' => $rate['input'], 'output' => $rate['output']];
            }
        }

        return ['input' => 1.00, 'output' => 3.00];
    }

    public static function microsToDollars(int $micros): float
    {
        return round($micros / self::MICROS_PER_DOLLAR, 6);
    }

    /** @return array<int, array{provider: string, model: string, input: float, output: float}> */
    private function customRates(): array
    {
        $stored = Setting::get('ai_budget_cost_rates', '[]');
        $rates = is_string($stored) ? json_decode($stored, true) : $stored;

        if (! is_array($rates)) {
            return [];
        }

        return collect($rates)
            ->filter(fn (mixed $rate): bool => is_array($rate) && filled($rate['provider'] ?? null))
            ->map(fn (array $rate): array => [
                'provider' => strtolower(trim((string) $rate['provider'])),
                'model' => strtolower(trim((string) ($rate['model'] ?? ''))),
                'input' => min(100000, max(0, (float) ($rate['input'] ?? 0))),
                'output' => min(100000, max(0, (float) ($rate['output'] ?? 0))),
            ])
            ->values()
            ->all();
    }

    private function matches(string $provider, string $model, string $providerPattern, string $modelNeedle): bool
    {
        $providerMatches = $providerPattern === '*'
            || ($providerPattern === 'openai-compatible-*' && str_starts_with($provider, 'openai-compatible-'))
            || $provider === $providerPattern;

        return $providerMatches && ($modelNeedle === '' || str_contains($model, $modelNeedle));
    }
}
