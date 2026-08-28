<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamSet;
use App\Models\ExamSetAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSetAssignment>
 *
 * Prefer forSet()+forUser() so the exam, the set and the student all line up.
 */
class ExamSetAssignmentFactory extends Factory
{
    protected $model = ExamSetAssignment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'exam_set_id' => ExamSet::factory(),
            'user_id' => User::factory(),
        ];
    }

    public function forSet(ExamSet $set): static
    {
        return $this->state(fn (array $attributes): array => [
            'exam_set_id' => $set->id,
            'exam_id' => $set->exam_id,
        ]);
    }

    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes): array => ['user_id' => $user->id]);
    }
}
