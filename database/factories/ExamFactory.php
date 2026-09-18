<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamSet;
use App\Models\Section;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Exam>
 */
class ExamFactory extends Factory
{
    protected $model = Exam::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'description' => fake()->sentence(),
            'exam_date' => now()->addDay(),
            'duration_minutes' => 60,
            'xp_rewards_enabled' => true,
            'completion_xp' => 10,
            'on_time_xp' => 5,
            'accuracy_xp_enabled' => true,
            'status' => 'published',
            'section_id' => null,
            'admin_id' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'draft']);
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'published']);
    }

    /**
     * A closed exam. Students may review answer keys once an exam is closed.
     */
    public function closed(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'closed']);
    }

    public function forSection(Section $section): static
    {
        return $this->state(fn (array $attributes) => [
            'section_id' => $section->id,
        ]);
    }

    public function withDuration(int $minutes): static
    {
        return $this->state(fn (array $attributes) => [
            'duration_minutes' => $minutes,
        ]);
    }

    /**
     * Ship the exam as several interchangeable sets (Set A, Set B, …).
     *
     * Students are dealt one set each from a shuffled deck, so tests that
     * create parts afterwards must attach them to the set they mean — see
     * ExamPartFactory::forSet().
     */
    public function withSets(int $count = 2): static
    {
        return $this->afterCreating(function (Exam $exam) use ($count): void {
            foreach (range(0, max(1, $count) - 1) as $index) {
                ExamSet::factory()
                    ->forExam($exam)
                    ->titled(ExamSet::titleForIndex($index))
                    ->create();
            }
        });
    }
}
