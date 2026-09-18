<?php

namespace App\Filament\Resources\TowerDefense\TdLevels\Pages;

use App\Filament\Resources\TowerDefense\TdLevels\TdLevelResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditTdLevel extends EditRecord
{
    protected static string $resource = TdLevelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('playtest')
                ->label('Playtest')
                ->icon('heroicon-o-play')
                ->color('success')
                ->url(fn () => route('games.tower-defense.play', ['level' => $this->record->slug]))
                ->openUrlInNewTab(),
            DeleteAction::make(),
        ];
    }
}
