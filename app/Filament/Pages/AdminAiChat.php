<?php

namespace App\Filament\Pages;

use App\Models\ChatSession;
use App\Models\Setting;
use App\Services\AiSdkProviderService;
use App\Support\PublicFileUrl;
use App\Support\WorkspaceContext;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;
use Illuminate\Support\Str;

class AdminAiChat extends Page
{
    protected static string $layout = 'filament-panels::components.layout.base';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'AI Studio';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Echo AI';

    protected static ?string $navigationLabel = 'Echo AI';

    protected static ?string $slug = 'ai-chat';

    protected string $view = 'filament.pages.ai-chat';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        if (! $user?->is_admin) {
            return false;
        }

        if ($user->isSuperAdmin()) {
            return true;
        }

        return (bool) Setting::get('admin_ai_chat_enabled', true);
    }

    /**
     * @return array<NavigationItem>
     */
    public static function getNavigationItems(): array
    {
        return [
            NavigationItem::make(static::getNavigationLabel())
                ->group(static::getNavigationGroup())
                ->icon(static::getNavigationIcon())
                ->activeIcon(static::getActiveNavigationIcon())
                ->sort(static::getNavigationSort())
                ->url(static::getNavigationUrl())
                ->openUrlInNewTab(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();
        $provider = (string) Setting::get('ai_provider', 'gemini');
        if (str_starts_with($provider, 'openai-compatible')) {
            $providerLabel = 'OpenAI Compatible';
        } else {
            $providerLabel = AiSdkProviderService::TEXT_PROVIDER_LABELS[$provider] ?? ucfirst($provider);
        }

        $model = match ($provider) {
            'gemini' => Setting::get('gemini_chat_model', 'gemini-3.5-flash'),
            'groq' => Setting::get('groq_chat_model', 'llama-3.3-70b-versatile'),
            'cloudflare' => Setting::get('cloudflare_chat_model', '@cf/meta/llama-3.1-8b-instruct'),
            default => Setting::get("{$provider}_model") ?: (AiSdkProviderService::DEFAULT_MODELS[$provider] ?? 'Standard model'),
        };

        $workspace = app(WorkspaceContext::class)->workspace();

        $requestedParam = request()->query('c') ?? request()->query('session');
        $initialActiveSession = null;

        $sessionsQuery = ChatSession::query()
            ->where('user_id', $user?->id)
            ->latest('updated_at')
            ->limit(30)
            ->get();

        if ($requestedParam && $user) {
            $hasUuidCol = ChatSession::hasUuidColumn();
            $isParamUuid = is_string($requestedParam) && Str::isUuid($requestedParam);
            $isParamNumeric = is_numeric($requestedParam);

            $matchedSession = $sessionsQuery->first(function (ChatSession $s) use ($requestedParam, $hasUuidCol): bool {
                return (string) $s->id === (string) $requestedParam
                    || ($hasUuidCol && $s->uuid && $s->uuid === $requestedParam);
            });

            if (! $matchedSession && ($isParamNumeric || ($hasUuidCol && $isParamUuid))) {
                $matchedSession = ChatSession::query()
                    ->where('user_id', $user->id)
                    ->where(function ($q) use ($requestedParam, $isParamNumeric, $hasUuidCol, $isParamUuid): void {
                        if ($isParamNumeric) {
                            $q->where('id', (int) $requestedParam);
                        } elseif ($hasUuidCol && $isParamUuid) {
                            $q->where('uuid', $requestedParam);
                        } else {
                            $q->whereRaw('1 = 0');
                        }
                    })
                    ->first();

                if ($matchedSession) {
                    $sessionsQuery->prepend($matchedSession);
                }
            }

            $initialActiveSession = $matchedSession;
        }

        $initialSessions = $sessionsQuery
            ->map(fn (ChatSession $session) => [
                'id' => $session->id,
                'uuid' => $session->uuid ?? null,
                'title' => $session->title ?: 'New chat',
                'updated_at' => $session->updated_at?->toISOString(),
                'updated_at_human' => $session->updated_at?->diffForHumans(),
            ])
            ->values()
            ->all();

        return [
            'provider' => $provider,
            'providerLabel' => $providerLabel,
            'modelName' => $model,
            'assistantName' => 'Echo AI',
            'schoolName' => Setting::get('school_name', 'LSI Engine'),
            'assistantLogoUrl' => PublicFileUrl::resolve(Setting::get('school_logo_path')),
            'workspace' => [
                'id' => $workspace?->id,
                'name' => $workspace?->name ?? 'Default Workspace',
            ],
            'adminUser' => [
                'id' => $user?->id,
                'name' => $user?->name ?? 'Administrator',
                'first_name' => $user?->first_name ?? ($user?->name ? explode(' ', trim($user->name))[0] : 'Administrator'),
                'email' => $user?->email,
            ],
            'initialSessions' => $initialSessions,
            'initialActiveSession' => $initialActiveSession ? [
                'id' => $initialActiveSession->id,
                'uuid' => $initialActiveSession->uuid,
                'title' => $initialActiveSession->title ?: 'New chat',
            ] : null,
        ];
    }
}
