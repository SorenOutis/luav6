<?php

use App\Console\Commands\DispatchAiDailyReport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Check each minute so a missed tick catches up after the env-defined time.
// A unique report_date and atomic queue claims keep each local day idempotent.
Schedule::command(DispatchAiDailyReport::class, ['--no-interaction'])
    ->everyMinute()
    ->timezone(config('app.timezone'))
    ->withoutOverlapping(5)
    ->onOneServer();
