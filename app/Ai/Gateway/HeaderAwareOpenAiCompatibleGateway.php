<?php

namespace App\Ai\Gateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Providers\TextProvider;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use Laravel\Ai\Gateway\StepContext;
use Laravel\Ai\Gateway\StepResponse;
use Laravel\Ai\Gateway\TextGenerationOptions;
use Laravel\Ai\Providers\Provider;

class HeaderAwareOpenAiCompatibleGateway extends OpenAiCompatibleGateway
{
    /**
     * Build the request body for text generation, explicitly requesting
     * non-streaming responses so proxies do not default to SSE.
     */
    protected function buildStepBody(
        Provider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        StepContext $stepContext,
    ): array {
        $body = parent::buildStepBody(
            $provider,
            $model,
            $instructions,
            $messages,
            $tools,
            $schema,
            $options,
            $stepContext,
        );

        $body['stream'] = false;

        return $body;
    }

    /**
     * Execute a text generation step with SSE recovery and clean exceptions.
     */
    public function generateTextStep(
        TextProvider $provider,
        string $model,
        ?string $instructions,
        array $messages,
        array $tools,
        ?array $schema,
        ?TextGenerationOptions $options,
        ?int $timeout,
        StepContext $stepContext,
    ): StepResponse {
        $body = $this->buildStepBody($provider, $model, $instructions, $messages, $tools, $schema, $options, $stepContext);

        $response = $this->withErrorHandling(
            $provider->name(),
            fn () => $this->client($provider, $timeout)->post('chat/completions', $body),
        );

        $data = $response->json();

        if ($data === null && str_starts_with(trim($response->body()), 'data:')) {
            $data = $this->parseSseBodyToChatCompletion($response->body(), $model);
        }

        if (! is_array($data)) {
            throw new AiException(sprintf(
                'OpenAI-compatible Error: Invalid JSON response received from [%s]: %s',
                $provider->name(),
                Str::limit($response->body(), 200),
            ));
        }

        $this->validateTextResponse($data);

        return $this->parseTextResponse($data, $provider, filled($schema));
    }

    /**
     * Reassemble an SSE stream into a standard chat completion array when
     * a proxy ignores stream: false.
     *
     * @return array<string, mixed>
     */
    protected function parseSseBodyToChatCompletion(string $body, string $model): array
    {
        $content = '';
        $id = 'chatcmpl-stream';
        $usage = ['prompt_tokens' => 0, 'completion_tokens' => 0, 'total_tokens' => 0];

        foreach (preg_split('/\r?\n/', $body) as $line) {
            $line = trim($line);
            if (! str_starts_with($line, 'data:')) {
                continue;
            }

            $payload = trim(substr($line, 5));
            if ($payload === '' || $payload === '[DONE]') {
                continue;
            }

            $chunk = json_decode($payload, true);
            if (! is_array($chunk)) {
                continue;
            }

            $id = $chunk['id'] ?? $id;
            $model = $chunk['model'] ?? $model;

            if (isset($chunk['choices'][0]['delta']['content'])) {
                $content .= (string) $chunk['choices'][0]['delta']['content'];
            }

            if (isset($chunk['usage']) && is_array($chunk['usage'])) {
                $usage = $chunk['usage'];
            }
        }

        return [
            'id' => $id,
            'object' => 'chat.completion',
            'model' => $model,
            'choices' => [
                [
                    'index' => 0,
                    'message' => [
                        'role' => 'assistant',
                        'content' => $content,
                    ],
                    'finish_reason' => 'stop',
                ],
            ],
            'usage' => $usage,
        ];
    }

    /**
     * Keep billed usage available when a gateway returns a JSON scalar.
     *
     * @param  array<string, mixed>  $data
     */
    protected function parseTextResponse(
        array $data,
        Provider $provider,
        bool $structured,
    ): StepResponse {
        $response = parent::parseTextResponse($data, $provider, false);

        if ($structured) {
            $text = trim($response->text);
            if (preg_match('/^```(?:json)?\s*(.*?)\s*```$/s', $text, $matches)) {
                $text = trim($matches[1]);
            }

            $decoded = json_decode($text, true);
            $response->structured = is_array($decoded) ? $decoded : [];
        }

        return $response;
    }

    /**
     * Build the standard OpenAI-compatible client, allowing explicit headers
     * to override the bearer Authorization header for compatible gateways
     * that use another authentication scheme.
     */
    protected function client(Provider $provider, ?int $timeout = null): PendingRequest
    {
        $configuration = $provider->additionalConfiguration();
        $client = Http::baseUrl($this->baseUrl($provider))
            ->timeout($timeout ?? 60)
            ->throw();

        if (filled($key = $provider->providerCredentials()['key'] ?? null)) {
            $client->withToken($key);
        }

        return $client->withHeaders($configuration['headers'] ?? []);
    }
}
