<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\FileUpload;
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
