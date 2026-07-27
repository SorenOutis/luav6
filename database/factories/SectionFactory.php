<?php

namespace Database\Factories;

use App\Models\Season;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Section>
 */
class SectionFactory extends Factory
{
    protected $model = Section::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // NOTE: 'password' is intentionally omitted — it is not in Section::$fillable,
        // so setting it here would be silently discarded (and will throw once
        // preventSilentlyDiscardingAttributes is enabled in Phase 0.3).
        return [
            'name' => 'Section '.strtoupper(fake()->unique()->lexify('??')),
            'season_id' => null,
            'school_level' => Section::SCHOOL_LEVEL_SENIOR_HIGH,
            'join_code' => strtoupper(Str::random(8)),
        ];
    }

    /**
     * Attach the section to a specific season.
     */
    public function forSeason(Season $season): static
    {
        return $this->state(fn (array $attributes) => [
            'season_id' => $season->id,
        ]);
    }
}
