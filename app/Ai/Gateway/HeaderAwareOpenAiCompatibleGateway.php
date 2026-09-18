<?php

namespace App\Ai\Gateway;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Gateway\OpenAiCompatible\OpenAiCompatibleGateway;
use Laravel\Ai\Providers\Provider;

class HeaderAwareOpenAiCompatibleGateway extends OpenAiCompatibleGateway
{
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
