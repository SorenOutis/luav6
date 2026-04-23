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

        User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
            'password' => Hash::make('password'),
            'is_admin' => true,
        ]);

        Section::firstOrCreate(['name' => 'Section A']);
        Section::firstOrCreate(['name' => 'Section B']);
        Section::firstOrCreate(['name' => 'Section C']);

        $this->call(TowerDefenseSeeder::class);
    }
}
