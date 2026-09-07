<?php

namespace App\Filament\Pages;

use App\Support\PlatformMaintenance;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class MaintenanceSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->isSuperAdmin();
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Maintenance Mode';

    protected static ?string $navigationLabel = 'Maintenance';

    protected string $view = 'filament.pages.maintenance-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(PlatformMaintenance::formState());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Platform maintenance')
                    ->description('When turned on, every student page (including /dashboard typed directly) shows the maintenance screen. Only super admins can still browse normally.')
                    ->schema([
                        Toggle::make('maintenance_enabled')
                            ->label('Maintenance mode')
                            ->helperText('If enabled, students and workspace admins see the maintenance page. Super admins bypass it.')
                            ->reactive(),

                        TextInput::make('maintenance_title')
                            ->label('Title')
                            ->placeholder(PlatformMaintenance::DEFAULT_TITLE)
                            ->required()
                            ->maxLength(120)
                            ->visible(fn ($get) => (bool) $get('maintenance_enabled'))
                            ->columnSpanFull(),

                        Textarea::make('maintenance_message')
                            ->label('Message')
                            ->placeholder(PlatformMaintenance::DEFAULT_MESSAGE)
                            ->required()
                            ->maxLength(500)
                            ->rows(3)
                            ->visible(fn ($get) => (bool) $get('maintenance_enabled'))
                            ->columnSpanFull(),

                        Select::make('maintenance_image')
                            ->label('Mascot image')
                            ->options(PlatformMaintenance::images())
                            ->native(false)
                            ->required()
                            ->visible(fn ($get) => (bool) $get('maintenance_enabled'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        PlatformMaintenance::save($data);

        Notification::make()
            ->title('Maintenance settings saved')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Settings')
                ->submit('save'),
        ];
    }
}
