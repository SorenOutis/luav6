<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\Concerns\SyncsSectionProgress;
use App\Filament\Resources\Users\UserResource;
use App\Filament\Support\ImpersonateUser;
use App\Services\AdminUserGamificationService;
use App\Services\TeacherXpAwardService;
use App\Support\WorkspaceContext;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditUser extends EditRecord
{
    use SyncsSectionProgress;

    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ImpersonateUser::action($this->getRecord()),
            Action::make('awardXp')
                ->label('Award XP')
                ->icon('heroicon-o-sparkles')
                ->color('success')
                ->modalHeading(fn (): string => 'Award XP to '.$this->getRecord()->name)
                ->form([
                    Select::make('section_id')
                        ->label('Section')
                        ->options(function () {
                            $query = $this->getRecord()->sections()->orderBy('name');
                            if (! auth()->user()?->isSuperAdmin()) {
                                $query->where('sections.workspace_id', app(WorkspaceContext::class)->id());
                            }

                            return $query->pluck('name', 'sections.id');
                        })
                        ->required(),
                    TextInput::make('amount')
                        ->label('XP amount')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->required(),
                    Textarea::make('reason')
                        ->label('Reason')
                        ->placeholder('e.g. Excellent class participation')
                        ->maxLength(255)
                        ->required(),
                ])
                ->action(function (array $data): void {
                    app(TeacherXpAwardService::class)->award(
                        $this->getRecord(),
                        (int) $data['section_id'],
                        (int) $data['amount'],
                        (string) $data['reason'],
                    );

                    $this->record = $this->getRecord()->fresh(['sections', 'sectionProgress', 'currentSeasonProgress']);
                    $this->refreshFormData(['exp', 'level']);

                    Notification::make()
                        ->title($data['amount'].' XP awarded')
                        ->body((string) $data['reason'])
                        ->success()
                        ->send();
                }),
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $this->record->load(['sections', 'sectionProgress', 'currentSeasonProgress']);
        $data['section_progress_rows'] = app(AdminUserGamificationService::class)->rowsFor($this->record);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return $this->extractSectionProgressRows($data);
    }

    protected function afterSave(): void
    {
        $this->persistSectionProgress();
    }
}
