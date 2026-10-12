<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use App\Models\Workspace;
use App\Services\AiSdkProviderService;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator as SettingsValidator;

class AiDailyReportSettings
{
    public const SETTINGS_KEY = 'ai_daily_reports';

    /**
     * @return array{enabled: bool, time: string, workspace_ids: list<int>, recipient_id: ?int, provider: string, max_cost_usd: float}
     */
    public static function state(): array
    {
        return app(WorkspaceContext::class)->run(null, function (): array {
            $provider = Setting::get('ai_provider', 'gemini');
            $defaults = [
                'enabled' => false,
                'time' => (string) config('ai_reports.time', '00:15'),
                'workspace_ids' => [],
                'recipient_id' => null,
                'provider' => is_string($provider) && ($provider === 'gemini' || AiSdkProviderService::isSdkRouted($provider))
                    ? $provider
                    : 'gemini',
                'max_cost_usd' => 0.25,
            ];
            $stored = Setting::get(self::SETTINGS_KEY);
            $data = is_string($stored) ? json_decode($stored, true) : null;

            if (! is_array($data) || ! is_bool($data['enabled'] ?? null)) {
                return $defaults;
            }

            $data['time'] ??= $defaults['time'];

            $rules = self::rules($data['enabled']);
            $rules['provider'][] = Rule::in(array_keys(array_diff_key(
                AiSdkProviderService::textProviderLabels(),
                array_flip(['groq', 'cloudflare']),
            )));
            $validator = Validator::make($data, $rules);

            if ($validator->fails()) {
                return $defaults;
            }

            return self::canonicalState($validator->validated());
        });
    }

    /**
     * @return array<string, string>
     */
    public static function providerOptions(): array
    {
        return app(WorkspaceContext::class)->run(null, fn (): array => array_diff_key(
            AiSdkProviderService::configuredProviders(),
            array_flip(['groq', 'cloudflare']),
        ));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public static function save(array $data): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User && User::query()
            ->whereKey($user->getAuthIdentifier())
            ->where('is_admin', true)
            ->where('is_super_admin', true)
            ->exists(), 403);

        app(WorkspaceContext::class)->run(null, function () use ($data): void {
            $enabled = in_array($data['enabled'] ?? false, [true, 1, '1'], true);
            $rules = self::rules($enabled);
            $rules['workspace_ids.*'][] = Rule::exists(Workspace::class, 'id');
            $rules['recipient_id'][] = Rule::exists(User::class, 'id')
                ->where(fn (Builder $query): Builder => $query
                    ->where(fn (Builder $q) => $q
                        ->where('is_super_admin', true)
                        ->orWhere(fn (Builder $sub) => $sub->where('is_admin', true)->where('is_super_admin', true))));
            $rules['provider'][] = Rule::in(array_keys($enabled
                ? self::providerOptions()
                : array_diff_key(AiSdkProviderService::textProviderLabels(), array_flip(['groq', 'cloudflare']))));

            $validator = Validator::make($data, $rules, [
                'workspace_ids.required' => 'Select at least one workspace before enabling reports.',
                'workspace_ids.max' => 'Select no more than 25 workspaces.',
                'recipient_id.required' => 'Select a super admin to receive reports.',
                'recipient_id.exists' => 'The recipient must be an existing super admin.',
                'provider.in' => 'Choose a supported provider with global credentials, or local Ollama. Groq and Cloudflare are not supported for daily reports.',
                'time.required' => 'Daily report time is required.',
                'time.regex' => 'Daily report time must use 24-hour HH:MM format.',
            ]);
            $validator->after(function (SettingsValidator $validator) use ($data): void {
                if (array_diff(array_keys($data), ['enabled', 'time', 'workspace_ids', 'recipient_id', 'provider', 'max_cost_usd']) !== []) {
                    $validator->errors()->add('settings', 'Only daily report settings may be saved. Timezone is read-only.');
                }
            });

            Setting::setGlobal(self::SETTINGS_KEY, json_encode(
                self::canonicalState($validator->validate()),
                JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION,
            ));
        });
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    private static function rules(bool $enabled): array
    {
        return [
            'enabled' => ['required', 'boolean'],
            'time' => ['required', 'string', 'regex:/\A(?:[01][0-9]|2[0-3]):[0-5][0-9](?::[0-5][0-9])?\z/'],
            'workspace_ids' => ['present', 'array', 'list', 'max:25', Rule::requiredIf($enabled)],
            'workspace_ids.*' => ['required', 'integer', 'min:1', 'distinct'],
            'recipient_id' => ['present', 'nullable', 'integer', 'min:1', Rule::requiredIf($enabled)],
            'provider' => ['required', 'string', 'max:255'],
            'max_cost_usd' => ['required', 'numeric', 'between:0.001,10'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{enabled: bool, time: string, workspace_ids: list<int>, recipient_id: ?int, provider: string, max_cost_usd: float}
     */
    private static function canonicalState(array $data): array
    {
        $time = (string) ($data['time'] ?? config('ai_reports.time', '00:15'));
        if (preg_match('/\A((?:[01][0-9]|2[0-3]):[0-5][0-9])(?::[0-5][0-9])?\z/', $time, $matches)) {
            $time = $matches[1];
        }

        return [
            'enabled' => (bool) $data['enabled'],
            'time' => $time,
            'workspace_ids' => array_values(array_map(fn (mixed $id): int => (int) $id, $data['workspace_ids'])),
            'recipient_id' => blank($data['recipient_id']) ? null : (int) $data['recipient_id'],
            'provider' => $data['provider'],
            'max_cost_usd' => (float) $data['max_cost_usd'],
        ];
    }
}
