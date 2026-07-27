<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamSubmission>
 */
class ExamSubmissionFactory extends Factory
{
    protected $model = ExamSubmission::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'exam_id' => Exam::factory(),
            'exam_part_id' => ExamPart::factory(),
            'answers' => [],
            'status' => 'submitted',
            'score' => 0,
            'feedback' => null,
        ];
    }

    public function for(User $user, Exam $exam, ExamPart $part): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
            'exam_id' => $exam->id,
            'exam_part_id' => $part->id,
        ]);
    }

    public function pendingReview(): static
    {
        return $this->state(fn (array $attributes) => ['status' => 'pending_review']);
    }

    public function graded(float $score): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'graded',
            'score' => $score,
        ]);
    }
}
