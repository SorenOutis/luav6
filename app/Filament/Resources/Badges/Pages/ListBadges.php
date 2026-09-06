<?php

namespace App\Filament\Resources\Badges\Pages;

use App\Filament\Resources\Badges\BadgeResource;
use App\Models\Badge;
use App\Support\BadgeImageGenerator;
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
            Action::make('generateLevelBadges')
                ->label('Generate level badges')
                ->icon('heroicon-o-photo')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Generate level 1-100 badges')
                ->modalDescription('This writes the full level 1-100 SVG badge set to public/images/badges and links any matching level badges in this workspace to the new images.')
                ->modalSubmitActionLabel('Generate badges')
                ->action(function (): void {
                    $generated = BadgeImageGenerator::generateAll();

                    $updated = 0;
                    $badges = Badge::query()
                        ->whereNotNull('required_level')
                        ->whereBetween('required_level', [
                            BadgeImageGenerator::MIN_LEVEL,
                            BadgeImageGenerator::MAX_LEVEL,
                        ])
                        ->get(['id', 'required_level']);

                    foreach ($badges as $badge) {
                        $badge->update([
                            'image_path' => BadgeImageGenerator::filenameFor((int) $badge->required_level),
                        ]);

                        $updated++;
                    }

                    Notification::make()
                        ->title(sprintf('Generated %d badge images', $generated))
                        ->body(sprintf('Linked %d level badge records.', $updated))
                        ->success()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
