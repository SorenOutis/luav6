<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class Impersonation
{
    public const SESSION_KEY = 'impersonated_by';

    public const BACK_TO_KEY = 'impersonate.back_to';

    public static function isImpersonating(): bool
    {
        return session()->has(self::SESSION_KEY);
    }

    public static function enter(Authenticatable $from, Authenticatable $to): bool
    {
        session()->put(self::SESSION_KEY, $from->getAuthIdentifier());
        auth()->login($to);

        return true;
    }

    public static function leave(): bool
    {
        $id = session()->pull(self::SESSION_KEY);

        if ($id === null) {
            return false;
        }

        $impersonator = User::query()->find($id);

        if (! $impersonator) {
            return false;
        }

        auth()->login($impersonator);

        return true;
    }
}
