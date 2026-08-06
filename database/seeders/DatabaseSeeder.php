<?php

namespace Database\Seeders;

use App\Models\Section;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $admin = User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'first_name' => 'Admin',
                'last_name' => 'User',
                'password' => Hash::make('password'),
                'is_admin' => true,
                'is_super_admin' => true,
            ]
        );

        // Seed sections under this admin's workspace
        Section::firstOrCreate(
            ['name' => 'Section A'],
            ['admin_id' => $admin->id]
        );
        Section::firstOrCreate(
            ['name' => 'Section B'],
            ['admin_id' => $admin->id]
        );
        Section::firstOrCreate(
            ['name' => 'Section C'],
            ['admin_id' => $admin->id]
        );

        $this->call(BadgeSeeder::class);
        $this->call(TowerDefenseSeeder::class);
        $this->call(CourseSeeder::class);
        $this->call(CourseUserPivotSeeder::class);
        $this->call(LessonProgressSeeder::class);
    }
}
