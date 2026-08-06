<?php

namespace App\Services;

use App\Models\AiUsageLog;
use Illuminate\Support\Facades\DB;

/**
 * Estimates and records AI token/neuron usage.
 *
 * Cloudflare does not expose a public API for Workers AI neuron usage (it is
 * dashboard-only), so this service approximates usage from what the app itself
 * sends: tokens are estimated at ~4 chars/token, and neuron cost is looked up
 * from Cloudflare's published per-model rates (neurons per million tokens).
 *
 * The daily free allowance is 10,000 neurons, resetting at 00:00 UTC.
 */
class AiUsageTracker
{
    public const DAILY_NEURON_LIMIT = 10_000;

    /**
     * Ordered [needle, input-neurons-per-M-tokens, output-neurons-per-M-tokens]
     * lookup. First match wins, so most specific needles come first.
     * Rates are from https://developers.cloudflare.com/workers-ai/platform/pricing/
     *
     * @var array<int, array{0: string, 1: int, 2: int}>
     */
    private const PRICING = [
        ['llama-3.2-1b', 2457, 18252],
        ['llama-3.2-3b', 4625, 30475],
        ['llama-3.1-8b-instruct-fp8-fast', 4119, 34868],
        ['llama-3.1-8b-instruct-fp8', 13778, 26128],
        ['llama-3.1-8b-instruct-awq', 11161, 24215],
        ['llama-3.1-8b', 25608, 75147],
        ['llama-3-8b-instruct-awq', 11161, 24215],
        ['llama-3-8b', 25608, 75147],
        ['glm-4.7-flash', 5500, 36400],
        ['glm-4.7', 5500, 36400],
        ['llama-4-scout', 24545, 77273],
        ['gemma-3-12b', 31371, 50560],
        ['gpt-oss-20b', 18182, 27273],
        ['gpt-oss-120b', 31818, 68182],
        ['qwen3-30b-a3b-fp8', 4625, 30475],
        ['mistral-small-3.1-24b', 31876, 50488],
        ['mistral-7b', 10000, 17300],
        // Unknown models are charged the llama-3.1-8b rate as a conservative
        // default so the estimate never under-reports toward the daily cap.
        ['', 25608, 75147],
    ];

    /**
     * Record one AI call in the daily usage log.
     *
     * Neurons are only meaningful for Cloudflare models; other providers
     * (gemini/groq/ollama) are logged with token counts but a null neuron
     * estimate.
     */
    public function record(
        string $provider,
        ?string $model,
        string $source,
        int $inputTokens,
        int $outputTokens
    ): void {
        try {
            AiUsageLog::create([
                'date' => now()->toDateString(),
                'provider' => $provider,
                'model' => $model,
                'source' => $source,
                'input_tokens' => max(0, $inputTokens),
                'output_tokens' => max(0, $outputTokens),
                'neurons' => $provider === 'cloudflare'
                    ? $this->estimateNeurons($model, $inputTokens, $outputTokens)
                    : null,
            ]);
        } catch (\Throwable $e) {
            // Usage tracking must never break the AI call itself.
            report($e);
        }
    }

    /**
     * Estimate neurons for a Cloudflare call using the per-model rates.
     *
     * Unknown/empty models fall back to the conservative llama-3.1-8b rate so
     * the daily estimate never under-reports toward the cap.
     */
    public function estimateNeurons(?string $model, int $inputTokens, int $outputTokens): float
    {
        [$inputPerM, $outputPerM] = $this->pricingFor($model ?? '');

        $neurons = ($inputTokens / 1_000_000) * $inputPerM
            + ($outputTokens / 1_000_000) * $outputPerM;

        return round($neurons, 2);
    }

    /**
     * @return array{0: int, 1: int}
     */
    private function pricingFor(string $model): array
    {
        $model = strtolower($model);

        foreach (self::PRICING as [$needle, $inputPerM, $outputPerM]) {
            if ($needle === '' || str_contains($model, $needle)) {
                return [$inputPerM, $outputPerM];
            }
        }

        return [25608, 75147];
    }

    /**
     * Rough token count for a block of text (~4 characters per token).
     */
    public static function tokensFromChars(int $chars): int
    {
        return max(1, intdiv($chars, 4));
    }

    // ──────────────────────────────────────────────
    //   Aggregation helpers for the dashboard widget
    // ──────────────────────────────────────────────

    /**
     * Estimated neurons recorded for the given day (UTC).
     */
    public static function neuronsForDay(?string $date = null): float
    {
        $date ??= now()->toDateString();

        return (float) AiUsageLog::query()
            ->where('date', $date)
            ->whereNotNull('neurons')
            ->sum('neurons');
    }

    /**
     * Estimated neurons per day for the last $days days, oldest first.
     *
     * @return array<string, float>
     */
    public static function neuronsForLastDays(int $days = 7): array
    {
        $start = now()->startOfDay()->subDays($days - 1);

        $raw = AiUsageLog::query()
            ->where('date', '>=', $start->toDateString())
            ->whereNotNull('neurons')
            ->select('date', DB::raw('SUM(neurons) as total'))
            ->groupBy('date')
            ->pluck('total', 'date');

        $series = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $day = now()->startOfDay()->subDays($i)->toDateString();
            $series[$day] = (float) ($raw[$day] ?? 0);
        }

        return $series;
    }

    /**
     * Number of recorded AI calls today, grouped by source.
     *
     * @return array<string, int>
     */
    public static function requestsTodayBySource(): array
    {
        $counts = AiUsageLog::query()
            ->where('date', now()->toDateString())
            ->select('source', DB::raw('COUNT(*) as total'))
            ->groupBy('source')
            ->pluck('total', 'source');

        return [
            'chat' => (int) ($counts['chat'] ?? 0),
            'grading' => (int) ($counts['grading'] ?? 0),
            'generation' => (int) ($counts['generation'] ?? 0),
        ];
    }
}
