<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
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

    protected static ?string $title = 'AI Chat Settings';

    protected static ?string $navigationLabel = 'AI Settings';

    protected string $view = 'filament.pages.ai-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'ai_chat_enabled' => (bool) Setting::get('ai_chat_enabled', true),
            'ai_chat_maintenance_message' => Setting::get('ai_chat_maintenance_message', 'KOA is currently under maintenance. Please try again later.'),
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
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        try {
            $data = $this->form->getState();

            Setting::set('ai_chat_enabled', $data['ai_chat_enabled'] ? '1' : '0');
            Setting::set('ai_chat_maintenance_message', $data['ai_chat_maintenance_message']);

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
