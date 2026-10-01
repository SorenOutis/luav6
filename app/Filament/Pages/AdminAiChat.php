<?php

namespace App\Filament\Pages;

use App\Models\ChatSession;
use App\Models\Setting;
use App\Services\AiSdkProviderService;
use App\Support\WorkspaceContext;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Page;

class AdminAiChat extends Page
{
    protected static string $layout = 'filament-panels::components.layout.base';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';

    protected static string|\UnitEnum|null $navigationGroup = 'Administration';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'AI Assistant';

    protected static ?string $navigationLabel = 'AI Assistant';

    protected static ?string $slug = 'ai-chat';

    protected string $view = 'filament.pages.ai-chat';

    public static function canAccess(): bool
    {
        return (bool) Filament::auth()->user()?->is_admin;
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

        $initialSessions = ChatSession::query()
            ->where('user_id', $user?->id)
            ->latest('updated_at')
            ->limit(30)
            ->get()
            ->map(fn (ChatSession $session) => [
                'id' => $session->id,
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
        ];
    }
}
