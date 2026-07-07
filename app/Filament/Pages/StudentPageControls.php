<?php

namespace App\Filament\Pages;

use App\Support\StudentPageRegistry;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;

class StudentPageControls extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return ! ($user && $user->is_admin && ! $user->isSuperAdmin());
    }

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-eye-slash';

    protected static string|\UnitEnum|null $navigationGroup = 'Settings';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Student Page Controls';

    protected static ?string $navigationLabel = 'Student Pages';

    protected string $view = 'filament.pages.student-page-controls';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'pages' => StudentPageRegistry::controls(),
        ]);
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Student-facing pages')
                    ->description('Choose whether each student page is available, blurred, or temporarily turned off.')
                    ->schema($this->pageControlFields()),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        StudentPageRegistry::setControls($data['pages'] ?? []);

        Notification::make()
            ->title('Student page controls saved')
            ->success()
            ->send();
    }

    protected function getFormActions(): array
    {
        return [
            Action::make('save')
                ->label('Save Controls')
                ->submit('save'),
        ];
    }

    private function pageControlFields(): array
    {
        return collect(StudentPageRegistry::pages())
            ->map(fn (array $page, string $key) => Section::make($page['label'])
                ->description($page['description'])
                ->schema([
                    Select::make("pages.{$key}.mode")
                        ->label('Visibility')
                        ->options([
                            StudentPageRegistry::MODE_ENABLED => 'On',
                            StudentPageRegistry::MODE_BLURRED => 'Blurred',
                            StudentPageRegistry::MODE_DISABLED => 'Off',
                        ])
                        ->native(false)
                        ->required()
                        ->default(StudentPageRegistry::MODE_ENABLED)
                        ->helperText('Blurred keeps the page visible but covered. Off blocks student access.'),

                    Textarea::make("pages.{$key}.message")
                        ->label('Student message')
                        ->rows(2)
                        ->maxLength(240)
                        ->placeholder('Optional message shown when this page is blurred or off.'),
                ])
                ->columns(2))
            ->values()
            ->all();
    }
}
