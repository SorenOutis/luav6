<?php

namespace App\Filament\Resources\Badges\Pages;

use App\Filament\Resources\Badges\BadgeResource;
use App\Models\Badge;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListBadges extends ListRecords
{
    protected static string $resource = BadgeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('useBundledBadgeImages')
                ->label('Use shipped badge images')
                ->icon('heroicon-o-photo')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Link level badges to shipped images')
                ->modalDescription('This links existing level 1-100 badges to the pre-generated badge artwork shipped under public/images/badges. It does not create or modify any image files.')
                ->modalSubmitActionLabel('Link badges')
                ->action(function (): void {
                    $badges = Badge::query()
                        ->whereNotNull('required_level')
                        ->whereBetween('required_level', [1, 100])
                        ->get(['id', 'required_level']);

                    $updated = 0;

                    foreach ($badges as $badge) {
                        $badge->update([
                            'image_path' => sprintf('images/badges/level-%03d.svg', (int) $badge->required_level),
                        ]);

                        $updated++;
                    }

                    Notification::make()
                        ->title('Level badges linked')
                        ->body(sprintf('Linked %d badges to the shipped badge images.', $updated))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
