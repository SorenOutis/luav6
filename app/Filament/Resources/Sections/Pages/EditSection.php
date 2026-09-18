<?php

namespace App\Filament\Resources\Sections\Pages;

use App\Filament\Resources\Sections\SectionResource;
use App\Models\Section;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditSection extends EditRecord
{
    protected static string $resource = SectionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('regenerateCode')
                ->label('Regenerate Code')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Regenerate section join code?')
                ->modalDescription('Current students using this code will need the new code to join. Their existing enrollments will not be affected.')
                ->modalSubmitActionLabel('Yes, regenerate')
                ->action(function () {
                    $record = $this->getRecord();
                    $newCode = Section::generateUniqueJoinCode();
                    $record->update(['join_code' => $newCode]);

                    Notification::make()
                        ->title('Join code regenerated')
                        ->body('New code: '.Section::formatJoinCode($newCode))
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }
}
