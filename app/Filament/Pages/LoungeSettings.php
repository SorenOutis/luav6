<?php

namespace App\Filament\Pages;

use App\Support\CommunitySettings;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use UnitEnum;

class LoungeSettings extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return (bool) $user?->is_admin;
    }

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Community Lounge Settings';

    protected static ?string $navigationLabel = 'Lounge Settings';

    protected static ?string $slug = 'community-settings';

    protected string $view = 'filament.pages.lounge-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(CommunitySettings::formState());
    }

    public function form(Schema $form): Schema
    {
        return $form
            ->schema([
                Section::make('Post & Feed Visibility')
                    ->description('Configure whether student posts in the Community Lounge are shared across the workspace feed or strictly confined to each student’s section.')
                    ->schema([
                        Radio::make('feed_scope')
                            ->label('Visibility Scope')
                            ->options([
                                CommunitySettings::SCOPE_WORKSPACE => 'Entire Workspace Feed (Cross-section feed enabled)',
                                CommunitySettings::SCOPE_SECTION => 'Section Only (Confined to student’s enrolled section)',
                            ])
                            ->descriptions([
                                CommunitySettings::SCOPE_WORKSPACE => 'Students see posts from all sections in the workspace. Privacy protection automatically masks names and avatars for students outside their section.',
                                CommunitySettings::SCOPE_SECTION => 'Students can only see, publish, and interact with posts and stories from classmates in their own section. Cross-section posts are completely hidden.',
                            ])
                            ->required(),
                    ]),

                Section::make('Interaction & Privacy Controls')
                    ->description('Fine-tune student participation and lounge posting capabilities.')
                    ->schema([
                        Toggle::make('allow_anonymous')
                            ->label('Allow Anonymous Posting')
                            ->helperText('Allows students to check “Post anonymously” to conceal their identity while participating in the lounge.'),

                        Toggle::make('stories_enabled')
                            ->label('Enable 24-Hour Stories (MyDay)')
                            ->helperText('Allows students to share ephemeral photos and video stories to the top stories tray in the lounge.'),

                        Toggle::make('music_enabled')
                            ->label('Enable Background Music (Soundtracks)')
                            ->helperText('Allows students to attach background music soundtracks to their posts and browse the soundtrack library.'),

                        Toggle::make('media_uploads_enabled')
                            ->label('Enable Photo & Video Uploads')
                            ->helperText('Allows students to attach photos and videos to posts and open the media creator modal.'),

                        Toggle::make('file_attachments_enabled')
                            ->label('Enable Document & Code Attachments')
                            ->helperText('Allows students to attach documents, text files, and code snippets to their posts.'),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $data = $this->form->getState();

        CommunitySettings::save($data);

        Notification::make()
            ->title('Lounge settings saved successfully')
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
