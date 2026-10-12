<?php

namespace App\Services;

use App\Ai\Agents\DailyWorkspaceAnalyst;
use App\Ai\Providers\HeaderAwareOpenAiCompatibleProvider;
use App\Models\AiUsageLog;
use App\Support\WorkspaceContext;
use Closure;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Foundation\Application as LaravelApplication;
use Illuminate\JsonSchema\JsonSchemaTypeFactory;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use InvalidArgumentException;
use Laravel\Ai\AiManager;
use Laravel\Ai\ObjectSchema;
use Laravel\Ai\Responses\StructuredAgentResponse;
use RuntimeException;

class AiDailyReportAnalystService
{
    public function __construct(
        private readonly WorkspaceContext $workspaceContext,
        private readonly AiCostEstimator $costEstimator,
        private readonly AiUsageTracker $usageTracker,
        private readonly AiManager $aiManager,
    ) {}

    /**
     * @param  array<string, mixed>  $snapshot
     * @param  (Closure(array{provider: string, model: string, input_tokens: int, output_tokens: int, estimated_cost_micros: int}): void)|null  $recordUsage
     * @return array{
     *     analysis: array{summary: string, findings: list<array{severity: string, title: string, detail: string, evidence_keys: list<string>}>, recommendations: list<string>},
     *     provider: string,
     *     model: string,
     *     input_tokens: int,
     *     output_tokens: int,
     *     estimated_cost_micros: int
     * }
     */
    public function analyze(array $snapshot, string $provider, float $maxCostUsd, ?Closure $recordUsage = null): array
    {
        return $this->workspaceContext->run(null, function () use ($snapshot, $provider, $maxCostUsd, $recordUsage): array {
            if (! is_finite($maxCostUsd) || $maxCostUsd <= 0) {
                throw new InvalidArgumentException('The daily report estimated cost cap must be positive and finite.');
            }

            if ($provider !== 'gemini' && ! AiSdkProviderService::isSdkRouted($provider)) {
                throw new InvalidArgumentException('The selected daily report provider does not support this analyst.');
            }

            $snapshot = $this->sanitizeSnapshot($snapshot);
            $agent = new DailyWorkspaceAnalyst;
            $prompt = json_encode($snapshot, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);

            if (mb_strlen($prompt) > max(1, (int) config('ai_reports.max_prompt_characters', 50000))) {
                throw new InvalidArgumentException('The daily report snapshot exceeds the prompt size limit.');
            }

            $schema = json_encode(
                (new ObjectSchema($agent->schema(new JsonSchemaTypeFactory)))->toSchema(),
                JSON_THROW_ON_ERROR,
            );

            // One token per UTF-8 byte overbounds text tokenization; reserve extra
            // space for provider message framing and structured-output wrappers.
            $maximumInputTokens = strlen($agent->instructions()) + strlen($prompt) + strlen($schema) + 4096;
            $previousProviders = config('ai.providers', []);
            $compatible = AiSdkProviderService::findCompatibleProvider($provider) !== null;

            try {
                $this->resetProviderConfig($provider, $previousProviders);

                if ($provider === 'gemini') {
                    // Resolve inside the global scope, never reuse a tenant-bound service.
                    $settings = new GeminiAIService;
                    if (blank($settings->apiKey())) {
                        throw new RuntimeException('The selected daily report provider is not configured globally.');
                    }

                    $model = $settings->chatModel();
                } else {
                    $settings = AiSdkProviderService::for($provider);
                    if (! $settings->isConfigured()) {
                        throw new RuntimeException('The selected daily report provider is not configured globally.');
                    }

                    $model = $settings->model();
                }

                if (trim($model) === '') {
                    throw new RuntimeException('The selected daily report provider has no configured model.');
                }

                $maximumCostMicros = $this->costEstimator->estimateMicros(
                    $provider,
                    $model,
                    $maximumInputTokens,
                    $agent->maxTokens(),
                );

                if ($maximumCostMicros > $maxCostUsd * AiCostEstimator::MICROS_PER_DOLLAR) {
                    throw new RuntimeException('The daily report would exceed its estimated cost cap.');
                }

                $settings->applyToSdk($model);
                $response = $agent->prompt($prompt, provider: $provider, model: $model, timeout: $agent->timeout());
                $inputTokens = max(0, $response->usage->promptTokens);
                $outputTokens = max(0, $response->usage->completionTokens);

                // SDK 0.9 separates cached input on these drivers, and Gemini's
                // completion count excludes billed thought tokens.
                if (in_array($provider, ['openai', 'anthropic', 'azure', 'gemini', 'xai'], true)) {
                    $inputTokens += max(0, $response->usage->cacheWriteInputTokens) + max(0, $response->usage->cacheReadInputTokens);
                }
                if ($provider === 'gemini') {
                    $outputTokens += max(0, $response->usage->reasoningTokens);
                }

                $usage = [
                    'provider' => $provider,
                    'model' => $model,
                    'input_tokens' => $inputTokens,
                    'output_tokens' => $outputTokens,
                    'estimated_cost_micros' => $this->costEstimator->estimateMicros($provider, $model, $inputTokens, $outputTokens),
                ];

                // Persist received usage before accounting or output validation can fail.
                $recordUsage?->__invoke($usage);

                // Global reports skip workspace budgets and the tenant creating hook.
                // Strict accounting surfaces ledger failures without another AI call.
                AiUsageLog::withoutEvents(fn () => $this->usageTracker->recordOrFail(
                    $provider, $model, 'daily_report', $inputTokens, $outputTokens,
                ));

                if (! $response instanceof StructuredAgentResponse) {
                    throw new RuntimeException('The daily report provider did not return structured analysis.');
                }

                $rawAnalysis = $response->toArray();
                if ($rawAnalysis === [] && filled($response->text)) {
                    $text = trim($response->text);
                    if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $matches)) {
                        $text = trim($matches[1]);
                    }
                    $decoded = json_decode($text, true);
                    if (is_array($decoded)) {
                        $rawAnalysis = $decoded;
                    }
                }

                $analysis = $this->validateAnalysis($rawAnalysis, array_column($snapshot['metrics'], 'key'));

                return [
                    'analysis' => $analysis,
                    ...$usage,
                ];
            } finally {
                config(['ai.providers' => $previousProviders]);

                if ($compatible) {
                    // applyToSdk registers creators capturing credentials. Replace
                    // those closures with config-only creators before reusing a worker.
                    $creator = fn (Application $app, array $resolvedConfig = []): HeaderAwareOpenAiCompatibleProvider => new HeaderAwareOpenAiCompatibleProvider(
                        $resolvedConfig,
                        $app->make('events'),
                    );
                    $this->aiManager->extend(AiSdkProviderService::HEADER_AWARE_OPENAI_COMPATIBLE_DRIVER, $creator);
                    $this->aiManager->extend($provider, $creator);
                }

                $this->aiManager->forgetInstance($provider);
            }
        });
    }

    /** @param array<string, array<string, mixed>> $previousProviders */
    private function resetProviderConfig(string $provider, array $previousProviders): void
    {
        // Re-read the config file, not mutated worker key/url/deployment/headers.
        // Only env_* entries in the runtime repository are safe fallback overrides.
        $application = LaravelApplication::getInstance();
        $pristine = $application->configurationIsCached()
            ? (require $application->getCachedConfigPath())['ai']
            : require config_path('ai.php');
        $providerConfig = $pristine['providers'][$provider] ?? [];

        foreach (['env_key' => 'key', 'env_url' => 'url'] as $pristineKey => $runtimeKey) {
            if (array_key_exists($pristineKey, $previousProviders[$provider] ?? [])) {
                $providerConfig[$pristineKey] = $previousProviders[$provider][$pristineKey];
                $providerConfig[$runtimeKey] = $previousProviders[$provider][$pristineKey];
            }
        }

        config(["ai.providers.{$provider}" => $providerConfig]);
        $this->aiManager->forgetInstance($provider);
    }

    /**
     * @param  array<string, mixed>  $snapshot
     * @return array{
     *     date: string,
     *     comparison_date: string,
     *     timezone: string,
     *     workspace_ids: list<int>,
     *     section_leaderboards?: list<array{workspace_id: int, section_id: int, section_name: string, top_student_name: ?string, exp: int, level: int}>,
     *     metrics: list<array{key: string, label: string, workspace_id: int, current: int|float, previous: int|float, unit: string}>,
     *     limitations: list<string>
     * }
     */
    private function sanitizeSnapshot(array $snapshot): array
    {
        $snapshot = Arr::only($snapshot, ['date', 'comparison_date', 'timezone', 'workspace_ids', 'section_leaderboards', 'metrics', 'limitations']);

        Validator::make($snapshot, [
            'date' => ['required', 'string', 'date_format:Y-m-d'],
            'comparison_date' => ['required', 'string', 'date_format:Y-m-d'],
            'timezone' => ['required', 'string', 'timezone:all'],
            'workspace_ids' => ['required', 'array', 'list', 'min:1', 'max:25'],
            'workspace_ids.*' => ['required', 'integer:strict', 'min:1', 'distinct:strict'],
            'section_leaderboards' => ['sometimes', 'array', 'list', 'max:100'],
            'section_leaderboards.*.workspace_id' => ['required', 'integer:strict', Rule::in($snapshot['workspace_ids'])],
            'section_leaderboards.*.section_id' => ['required', 'integer:strict', 'min:1'],
            'section_leaderboards.*.section_name' => ['required', 'string', 'max:250'],
            'section_leaderboards.*.top_student_name' => ['nullable', 'string', 'max:250'],
            'section_leaderboards.*.exp' => ['required', 'integer:strict', 'min:0'],
            'section_leaderboards.*.level' => ['required', 'integer:strict', 'min:1'],
            'metrics' => ['present', 'array', 'list', 'max:250'],
            'metrics.*' => ['required', 'array'],
            'limitations' => ['present', 'array', 'list', 'max:25'],
            'limitations.*' => ['required', 'string', 'max:2000'],
        ])->validate();

        if (isset($snapshot['section_leaderboards']) && is_array($snapshot['section_leaderboards'])) {
            $snapshot['section_leaderboards'] = array_map(
                fn (array $item): array => Arr::only($item, ['workspace_id', 'section_id', 'section_name', 'top_student_name', 'exp', 'level']),
                $snapshot['section_leaderboards'],
            );
        }

        $snapshot['metrics'] = array_map(
            fn (array $metric): array => Arr::only($metric, ['key', 'label', 'workspace_id', 'current', 'previous', 'unit']),
            $snapshot['metrics'],
        );

        Validator::make($snapshot, [
            'metrics.*.key' => ['required', 'string', 'max:191', 'regex:/\A[a-zA-Z0-9_.:\-]+\z/', 'distinct:strict'],
            'metrics.*.label' => ['required', 'string', 'max:250'],
            'metrics.*.workspace_id' => ['required', 'integer:strict', Rule::in($snapshot['workspace_ids'])],
            'metrics.*.current' => ['required', 'numeric:strict'],
            'metrics.*.previous' => ['required', 'numeric:strict'],
            'metrics.*.unit' => ['required', 'string', 'max:50'],
        ])->validate();

        foreach ($snapshot['metrics'] as $metric) {
            if (! is_finite($metric['current']) || ! is_finite($metric['previous'])) {
                throw new InvalidArgumentException('Daily report metrics must contain finite numbers.');
            }
        }

        return $snapshot;
    }

    /**
     * @param  array<string, mixed>  $analysis
     * @param  list<string>  $metricKeys
     * @return array{summary: string, findings: list<array{severity: string, title: string, detail: string, evidence_keys: list<string>}>, recommendations: list<string>}
     */
    private function validateAnalysis(array $analysis, array $metricKeys): array
    {
        $textRules = ['required', 'string', 'not_regex:~(?:<[^>]*>|(?:https?://|www\.))~iu'];

        $validated = Validator::make(['analysis' => $analysis], [
            'analysis' => ['required', 'array:summary,findings,recommendations'],
            'analysis.summary' => [...$textRules, 'max:1500'],
            'analysis.findings' => ['present', 'array', 'list', 'max:5'],
            'analysis.findings.*' => ['required', 'array:severity,title,detail,evidence_keys'],
            'analysis.findings.*.severity' => ['required', 'string', Rule::in(['info', 'warning', 'critical'])],
            'analysis.findings.*.title' => [...$textRules, 'max:120'],
            'analysis.findings.*.detail' => [...$textRules, 'max:1000'],
            'analysis.findings.*.evidence_keys' => ['required', 'array', 'list', 'min:1', 'max:8'],
            'analysis.findings.*.evidence_keys.*' => ['required', 'string', Rule::in($metricKeys)],
            'analysis.recommendations' => ['present', 'array', 'list', 'max:5'],
            'analysis.recommendations.*' => [...$textRules, 'max:500'],
        ])->validate();

        return $validated['analysis'];
    }
}
