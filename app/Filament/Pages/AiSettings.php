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
            'ai_chat_maintenance_message' => Setting::get('ai_chat_maintenance_message', 'KOA is currently under maintenance. Please try again later.'),
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

            Setting::set('ai_chat_enabled', $data['ai_chat_enabled'] ? '1' : '0');
            Setting::set('ai_chat_maintenance_message', $data['ai_chat_maintenance_message']);
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
