<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        foreach (range(1, 100) as $level) {
            Badge::firstOrCreate(
                ['required_level' => $level],
                [
                    'name' => "Level {$level} Badge",
                    'description' => "Awarded for reaching Level {$level}.",
                    'image_path' => null,
                ]
            );
        }
    }
}
