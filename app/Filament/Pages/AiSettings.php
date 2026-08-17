<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use App\Services\AiSdkProviderService;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AiSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->is_admin;
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Platform Settings';

    protected static ?string $navigationLabel = 'Platform Settings';

    protected string $view = 'filament.pages.ai-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $provider = Setting::get('ai_provider', 'gemini');
        $compatibleProviders = collect(AiSdkProviderService::compatibleProviders())
            ->map(fn (array $compatible): array => [
                'id' => $compatible['id'],
                'name' => $compatible['name'],
                'url' => $compatible['url'],
                'model' => $compatible['model'],
                'api_key' => $compatible['api_key'],
                'headers' => $compatible['headers'],
                'is_default' => $provider === $compatible['provider'],
            ])
            ->all();

        $this->form->fill(array_merge([
            'ai_chat_enabled' => (bool) Setting::get('ai_chat_enabled', true),
            'ai_chat_maintenance_message' => Setting::get('ai_chat_maintenance_message', 'The AI service is currently under maintenance. Please try again later.'),
            'ai_budget_enabled' => (string) Setting::get('ai_budget_enabled', '0') === '1',
            'ai_budget_daily_tokens' => (int) Setting::get('ai_budget_daily_tokens', 0),
            'ai_budget_monthly_tokens' => (int) Setting::get('ai_budget_monthly_tokens', 0),
            'ai_budget_daily_cost' => (float) Setting::get('ai_budget_daily_cost', 0),
            'ai_budget_monthly_cost' => (float) Setting::get('ai_budget_monthly_cost', 0),
            'ai_budget_warning_percent' => (int) Setting::get('ai_budget_warning_percent', 80),
            'ai_fallback_mode' => Setting::get(
                'ai_fallback_mode',
                (string) Setting::get('ollama_enabled', '0') === '1' ? 'provider_failure' : 'disabled',
            ),
            'ai_fallback_provider' => Setting::get('ai_fallback_provider', 'ollama'),
            'ai_budget_cost_rates' => $this->storedCostRates(),
            'ai_provider' => $provider,
            'gemini_api_key' => Setting::get('gemini_api_key'),
            'gemini_chat_model' => Setting::get('gemini_chat_model', 'gemini-3.5-flash'),
            'gemini_grading_model' => Setting::get('gemini_grading_model', 'gemini-3.5-flash'),
            'openai_api_key' => Setting::get('openai_api_key'),
            'openai_url' => Setting::get('openai_url'),
            'openai_model' => Setting::get('openai_model'),
            'anthropic_api_key' => Setting::get('anthropic_api_key'),
            'anthropic_url' => Setting::get('anthropic_url'),
            'anthropic_model' => Setting::get('anthropic_model'),
            'mistral_api_key' => Setting::get('mistral_api_key'),
            'mistral_url' => Setting::get('mistral_url'),
            'mistral_model' => Setting::get('mistral_model'),
            'deepseek_api_key' => Setting::get('deepseek_api_key'),
            'deepseek_model' => Setting::get('deepseek_model'),
            'xai_api_key' => Setting::get('xai_api_key'),
            'xai_url' => Setting::get('xai_url'),
            'xai_model' => Setting::get('xai_model'),
            'openrouter_api_key' => Setting::get('openrouter_api_key'),
            'openrouter_model' => Setting::get('openrouter_model'),
            'azure_api_key' => Setting::get('azure_api_key'),
            'azure_url' => Setting::get('azure_url'),
            'azure_api_version' => Setting::get('azure_api_version'),
            'azure_deployment' => Setting::get('azure_deployment'),
            'azure_embedding_deployment' => Setting::get('azure_embedding_deployment'),
            'cohere_api_key' => Setting::get('cohere_api_key'),
            'jina_api_key' => Setting::get('jina_api_key'),
            'voyageai_api_key' => Setting::get('voyageai_api_key'),
            'eleven_api_key' => Setting::get('eleven_api_key'),
            'cloudflare_account_id' => Setting::get('cloudflare_account_id'),
            'cloudflare_api_token' => Setting::get('cloudflare_api_token'),
            'cloudflare_model' => Setting::get('cloudflare_model', '@cf/zai-org/glm-4.7-flash'),
            // Backfill: until an admin picks a grading model explicitly, keep
            // using whatever cloudflare_model was set to (existing installs).
            'cloudflare_grading_model' => Setting::get('cloudflare_grading_model') ?? Setting::get('cloudflare_model', '@cf/meta/llama-3.1-8b-instruct'),
            'groq_api_key' => Setting::get('groq_api_key'),
            'groq_model' => Setting::get('groq_model', 'llama-3.1-8b-instant'),
            'ollama_url' => Setting::get('ollama_url', 'http://localhost:11434'),
            'ollama_model' => Setting::get('ollama_model', 'llama3.2:1b'),
            'openai_compatible_providers' => $compatibleProviders,
            'login_enabled' => (bool) Setting::get('login_enabled', true),
            'login_disabled_message' => Setting::get('login_disabled_message', 'Login is currently disabled. Please try again later.'),
            'registration_enabled' => (bool) Setting::get('registration_enabled', true),
            'registration_disabled_message' => Setting::get('registration_disabled_message', 'Registration is currently disabled. Please try again later.'),
            'daily_claim_enabled' => (bool) Setting::get('daily_claim_enabled', true),
            'daily_claim_base_xp' => (int) Setting::get('daily_claim_base_xp', 1),
            'welcome_demo_video_path' => Setting::get('welcome_demo_video_path'),
            'school_name' => Setting::get('school_name', 'LSI Engine'),
            'school_tagline' => Setting::get('school_tagline', 'Learning Systems Intelligence'),
            'school_logo_path' => Setting::get('school_logo_path'),
            'school_accent_color' => Setting::get('school_accent_color', '#f59e0b'),
        ], collect($this->defaultableProviders())
            ->keys()
            ->mapWithKeys(fn (string $key): array => ["provider_default_{$key}" => $provider === $key])
            ->all()));
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Access Control')
                    ->description('Manage platform access for students.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Toggle::make('login_enabled')
                                    ->label('Enable Login')
                                    ->helperText('If disabled, students will not be able to log in to their accounts.')
                                    ->reactive(),

                                Toggle::make('registration_enabled')
                                    ->label('Enable Registration')
                                    ->helperText('If disabled, new students will not be able to create accounts.')
                                    ->reactive(),
                            ]),

                        Textarea::make('login_disabled_message')
                            ->label('Login Disabled Message')
                            ->placeholder('Enter the message to display when login is disabled...')
                            ->required()
                            ->visible(fn ($get) => ! $get('login_enabled'))
                            ->columnSpanFull(),

                        Textarea::make('registration_disabled_message')
                            ->label('Registration Disabled Message')
                            ->placeholder('Enter the message to display when registration is disabled...')
                            ->required()
                            ->visible(fn ($get) => ! $get('registration_enabled'))
                            ->columnSpanFull(),
                    ]),

                $this->aiProviderSection(),

                Section::make('AI Chat')
                    ->description('Manage AI chat availability in both the floating widget and the Chats page.')
                    ->schema([
                        Toggle::make('ai_chat_enabled')
                            ->label('Enable AI Chat')
                            ->helperText('If disabled, the floating widget and Chats page composer will show the maintenance message. Students can still open the Chats page and read their history.')
                            ->reactive(),

                        Textarea::make('ai_chat_maintenance_message')
                            ->label('Maintenance Message')
                            ->placeholder('Enter the message to display when AI chat is disabled...')
                            ->required()
                            ->visible(fn ($get) => ! $get('ai_chat_enabled')),
                    ]),

                $this->aiBudgetSection(),

                Section::make('Daily XP Claim')
                    ->description('Configure the daily login reward students can claim on their dashboard.')
                    ->schema([
                        Toggle::make('daily_claim_enabled')
                            ->label('Enable Daily XP Claim')
                            ->helperText('If disabled, the claim button and daily reward prompt are hidden from the student dashboard.')
                            ->reactive(),

                        TextInput::make('daily_claim_base_xp')
                            ->label('Base XP per Claim')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->maxValue(1000)
                            ->required()
                            ->helperText('XP awarded per daily claim before the streak bonus. Streaks add +1 XP every 5 days, up to +4 (e.g. base 1 → 1–5 XP).')
                            ->visible(fn ($get) => (bool) $get('daily_claim_enabled')),
                    ]),

                Section::make('Welcome Demo Video')
                    ->description('Upload the video shown when visitors click Watch Demo on the welcome page.')
                    ->schema([
                        FileUpload::make('welcome_demo_video_path')
                            ->label('Demo Video')
                            ->disk('public')
                            ->directory('welcome-demo')
                            ->acceptedFileTypes(['video/mp4', 'video/webm', 'video/ogg'])
                            ->maxSize(204800)
                            ->downloadable()
                            ->openable()
                            ->helperText('Upload an MP4, WebM, or OGG video. If this is empty, the welcome page shows a polished placeholder.'),
                    ]),

                Section::make('School Branding')
                    ->description('Customize the visible school identity used on public and app-facing screens.')
                    ->schema([
                        TextInput::make('school_name')
                            ->label('School / Platform Name')
                            ->required()
                            ->maxLength(80)
                            ->helperText('Shown in the welcome header, auth screens, and app sidebar.'),

                        Textarea::make('school_tagline')
                            ->label('Tagline')
                            ->rows(2)
                            ->maxLength(160)
                            ->helperText('Short supporting line for public/auth surfaces.'),

                        FileUpload::make('school_logo_path')
                            ->label('School Logo')
                            ->image()
                            ->disk('public')
                            ->directory('branding')
                            ->maxSize(10240)
                            ->downloadable()
                            ->openable()
                            ->helperText('Upload a square or horizontal logo. Transparent PNG works best.'),

                        ColorPicker::make('school_accent_color')
                            ->label('Accent Color')
                            ->default('#f59e0b')
                            ->helperText('Used as a visual accent in branded areas.'),
                    ])
                    ->columns(2),
            ])
            ->statePath('data');
    }

    private function aiBudgetSection(): Section
    {
        return Section::make('Workspace AI Budget & Fallback')
            ->description('Set tenant-specific token and estimated-cost ceilings. Reservations are atomic, so concurrent requests cannot overspend the configured budget.')
            ->schema([
                Toggle::make('ai_budget_enabled')
                    ->label('Enforce workspace AI budgets')
                    ->helperText('Disabled by default. A value of 0 for any limit means unlimited.')
                    ->live(),

                Grid::make(2)->schema([
                    TextInput::make('ai_budget_daily_tokens')
                        ->label('Daily token limit')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Input plus output tokens. 0 = unlimited.'),
                    TextInput::make('ai_budget_monthly_tokens')
                        ->label('Monthly token limit')
                        ->numeric()
                        ->integer()
                        ->minValue(0)
                        ->default(0)
                        ->helperText('Input plus output tokens. 0 = unlimited.'),
                    TextInput::make('ai_budget_daily_cost')
                        ->label('Daily estimated-cost limit')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1000000)
                        ->step(0.01)
                        ->prefix('$')
                        ->default(0)
                        ->helperText('Estimated USD. 0 = unlimited.'),
                    TextInput::make('ai_budget_monthly_cost')
                        ->label('Monthly estimated-cost limit')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(1000000)
                        ->step(0.01)
                        ->prefix('$')
                        ->default(0)
                        ->helperText('Estimated USD. 0 = unlimited.'),
                    TextInput::make('ai_budget_warning_percent')
                        ->label('Warning threshold')
                        ->numeric()
                        ->integer()
                        ->minValue(50)
                        ->maxValue(100)
                        ->suffix('%')
                        ->default(80)
                        ->helperText('Creates one warning event per daily/monthly period.'),
                ])->visible(fn (Get $get): bool => (bool) $get('ai_budget_enabled')),

                Grid::make(2)->schema([
                    Select::make('ai_fallback_mode')
                        ->label('Fallback rule')
                        ->options([
                            'disabled' => 'Never fall back',
                            'provider_failure' => 'On provider failure only',
                            'provider_failure_or_budget' => 'On provider failure or cost/token budget block',
                        ])
                        ->required()
                        ->default('disabled')
                        ->helperText('Budget fallback is most useful with a local or lower-cost provider.'),
                    Select::make('ai_fallback_provider')
                        ->label('Fallback provider')
                        ->options($this->defaultableProviders())
                        ->searchable()
                        ->required()
                        ->default('ollama')
                        ->helperText('Must be configured and different from the default provider.'),
                ]),

                Repeater::make('ai_budget_cost_rates')
                    ->label('Custom estimated provider rates')
                    ->helperText('Optional USD per million-token overrides. The first matching provider/model pattern wins; leave empty to use conservative built-in estimates.')
                    ->addActionLabel('Add cost-rate override')
                    ->defaultItems(0)
                    ->collapsible()
                    ->columns(2)
                    ->schema([
                        Select::make('provider')
                            ->options($this->defaultableProviders())
                            ->searchable()
                            ->required(),
                        TextInput::make('model')
                            ->label('Model contains')
                            ->maxLength(160)
                            ->helperText('Optional case-insensitive model substring.'),
                        TextInput::make('input')
                            ->label('Input $ / 1M tokens')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100000)
                            ->step(0.0001)
                            ->required(),
                        TextInput::make('output')
                            ->label('Output $ / 1M tokens')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(100000)
                            ->step(0.0001)
                            ->required(),
                    ]),
            ]);
    }

    /** @return array<int, array<string, mixed>> */
    private function storedCostRates(): array
    {
        $stored = Setting::get('ai_budget_cost_rates', '[]');
        $rates = is_string($stored) ? json_decode($stored, true) : $stored;

        return is_array($rates) ? array_values(array_filter($rates, 'is_array')) : [];
    }

    /**
     * Providers that can serve text (chat widget, essay grading, and AI
     * question generation) and can therefore be marked as the default.
     * The shared map lives on AiSdkProviderService so the provider pickers
     * on the AI Question Draft screens stay in sync.
     *
     * @return array<string, string> Provider key => card label
     */
    private function defaultableProviders(): array
    {
        return AiSdkProviderService::textProviderLabels();
    }

    /**
     * Field metadata for the standard Laravel AI SDK provider cards.
     *
     * @return array<string, array{help: string, env: string, url: ?string}>
     */
    private function sdkProviderFields(): array
    {
        return [
            'openai' => ['help' => 'Create one at platform.openai.com.', 'env' => 'OPENAI_API_KEY', 'url' => 'https://api.openai.com/v1'],
            'anthropic' => ['help' => 'Create one at console.anthropic.com.', 'env' => 'ANTHROPIC_API_KEY', 'url' => 'https://api.anthropic.com/v1'],
            'mistral' => ['help' => 'Create one at console.mistral.ai.', 'env' => 'MISTRAL_API_KEY', 'url' => 'https://api.mistral.ai/v1'],
            'deepseek' => ['help' => 'Create one at platform.deepseek.com.', 'env' => 'DEEPSEEK_API_KEY', 'url' => null],
            'xai' => ['help' => 'Create one at console.x.ai.', 'env' => 'XAI_API_KEY', 'url' => 'https://api.x.ai/v1'],
            'openrouter' => ['help' => 'Create one at openrouter.ai/keys.', 'env' => 'OPENROUTER_API_KEY', 'url' => null],
        ];
    }

    /**
     * The "Default provider" checkbox on a provider card. Radio semantics:
     * exactly one provider is always the default — checking a card unchecks
     * the others, and unchecking the current default snaps it back on.
     */
    private function defaultProviderCheckbox(string $key): Checkbox
    {
        return Checkbox::make("provider_default_{$key}")
            ->label('Default provider')
            ->helperText('Use this provider for the chat widget, essay grading, and AI question generation.')
            ->dehydrated(false)
            ->live()
            ->afterStateUpdated(function (?bool $state) use ($key): void {
                if ($state) {
                    $this->selectDefaultProvider($key);

                    return;
                }

                if (($this->data['ai_provider'] ?? null) === $key) {
                    $this->data["provider_default_{$key}"] = true;
                }
            });
    }

    /**
     * Keep the existing provider-card checkboxes and dynamic provider cards
     * in radio-button sync.
     */
    public function updatedData(mixed $value, string $key): void
    {
        if (preg_match('/^openai_compatible_providers\\.([^.]*)\\.is_default$/', $key, $matches) !== 1) {
            return;
        }

        $provider = data_get($this->data, "openai_compatible_providers.{$matches[1]}");

        if (! is_array($provider) || blank($provider['id'] ?? null)) {
            return;
        }

        $runtimeProvider = AiSdkProviderService::compatibleProviderNameForId((string) $provider['id']);

        if ($value) {
            $this->selectDefaultProvider($runtimeProvider);

            return;
        }

        if (($this->data['ai_provider'] ?? null) === $runtimeProvider) {
            data_set($this->data, "openai_compatible_providers.{$matches[1]}.is_default", true);
        }
    }

    private function selectDefaultProvider(string $provider): void
    {
        $this->data['ai_provider'] = $provider;

        foreach (array_keys($this->defaultableProviders()) as $other) {
            $this->data["provider_default_{$other}"] = $other === $provider;
        }

        foreach ((array) ($this->data['openai_compatible_providers'] ?? []) as $itemKey => $compatible) {
            if (! is_array($compatible) || blank($compatible['id'] ?? null)) {
                continue;
            }

            data_set(
                $this->data,
                "openai_compatible_providers.{$itemKey}.is_default",
                AiSdkProviderService::compatibleProviderNameForId((string) $compatible['id']) === $provider,
            );
        }
    }

    /**
     * A collapsible provider card with the "Default provider" checkbox on
     * top. Only the current default's card starts expanded.
     *
     * @param  array<int, Component>  $fields
     */
    private function providerSection(string $key, string $label, string $description, array $fields): Section
    {
        return Section::make($label)
            ->description(fn (Get $get): string => ($get('ai_provider') === $key ? '✔ Default — ' : '').$description)
            ->collapsible()
            ->collapsed(fn (Get $get): bool => $get('ai_provider') !== $key)
            ->schema([
                $this->defaultProviderCheckbox($key),
                ...$fields,
            ]);
    }

    /**
     * A standard Laravel AI SDK provider card: API key, optional base URL,
     * and a model input that falls back to the service default.
     */
    private function sdkProviderCard(string $key, string $label): Section
    {
        $meta = $this->sdkProviderFields()[$key];
        $defaultModel = AiSdkProviderService::DEFAULT_MODELS[$key];

        $fields = [
            TextInput::make("{$key}_api_key")
                ->label('API Key')
                ->password()
                ->revealable()
                ->helperText("{$meta['help']} Falls back to the {$meta['env']} env var if left empty."),
        ];

        if ($meta['url'] !== null) {
            $fields[] = TextInput::make("{$key}_url")
                ->label('Base URL')
                ->placeholder($meta['url'])
                ->helperText('Leave empty to use the default endpoint.');
        }

        $fields[] = TextInput::make("{$key}_model")
            ->label('Model')
            ->placeholder($defaultModel)
            ->helperText("Used for chat, grading, and question generation. Leave empty to use {$defaultModel}.");

        return $this->providerSection($key, $label, 'Text via the Laravel AI SDK.', [
            Grid::make(2)->schema($fields),
        ]);
    }

    /**
     * The AI Providers section: every Laravel AI SDK provider gets its own
     * collapsible card with credentials, and exactly one card is checked as
     * the default via the hidden `ai_provider` state.
     */
    private function aiProviderSection(): Section
    {
        return Section::make('AI Provider Configuration')
            ->description('Every provider the Laravel AI SDK supports, ready to configure. Check "Default provider" on the card used for the chat widget, essay grading, and AI question generation.')
            ->schema([
                Hidden::make('ai_provider'),

                $this->providerSection('gemini', 'Gemini (Google)', 'Text, images, embeddings, and files via the Laravel AI SDK.', [
                    Grid::make(2)->schema([
                        TextInput::make('gemini_api_key')
                            ->label('Gemini API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('Paste your Google AI Studio API key')
                            ->helperText('Create one for free at aistudio.google.com. Falls back to the GEMINI_API_KEY env var if left empty.'),

                        Select::make('gemini_chat_model')
                            ->label('Gemini Chat Model')
                            ->options([
                                'gemini-3.5-flash' => 'Gemini 3.5 Flash (recommended)',
                                'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite (faster)',
                                'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash-Lite (cheapest)',
                                'gemini-2.5-pro' => 'Gemini 2.5 Pro (most capable)',
                            ])
                            ->default('gemini-3.5-flash')
                            ->helperText('Used by the floating chat widget.'),

                        Select::make('gemini_grading_model')
                            ->label('Gemini Grading Model')
                            ->options([
                                'gemini-3.5-flash' => 'Gemini 3.5 Flash (recommended)',
                                'gemini-3.5-flash-lite' => 'Gemini 3.5 Flash-Lite (faster)',
                                'gemini-3.1-flash-lite' => 'Gemini 3.1 Flash-Lite (cheapest)',
                                'gemini-2.5-pro' => 'Gemini 2.5 Pro (most accurate)',
                            ])
                            ->default('gemini-3.5-flash')
                            ->helperText('Used for essay grading and AI question/source generation.'),
                    ]),
                ]),

                $this->sdkProviderCard('openai', 'OpenAI'),
                $this->sdkProviderCard('anthropic', 'Anthropic (Claude)'),

                $this->providerSection('groq', 'Groq', 'Text via the Laravel AI SDK. Free tier: 14,400 requests/day.', [
                    Grid::make(2)->schema([
                        TextInput::make('groq_api_key')
                            ->label('Groq API Key')
                            ->password()
                            ->revealable()
                            ->placeholder('Your Groq API Key (free at console.groq.com)')
                            ->helperText('Falls back to the GROQ_API_KEY env var if left empty.'),

                        Select::make('groq_model')
                            ->label('Groq Model')
                            ->options([
                                'llama-3.1-8b-instant' => 'Llama 3.1 8B Instant (fastest)',
                                'llama-3.1-70b-versatile' => 'Llama 3.1 70B Versatile',
                                'llama-3.3-70b-versatile' => 'Llama 3.3 70B Versatile',
                                'mixtral-8x7b-32768' => 'Mixtral 8x7B',
                                'gemma-2-9b-it' => 'Gemma 2 9B',
                            ])
                            ->default('llama-3.1-8b-instant')
                            ->helperText('Llama 3.1 8B Instant is ultra-fast. Use 70B for complex tasks.'),
                    ]),
                ]),

                $this->sdkProviderCard('mistral', 'Mistral'),
                $this->sdkProviderCard('deepseek', 'DeepSeek'),
                $this->sdkProviderCard('xai', 'xAI (Grok)'),
                $this->sdkProviderCard('openrouter', 'OpenRouter'),
                $this->providerSection('azure', 'Azure OpenAI', 'Text and embeddings via the Laravel AI SDK. Prompts against deployment names.', [
                    Grid::make(2)->schema([
                        TextInput::make('azure_api_key')
                            ->label('Azure OpenAI API Key')
                            ->password()
                            ->revealable()
                            ->helperText('Falls back to the AZURE_OPENAI_API_KEY env var if left empty.'),

                        TextInput::make('azure_url')
                            ->label('Endpoint URL')
                            ->placeholder('https://your-resource.openai.azure.com')
                            ->helperText('Falls back to the AZURE_OPENAI_URL env var if left empty.'),

                        TextInput::make('azure_api_version')
                            ->label('API Version')
                            ->placeholder('2024-10-21')
                            ->helperText('Leave empty to use the default API version.'),

                        TextInput::make('azure_deployment')
                            ->label('Chat Deployment')
                            ->placeholder('gpt-4o')
                            ->helperText('Deployment used for chat, grading, and question generation.'),

                        TextInput::make('azure_embedding_deployment')
                            ->label('Embedding Deployment')
                            ->placeholder('text-embedding-3-small')
                            ->helperText('Deployment used for embeddings.'),
                    ]),
                ]),

                $this->providerSection('ollama', 'Ollama (Local)', 'Local text models via the Laravel AI SDK — no API key required.', [
                    Grid::make(2)->schema([
                        TextInput::make('ollama_url')
                            ->label('Ollama URL')
                            ->placeholder('http://localhost:11434')
                            ->default('http://localhost:11434')
                            ->helperText('URL of your local Ollama instance. Also used by the fallback below.'),

                        TextInput::make('ollama_model')
                            ->label('Ollama Model')
                            ->placeholder('llama3.2:1b')
                            ->default('llama3.2:1b')
                            ->helperText('Model to use (e.g., llama3.2:1b, llama3.1:8b).'),
                    ]),
                ]),

                Section::make('OpenAI-Compatible Providers')
                    ->description('Add hosted APIs, local gateways, or self-hosted models that implement the OpenAI Chat Completions API. The API key is optional; use custom headers for gateways with another authentication scheme.')
                    ->collapsible()
                    ->schema([
                        Repeater::make('openai_compatible_providers')
                            ->label('Providers')
                            ->addActionLabel('Add OpenAI-Compatible Provider')
                            ->defaultItems(0)
                            ->collapsible()
                            ->itemLabel(fn (array $state): ?string => filled($state['name'] ?? null) ? $state['name'] : 'New provider')
                            ->deleteAction(fn (Action $action): Action => $action->requiresConfirmation())
                            ->schema([
                                Hidden::make('id')
                                    ->default(fn (): string => (string) Str::uuid())
                                    ->required()
                                    ->rules(['uuid']),

                                Checkbox::make('is_default')
                                    ->label('Default provider')
                                    ->helperText('Use this provider for chat, essay grading, and AI question generation.')
                                    ->dehydrated(false)
                                    ->live(),

                                Grid::make(2)->schema([
                                    TextInput::make('name')
                                        ->label('Provider Name')
                                        ->required()
                                        ->maxLength(80)
                                        ->distinct()
                                        ->helperText('A descriptive label shown in AI provider pickers.'),

                                    TextInput::make('model')
                                        ->label('Model')
                                        ->required()
                                        ->maxLength(160)
                                        ->helperText('The text model used for chat, grading, and question generation.'),

                                    TextInput::make('url')
                                        ->label('Base URL')
                                        ->required()
                                        ->url()
                                        ->rule('regex:/^https?:\\/\\//i')
                                        ->maxLength(2048)
                                        ->placeholder('https://gateway.example.com/v1')
                                        ->helperText('Include the API version prefix when your gateway requires one.'),

                                    TextInput::make('api_key')
                                        ->label('Bearer API Key (optional)')
                                        ->password()
                                        ->revealable()
                                        ->helperText('Sent as a Bearer token unless an Authorization header below overrides it.'),
                                ]),

                                Repeater::make('headers')
                                    ->label('Custom Request Headers')
                                    ->addActionLabel('Add Header')
                                    ->defaultItems(0)
                                    ->columns(2)
                                    ->schema([
                                        TextInput::make('name')
                                            ->label('Header Name')
                                            ->required()
                                            ->maxLength(255)
                                            ->distinct(),
                                        TextInput::make('value')
                                            ->label('Header Value')
                                            ->required()
                                            ->password()
                                            ->revealable()
                                            ->maxLength(2048),
                                    ]),
                            ]),
                    ]),

                $this->providerSection('cloudflare', 'Cloudflare Workers AI', 'Custom integration — not part of the Laravel AI SDK.', [
                    Grid::make(2)->schema([
                        TextInput::make('cloudflare_account_id')
                            ->label('Cloudflare Account ID')
                            ->placeholder('Your Cloudflare Account ID'),

                        TextInput::make('cloudflare_api_token')
                            ->label('Cloudflare API Token')
                            ->password()
                            ->placeholder('Your Workers AI API Token'),

                        Select::make('cloudflare_model')
                            ->label('AI Chat Model')
                            ->options([
                                '@cf/zai-org/glm-4.7-flash' => 'GLM 4.7 Flash (fast, great for chat)',
                                '@cf/meta/llama-3.1-8b-instruct' => 'Llama 3.1 8B (recommended)',
                                '@cf/meta/llama-3.1-8b-instruct-fast' => 'Llama 3.1 8B Fast (faster)',
                                '@cf/meta/llama-3-8b-instruct' => 'Llama 3 8B',
                                '@cf/meta/llama-3.2-1b-instruct' => 'Llama 3.2 1B (Ultra Fast)',
                            ])
                            ->default('@cf/zai-org/glm-4.7-flash')
                            ->helperText('Used by the floating chat widget.'),

                        Select::make('cloudflare_grading_model')
                            ->label('AI Grading Model')
                            ->options([
                                '@cf/meta/llama-3.1-8b-instruct' => 'Llama 3.1 8B (most accurate)',
                                '@cf/meta/llama-3.1-8b-instruct-fp8-fast' => 'Llama 3.1 8B FP8 Fast (~5x cheaper, near-identical quality)',
                                '@cf/meta/llama-3-8b-instruct' => 'Llama 3 8B',
                                '@cf/meta/llama-3.2-1b-instruct' => 'Llama 3.2 1B (Ultra Fast, cheapest)',
                            ])
                            ->default('@cf/meta/llama-3.1-8b-instruct')
                            ->helperText('Used for essay grading and AI question/source generation. FP8 Fast is the cost/quality sweet spot.'),
                    ]),
                ]),

                Section::make('Specialized Providers')
                    ->description('These Laravel AI SDK providers offer embeddings, reranking, or audio only — no text generation — so they can never be the default. Their keys are stored for SDK features that use them.')
                    ->collapsible()
                    ->collapsed()
                    ->schema([
                        Grid::make(2)->schema([
                            TextInput::make('cohere_api_key')
                                ->label('Cohere API Key')
                                ->password()
                                ->revealable()
                                ->helperText('Embeddings and reranking. Falls back to the COHERE_API_KEY env var if left empty.'),

                            TextInput::make('jina_api_key')
                                ->label('Jina API Key')
                                ->password()
                                ->revealable()
                                ->helperText('Embeddings and reranking. Falls back to the JINA_API_KEY env var if left empty.'),

                            TextInput::make('voyageai_api_key')
                                ->label('VoyageAI API Key')
                                ->password()
                                ->revealable()
                                ->helperText('Embeddings. Falls back to the VOYAGEAI_API_KEY env var if left empty.'),

                            TextInput::make('eleven_api_key')
                                ->label('ElevenLabs API Key')
                                ->password()
                                ->revealable()
                                ->helperText('Text-to-speech and transcription. Falls back to the ELEVENLABS_API_KEY env var if left empty.'),
                        ]),
                    ]),

            ]);
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            if (
                ($data['ai_fallback_mode'] ?? 'disabled') !== 'disabled'
                && ($data['ai_fallback_provider'] ?? null) === ($data['ai_provider'] ?? 'gemini')
            ) {
                throw ValidationException::withMessages([
                    'data.ai_fallback_provider' => 'Choose a fallback provider different from the default provider.',
                ]);
            }

            Setting::set('ai_chat_enabled', ($data['ai_chat_enabled'] ?? true) ? '1' : '0');
            if (isset($data['ai_chat_maintenance_message'])) {
                Setting::set('ai_chat_maintenance_message', $data['ai_chat_maintenance_message']);
            }

            Setting::set('ai_budget_enabled', ($data['ai_budget_enabled'] ?? false) ? '1' : '0');
            Setting::set('ai_budget_daily_tokens', (string) max(0, (int) ($data['ai_budget_daily_tokens'] ?? 0)));
            Setting::set('ai_budget_monthly_tokens', (string) max(0, (int) ($data['ai_budget_monthly_tokens'] ?? 0)));
            Setting::set('ai_budget_daily_cost', (string) min(1000000, max(0, (float) ($data['ai_budget_daily_cost'] ?? 0))));
            Setting::set('ai_budget_monthly_cost', (string) min(1000000, max(0, (float) ($data['ai_budget_monthly_cost'] ?? 0))));
            Setting::set('ai_budget_warning_percent', (string) min(100, max(50, (int) ($data['ai_budget_warning_percent'] ?? 80))));

            $fallbackMode = in_array(($data['ai_fallback_mode'] ?? null), [
                'disabled',
                'provider_failure',
                'provider_failure_or_budget',
            ], true) ? $data['ai_fallback_mode'] : 'disabled';
            $fallbackProvider = (string) ($data['ai_fallback_provider'] ?? 'ollama');
            Setting::set('ai_fallback_mode', $fallbackMode);
            Setting::set('ai_fallback_provider', $fallbackProvider);
            // Preserve compatibility with older code/config while every AI
            // call site migrates to the explicit fallback policy.
            Setting::set(
                'ollama_enabled',
                $fallbackMode !== 'disabled' && $fallbackProvider === 'ollama' ? '1' : '0',
            );

            $costRates = collect((array) ($data['ai_budget_cost_rates'] ?? []))
                ->filter(fn (mixed $rate): bool => is_array($rate) && filled($rate['provider'] ?? null))
                ->map(fn (array $rate): array => [
                    'provider' => (string) $rate['provider'],
                    'model' => trim((string) ($rate['model'] ?? '')),
                    'input' => min(100000, max(0, (float) ($rate['input'] ?? 0))),
                    'output' => min(100000, max(0, (float) ($rate['output'] ?? 0))),
                ])
                ->values()
                ->all();
            Setting::set('ai_budget_cost_rates', json_encode($costRates, JSON_THROW_ON_ERROR));

            $compatibleProviders = collect((array) ($data['openai_compatible_providers'] ?? []))
                ->filter(fn (mixed $provider): bool => is_array($provider))
                ->map(function (array $provider): array {
                    $headers = collect((array) ($provider['headers'] ?? []))
                        ->filter(fn (mixed $header): bool => is_array($header))
                        ->map(fn (array $header): array => [
                            'name' => trim((string) ($header['name'] ?? '')),
                            'value' => trim((string) ($header['value'] ?? '')),
                        ])
                        ->filter(fn (array $header): bool => $header['name'] !== '' && $header['value'] !== '')
                        ->values()
                        ->all();

                    return [
                        'id' => (string) $provider['id'],
                        'name' => trim((string) $provider['name']),
                        'url' => trim((string) $provider['url']),
                        'model' => trim((string) $provider['model']),
                        'api_key' => filled($provider['api_key'] ?? null) ? (string) $provider['api_key'] : null,
                        'headers' => $headers,
                    ];
                })
                ->values()
                ->all();

            $defaultableProviders = AiSdkProviderService::TEXT_PROVIDER_LABELS + collect($compatibleProviders)
                ->mapWithKeys(fn (array $provider): array => [
                    AiSdkProviderService::compatibleProviderNameForId($provider['id']) => $provider['name'],
                ])
                ->all();

            // The hidden ai_provider state is the single source of truth for
            // the default provider. Guard against stale/unknown values (e.g.
            // a provider that was removed or can never serve text).
            $provider = $data['ai_provider'] ?? 'gemini';

            if (! array_key_exists($provider, $defaultableProviders)) {
                $provider = 'gemini';
            }

            Setting::set('ai_provider', $provider);
            Setting::set(
                AiSdkProviderService::OPENAI_COMPATIBLE_SETTINGS_KEY,
                json_encode($compatibleProviders, JSON_THROW_ON_ERROR),
            );
            Setting::set('gemini_api_key', $data['gemini_api_key'] ?? null);
            Setting::set('gemini_chat_model', $data['gemini_chat_model'] ?? 'gemini-3.5-flash');
            Setting::set('gemini_grading_model', $data['gemini_grading_model'] ?? 'gemini-3.5-flash');
            Setting::set('cloudflare_account_id', $data['cloudflare_account_id'] ?? null);
            Setting::set('cloudflare_api_token', $data['cloudflare_api_token'] ?? null);
            Setting::set('cloudflare_model', $data['cloudflare_model'] ?? '@cf/zai-org/glm-4.7-flash');
            Setting::set('cloudflare_grading_model', $data['cloudflare_grading_model'] ?? '@cf/meta/llama-3.1-8b-instruct');
            Setting::set('groq_api_key', $data['groq_api_key'] ?? null);
            Setting::set('groq_model', $data['groq_model'] ?? 'llama-3.1-8b-instant');
            Setting::set('ollama_url', $data['ollama_url'] ?? 'http://localhost:11434');
            Setting::set('ollama_model', $data['ollama_model'] ?? 'llama3.2:1b');

            // Laravel AI SDK provider credentials and models. Empty values
            // are stored as null so the runtime falls back to the env vars.
            foreach ([
                'openai_api_key', 'openai_url', 'openai_model',
                'anthropic_api_key', 'anthropic_url', 'anthropic_model',
                'mistral_api_key', 'mistral_url', 'mistral_model',
                'deepseek_api_key', 'deepseek_model',
                'xai_api_key', 'xai_url', 'xai_model',
                'openrouter_api_key', 'openrouter_model',
                'azure_api_key', 'azure_url', 'azure_api_version',
                'azure_deployment', 'azure_embedding_deployment',
                'cohere_api_key', 'jina_api_key', 'voyageai_api_key', 'eleven_api_key',
            ] as $sdkSetting) {
                Setting::set($sdkSetting, $data[$sdkSetting] ?? null);
            }

            Setting::set('login_enabled', ($data['login_enabled'] ?? true) ? '1' : '0');
            if (isset($data['login_disabled_message'])) {
                Setting::set('login_disabled_message', $data['login_disabled_message']);
            }

            Setting::set('registration_enabled', ($data['registration_enabled'] ?? true) ? '1' : '0');
            if (isset($data['registration_disabled_message'])) {
                Setting::set('registration_disabled_message', $data['registration_disabled_message']);
            }

            Setting::set('daily_claim_enabled', ($data['daily_claim_enabled'] ?? true) ? '1' : '0');
            Setting::set('daily_claim_base_xp', (string) max(1, (int) ($data['daily_claim_base_xp'] ?? 1)));

            Setting::set('welcome_demo_video_path', $data['welcome_demo_video_path'] ?? null);
            Setting::set('school_name', $data['school_name'] ?? 'LSI Engine');
            Setting::set('school_tagline', $data['school_tagline'] ?? 'Learning Systems Intelligence');
            Setting::set('school_logo_path', $data['school_logo_path'] ?? null);
            Setting::set('school_accent_color', $data['school_accent_color'] ?? '#f59e0b');

            Notification::make()
                ->title('Settings saved successfully!')
                ->success()
                ->send();
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Exception $e) {
            Notification::make()
                ->title('Error saving settings')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label(__('filament-panels::resources/pages/edit-record.form.actions.save.label'))
                ->submit('save'),
        ];
    }
}
