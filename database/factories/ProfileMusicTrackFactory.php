<?php

namespace Database\Factories;

use App\Models\ProfileMusicTrack;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileMusicTrack>
 */
class ProfileMusicTrackFactory extends Factory
{
    protected $model = ProfileMusicTrack::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->words(2, true),
            'artist' => fake()->name(),
            'audio_path' => 'profile-music/test-clip.wav',
            'duration_seconds' => 28.5,
            'cover_image_path' => null,
            'source_url' => 'https://ncs.io/track/test',
            'license_name' => 'NCS Usage Policy',
            'attribution_text' => 'Music provided by NoCopyrightSounds.',
            'is_active' => true,
            'is_global' => true,
        ];
    }

    public function workspaceOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_global' => false,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
