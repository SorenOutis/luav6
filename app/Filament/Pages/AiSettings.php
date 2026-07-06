<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class AiSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-cpu-chip';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Platform Settings';

    protected static ?string $navigationLabel = 'Platform Settings';

    protected string $view = 'filament.pages.ai-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'ai_chat_enabled' => (bool) Setting::get('ai_chat_enabled', true),
            'ai_chat_maintenance_message' => Setting::get('ai_chat_maintenance_message', 'The AI service is currently under maintenance. Please try again later.'),
            'ai_provider' => Setting::get('ai_provider', 'gemini'),
            'cloudflare_account_id' => Setting::get('cloudflare_account_id'),
            'cloudflare_api_token' => Setting::get('cloudflare_api_token'),
            'cloudflare_model' => Setting::get('cloudflare_model', '@cf/meta/llama-3.1-8b-instruct'),
            'groq_api_key' => Setting::get('groq_api_key'),
            'groq_model' => Setting::get('groq_model', 'llama-3.1-8b-instant'),
            'ollama_url' => Setting::get('ollama_url', 'http://localhost:11434'),
            'ollama_model' => Setting::get('ollama_model', 'llama3.2:1b'),
            'ollama_enabled' => (bool) Setting::get('ollama_enabled', false),
            'login_enabled' => (bool) Setting::get('login_enabled', true),
            'login_disabled_message' => Setting::get('login_disabled_message', 'Login is currently disabled. Please try again later.'),
            'registration_enabled' => (bool) Setting::get('registration_enabled', true),
            'registration_disabled_message' => Setting::get('registration_disabled_message', 'Registration is currently disabled. Please try again later.'),
            'welcome_demo_video_path' => Setting::get('welcome_demo_video_path'),
            'school_name' => Setting::get('school_name', 'LSI Engine'),
            'school_tagline' => Setting::get('school_tagline', 'Learning Systems Intelligence'),
            'school_logo_path' => Setting::get('school_logo_path'),
            'school_accent_color' => Setting::get('school_accent_color', '#f59e0b'),
        ]);
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

                Section::make('AI Chat Configuration')
                    ->description('Manage the availability of the AI floating widget.')
                    ->schema([
                        Toggle::make('ai_chat_enabled')
                            ->label('Enable AI Chat Widget')
                            ->helperText('If disabled, the floating widget will show a maintenance message and prevent chatting.')
                            ->reactive(),

                        Select::make('ai_provider')
                            ->label('AI Provider')
                            ->options([
                                'gemini' => 'Gemini (Google)',
                                'cloudflare' => 'Cloudflare Workers AI',
                                'groq' => 'Groq (Free - 14,400 req/day)',
                            ])
                            ->default('gemini')
                            ->required()
                            ->helperText('Select the AI provider to use for the chat widget. Groq is recommended for exam grading.')
                            ->visible(fn ($get) => $get('ai_chat_enabled')),

                        Grid::make(2)
                            ->schema([
                                TextInput::make('cloudflare_account_id')
                                    ->label('Cloudflare Account ID')
                                    ->placeholder('Your Cloudflare Account ID')
                                    ->visible(fn ($get) => $get('ai_provider') === 'cloudflare' && $get('ai_chat_enabled')),

                                TextInput::make('cloudflare_api_token')
                                    ->label('Cloudflare API Token')
                                    ->password()
                                    ->placeholder('Your Workers AI API Token')
                                    ->visible(fn ($get) => $get('ai_provider') === 'cloudflare' && $get('ai_chat_enabled')),
                            ]),

                        Select::make('cloudflare_model')
                            ->label('Cloudflare Model')
                            ->options([
                                '@cf/meta/llama-3.1-8b-instruct' => 'Llama 3.1 8B (recommended)',
                                '@cf/meta/llama-3.1-8b-instruct-fast' => 'Llama 3.1 8B Fast (faster)',
                                '@cf/meta/llama-3-8b-instruct' => 'Llama 3 8B',
                                '@cf/meta/llama-3.1-1b-instruct' => 'Llama 3.1 1B (Ultra Fast)',
                            ])
                            ->default('@cf/meta/llama-3.1-8b-instruct')
                            ->helperText('Llama 3.1 models are stable and well-tested. Use 1B for ultra-fast responses.')
                            ->visible(fn ($get) => $get('ai_provider') === 'cloudflare' && $get('ai_chat_enabled')),

                        TextInput::make('groq_api_key')
                            ->label('Groq API Key')
                            ->password()
                            ->placeholder('Your Groq API Key (free at console.groq.com)')
                            ->visible(fn ($get) => $get('ai_provider') === 'groq' && $get('ai_chat_enabled')),

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
                            ->helperText('Llama 3.1 8B Instant is ultra-fast. Use 70B for complex tasks.')
                            ->visible(fn ($get) => $get('ai_provider') === 'groq' && $get('ai_chat_enabled')),

                        Section::make('Ollama Fallback Configuration')
                            ->description('Configure local Ollama as a fallback when the primary provider fails.')
                            ->schema([
                                TextInput::make('ollama_url')
                                    ->label('Ollama URL')
                                    ->placeholder('http://localhost:11434')
                                    ->default('http://localhost:11434')
                                    ->helperText('URL of your local Ollama instance.'),

                                TextInput::make('ollama_model')
                                    ->label('Ollama Model')
                                    ->placeholder('llama3.2:1b')
                                    ->default('llama3.2:1b')
                                    ->helperText('Model to use for Ollama fallback (e.g., llama3.2:1b, llama3.1:8b).'),

                                Toggle::make('ollama_enabled')
                                    ->label('Enable Ollama Fallback')
                                    ->default(false)
                                    ->helperText('When enabled, Ollama will be used if the primary provider fails.'),
                            ])
                            ->visible(fn ($get) => $get('ai_chat_enabled')),

                        Textarea::make('ai_chat_maintenance_message')
                            ->label('Maintenance Message')
                            ->placeholder('Enter the message to display when the AI is disabled...')
                            ->required()
                            ->visible(fn ($get) => ! $get('ai_chat_enabled')),
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

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            Setting::set('ai_chat_enabled', ($data['ai_chat_enabled'] ?? true) ? '1' : '0');
            if (isset($data['ai_chat_maintenance_message'])) {
                Setting::set('ai_chat_maintenance_message', $data['ai_chat_maintenance_message']);
            }

            Setting::set('ai_provider', $data['ai_provider'] ?? 'gemini');
            Setting::set('cloudflare_account_id', $data['cloudflare_account_id'] ?? null);
            Setting::set('cloudflare_api_token', $data['cloudflare_api_token'] ?? null);
            Setting::set('cloudflare_model', $data['cloudflare_model'] ?? '@cf/meta/llama-3.1-8b-instruct');
            Setting::set('groq_api_key', $data['groq_api_key'] ?? null);
            Setting::set('groq_model', $data['groq_model'] ?? 'llama-3.1-8b-instant');
            Setting::set('ollama_url', $data['ollama_url'] ?? 'http://localhost:11434');
            Setting::set('ollama_model', $data['ollama_model'] ?? 'llama3.2:1b');
            Setting::set('ollama_enabled', ($data['ollama_enabled'] ?? false) ? '1' : '0');

            Setting::set('login_enabled', ($data['login_enabled'] ?? true) ? '1' : '0');
            if (isset($data['login_disabled_message'])) {
                Setting::set('login_disabled_message', $data['login_disabled_message']);
            }

            Setting::set('registration_enabled', ($data['registration_enabled'] ?? true) ? '1' : '0');
            if (isset($data['registration_disabled_message'])) {
                Setting::set('registration_disabled_message', $data['registration_disabled_message']);
            }

            Setting::set('welcome_demo_video_path', $data['welcome_demo_video_path'] ?? null);
            Setting::set('school_name', $data['school_name'] ?? 'LSI Engine');
            Setting::set('school_tagline', $data['school_tagline'] ?? 'Learning Systems Intelligence');
            Setting::set('school_logo_path', $data['school_logo_path'] ?? null);
            Setting::set('school_accent_color', $data['school_accent_color'] ?? '#f59e0b');

            Notification::make()
                ->title('Settings saved successfully!')
                ->success()
                ->send();
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
