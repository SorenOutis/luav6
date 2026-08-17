<?php

namespace Database\Factories;

use App\Models\Exam;
use App\Models\ExamPart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ExamPart>
 *
 * Generates realistic `questions` JSON so grading tests exercise the real
 * shape that ExamController::submitPart() reads:
 *
 *   multiple_choice / true_false -> options[] with exactly one is_correct
 *   identification               -> correct_answer string
 *   essay                        -> graded by AIService, no key
 */
class ExamPartFactory extends Factory
{
    protected $model = ExamPart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'exam_id' => Exam::factory(),
            'title' => fake()->sentence(2),
            'instructions' => fake()->sentence(),
            'type' => 'multiple_choice',
            'sort_order' => 0,
            'points' => 1,
            'options' => null,
            'questions' => [],
        ];
    }

    public function forExam(Exam $exam): static
    {
        return $this->state(fn (array $attributes) => ['exam_id' => $exam->id]);
    }

    /**
     * @param  int  $count  Number of questions
     * @param  int  $correctIndex  Which option index is correct
     */
    public function multipleChoice(int $count = 3, int $correctIndex = 1, int $points = 2): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'multiple_choice',
            'points' => $points,
            'questions' => collect(range(1, $count))->map(fn ($n) => [
                'text' => "Multiple choice question {$n}?",
                'type' => 'multiple_choice',
                'points' => $points,
                'options' => collect(range(0, 3))->map(fn ($i) => [
                    'text' => "Option {$i}",
                    'is_correct' => $i === $correctIndex,
                ])->all(),
            ])->all(),
        ]);
    }

    public function trueFalse(int $count = 2, bool $answer = true, int $points = 1): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'true_false',
            'points' => $points,
            'questions' => collect(range(1, $count))->map(fn ($n) => [
                'text' => "True or false statement {$n}.",
                'type' => 'true_false',
                'points' => $points,
                'options' => [
                    ['text' => 'True', 'is_correct' => $answer],
                    ['text' => 'False', 'is_correct' => ! $answer],
                ],
            ])->all(),
        ]);
    }

    /**
     * @param  array<int, string>  $answers  Correct answers, one per question.
     */
    public function identification(array $answers = ['Manila'], int $points = 3): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'identification',
            'points' => $points,
            'questions' => collect($answers)->map(fn ($answer, $i) => [
                'text' => 'Identification question '.($i + 1).'?',
                'type' => 'identification',
                'points' => $points,
                'correct_answer' => $answer,
            ])->all(),
        ]);
    }

    public function essay(int $count = 1, int $points = 10, string $gradingMethod = 'ai'): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'essay',
            'points' => $points,
            'questions' => collect(range(1, $count))->map(fn ($n) => [
                'text' => "Discuss topic {$n} in detail.",
                'type' => 'essay',
                'points' => $points,
                'grading_method' => $gradingMethod,
            ])->all(),
        ]);
    }

    /**
     * A part mixing every supported question type, in a known order:
     *   1: multiple_choice (correct index 1, 2pts)
     *   2: identification  ("Manila", 3pts)
     *   3: essay           (10pts)
     */
    public function mixed(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'mixed',
            'points' => 1,
            'questions' => [
                [
                    'text' => 'Mixed multiple choice?',
                    'type' => 'multiple_choice',
                    'points' => 2,
                    'options' => [
                        ['text' => 'Option 0', 'is_correct' => false],
                        ['text' => 'Option 1', 'is_correct' => true],
                    ],
                ],
                [
                    'text' => 'Mixed identification?',
                    'type' => 'identification',
                    'points' => 3,
                    'correct_answer' => 'Manila',
                ],
                [
                    'text' => 'Mixed essay?',
                    'type' => 'essay',
                    'points' => 10,
                    'grading_method' => 'ai',
                ],
            ],
        ]);
    }

    /**
     * Explicit question payload for bespoke cases.
     *
     * @param  array<int, array<string, mixed>>  $questions
     */
    public function withQuestions(array $questions): static
    {
        return $this->state(fn (array $attributes) => ['questions' => $questions]);
    }
}
