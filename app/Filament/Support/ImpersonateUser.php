<?php

namespace App\Filament\Support;

use App\Filament\Resources\Users\UserResource;
use Illuminate\Database\Eloquent\Model;
use STS\FilamentImpersonate\Actions\Impersonate;

final class ImpersonateUser
{
    public static function action(?Model $record = null): Impersonate
    {
        $action = Impersonate::make()
            ->label('Impersonate')
            ->icon('heroicon-o-finger-print')
            ->redirectTo(fn (): string => route('dashboard'))
            ->backTo(fn (): string => UserResource::getUrl('index'))
            ->withoutSpa();

        if ($record !== null) {
            $action->record($record);
        }

        return $action;
    }
}
