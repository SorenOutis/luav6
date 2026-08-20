<?php

namespace App\Filament\Support;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Illuminate\Database\Eloquent\Model;
use STS\FilamentImpersonate\Facades\Impersonation;

final class ImpersonateUser
{
    public static function action(?Model $record = null): Action
    {
        $action = Action::make('impersonate')
            ->label('Impersonate')
            ->icon('heroicon-o-finger-print')
            ->color('warning')
            ->visible(function (?Model $record): bool {
                $actor = auth()->user();

                return $actor instanceof User
                    && $record instanceof User
                    && $actor->canImpersonate()
                    && $record->canBeImpersonated();
            })
            ->action(function (Model $record) {
                $actor = auth()->user();

                if (! $actor instanceof User || ! $record instanceof User) {
                    return;
                }

                if (! $actor->canImpersonate() || ! $record->canBeImpersonated()) {
                    return;
                }

                session()->put('impersonate.back_to', UserResource::getUrl('index'));

                if (! Impersonation::enter($actor, $record, 'web')) {
                    return;
                }

                return redirect()->route('dashboard');
            });

        if ($record !== null) {
            $action->record($record);
        }

        return $action;
    }
}
