<?php

namespace App\Services;

use App\Exceptions\AiBudgetExceededException;
use App\Models\Setting;
use Throwable;

class AiProviderFallbackPolicy
{
    public const MODE_DISABLED = 'disabled';

    public const MODE_PROVIDER_FAILURE = 'provider_failure';

    public const MODE_PROVIDER_FAILURE_OR_BUDGET = 'provider_failure_or_budget';

    public function fallbackFor(string $primaryProvider, Throwable $failure): ?string
    {
        $mode = $this->mode();
        $isBudgetFailure = $failure instanceof AiBudgetExceededException;
        $allowed = $isBudgetFailure
            ? $mode === self::MODE_PROVIDER_FAILURE_OR_BUDGET
            : in_array($mode, [self::MODE_PROVIDER_FAILURE, self::MODE_PROVIDER_FAILURE_OR_BUDGET], true);

        if (! $allowed) {
            return null;
        }

        $fallback = (string) Setting::get('ai_fallback_provider', 'ollama');
        if ($fallback === '' || $fallback === $primaryProvider) {
            return null;
        }

        return array_key_exists($fallback, AiSdkProviderService::configuredProviders())
            ? $fallback
            : null;
    }

    public function reason(Throwable $failure): string
    {
        return $failure instanceof AiBudgetExceededException
            ? 'budget_limit'
            : 'provider_failure';
    }

    public function mode(): string
    {
        $legacyEnabled = (string) Setting::get('ollama_enabled', '0') === '1';
        $mode = (string) Setting::get(
            'ai_fallback_mode',
            $legacyEnabled ? self::MODE_PROVIDER_FAILURE : self::MODE_DISABLED,
        );

        return in_array($mode, [
            self::MODE_DISABLED,
            self::MODE_PROVIDER_FAILURE,
            self::MODE_PROVIDER_FAILURE_OR_BUDGET,
        ], true) ? $mode : self::MODE_DISABLED;
    }
}
