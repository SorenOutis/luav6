<?php

namespace App\Ai\Providers;

use App\Ai\Gateway\HeaderAwareOpenAiCompatibleGateway;
use Laravel\Ai\Contracts\Gateway\StepTextGateway;
use Laravel\Ai\Providers\OpenAiCompatibleProvider;

class HeaderAwareOpenAiCompatibleProvider extends OpenAiCompatibleProvider
{
    /**
     * Use the SDK's OpenAI-compatible gateway with support for the
     * administrator-configured request headers.
     */
    public function textGateway(): StepTextGateway
    {
        return $this->textGateway ??= new HeaderAwareOpenAiCompatibleGateway($this->events);
    }
}
