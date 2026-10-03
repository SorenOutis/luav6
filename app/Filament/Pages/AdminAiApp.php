<?php

namespace App\Filament\Pages;

use Filament\Facades\Filament;

class AdminAiApp extends AdminAiChat
{
    protected static ?string $slug = 'ai-assistant-app';

    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        return Filament::auth()->user()?->isSuperAdmin() ?? false;
    }
}
