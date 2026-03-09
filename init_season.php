<?php

use App\Models\Season;

require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

if (Season::count() === 0) {
    Season::create([
        'name' => 'Season 1',
        'start_date' => now(),
        'is_active' => true,
    ]);
    echo "Season 1 created successfully!\n";
} else {
    echo "Seasons already exist.\n";
    if (!Season::where('is_active', true)->exists()) {
        $season = Season::first();
        $season->update(['is_active' => true]);
        echo "Activated season: " . $season->name . "\n";
    }
}
