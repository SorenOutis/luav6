<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSet>
 */
class ExamSetFactory extends Factory
{
    protected $model = ExamSet::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            // A blank title is named by the model in rotation order:
            // Set A, Set B, … — mirroring what the admin panel does.
            'title' => null,
        ];
    }

    public function forExam(Exam $exam): static
    {
        return $this->state(fn (array $attributes): array => ['exam_id' => $exam->id]);
    }

    public function titled(string $title): static
    {
        return $this->state(fn (array $attributes): array => ['title' => $title]);
    }
}
