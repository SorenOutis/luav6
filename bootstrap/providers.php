<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\FortifyServiceProvider;
use Libsql\Laravel\LibsqlServiceProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    FortifyServiceProvider::class,
    ...(env('DB_CONNECTION') === 'libsql' ? [LibsqlServiceProvider::class] : []),
];
