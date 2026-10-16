<?php

namespace App\Ai\Tools;

use App\Models\Lesson;
use App\Models\LessonQuiz;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class ManageLessonQuizTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'manage_lesson_quiz';
    }

    public function description(): Stringable|string
    {
        return 'Prepare creating or replacing the quiz for a lesson. Questions are multiple-choice with exactly the given options; set is_correct on the correct option of each question. This tool never writes directly; only the administrator approving in the UI can execute it.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $workspaceId = $this->workspaceId();
        if (! $workspaceId) {
            return 'Error: no active workspace selected.';
        }

        $lesson = $this->resolveLesson($request, $workspaceId);
        if (is_string($lesson)) {
            return $lesson;
        }

        $questions = $this->parseQuestions($request['questions'] ?? null);
        if (is_string($questions)) {
            return $questions;
        }

        $existing = LessonQuiz::query()->where('lesson_id', $lesson->id)->first();

        $passScore = array_key_exists('pass_score', $request->all())
            ? (int) $request['pass_score']
            : (int) ($existing->pass_score ?? 75);

        if ($passScore < 0 || $passScore > 100) {
            return 'Error: pass_score must be between 0 and 100.';
        }

        $allowedAttempts = array_key_exists('allowed_attempts', $request->all())
            ? (int) $request['allowed_attempts']
            : (int) ($existing->allowed_attempts ?? 0);

        if ($allowedAttempts < 0) {
            return 'Error: allowed_attempts cannot be negative (0 means unlimited).';
        }

        $beforeCount = $existing ? count($existing->questions ?? []) : 0;

        return $this->stageAction(
            'manage_lesson_quiz',
            $existing ? 'Replace lesson quiz' : 'Create lesson quiz',
            ($existing ? 'Replace' : 'Create')." the quiz for lesson \"{$lesson->title}\" (#{$lesson->id}) with ".count($questions).' question(s).',
            [
                'lesson_id' => $lesson->id,
                'questions' => $questions,
                'pass_score' => $passScore,
                'allowed_attempts' => $allowedAttempts,
                'expected_updated_at' => $existing?->updated_at?->toJSON(),
            ],
            [
                ['field' => 'Lesson', 'before' => null, 'after' => "{$lesson->title} (#{$lesson->id})"],
                ['field' => 'Questions', 'before' => $beforeCount > 0 ? "{$beforeCount} existing" : 'None', 'after' => count($questions).' question(s)'],
                ['field' => 'Pass score', 'before' => $existing ? (string) $existing->pass_score : null, 'after' => (string) $passScore],
                ['field' => 'Allowed attempts', 'before' => $existing ? (string) $existing->allowed_attempts : null, 'after' => $allowedAttempts === 0 ? 'Unlimited' : (string) $allowedAttempts],
            ],
        );
    }

    /**
     * @return array<int, array{question: string, options: array<int, array{text: string, is_correct: bool}>}>|string
     */
    private function parseQuestions(mixed $value): array|string
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            if (! is_array($decoded)) {
                return 'Error: questions must be a JSON array like [{"question": "...", "options": [{"text": "...", "is_correct": true}, ...]}].';
            }
            $value = $decoded;
        }

        if (! is_array($value) || $value === []) {
            return 'Error: questions are required — provide a JSON array with at least one question.';
        }

        if (count($value) > 50) {
            return 'Error: too many questions (maximum 50 per quiz).';
        }

        $questions = [];

        foreach ($value as $index => $question) {
            $number = $index + 1;

            if (! is_array($question)) {
                return "Error: question #{$number} must be an object with a 'question' text and 'options' array.";
            }

            $text = trim((string) ($question['question'] ?? ''));
            if ($text === '') {
                return "Error: question #{$number} is missing its question text.";
            }

            $options = $question['options'] ?? null;
            if (! is_array($options) || count($options) < 2) {
                return "Error: question #{$number} must have at least 2 options.";
            }

            $normalizedOptions = [];
            $correctCount = 0;

            foreach ($options as $option) {
                $optionText = trim((string) (is_array($option) ? ($option['text'] ?? '') : $option));
                if ($optionText === '') {
                    return "Error: question #{$number} has an option without text.";
                }

                $isCorrect = is_array($option) && filter_var($option['is_correct'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($isCorrect) {
                    $correctCount++;
                }

                $normalizedOptions[] = ['text' => $optionText, 'is_correct' => $isCorrect];
            }

            if ($correctCount === 0) {
                return "Error: question #{$number} needs exactly one option marked is_correct: true.";
            }

            if ($correctCount > 1) {
                return "Error: question #{$number} has {$correctCount} correct options; mark exactly one as correct.";
            }

            $questions[] = ['question' => $text, 'options' => $normalizedOptions];
        }

        return $questions;
    }

    private function resolveLesson(Request $request, int $workspaceId): Lesson|string
    {
        $lessonId = (int) ($request['lesson_id'] ?? 0);
        $lessonTitle = trim((string) ($request['lesson_title'] ?? ''));

        if ($lessonId <= 0 && $lessonTitle === '') {
            return 'Error: provide lesson_id or lesson_title.';
        }

        $query = Lesson::query()
            ->withoutGlobalScope('workspace')
            ->where('workspace_id', $workspaceId)
            ->when($lessonId > 0, fn ($q) => $q->whereKey($lessonId))
            ->when($lessonId <= 0, fn ($q) => $q->where('title', 'like', "%{$lessonTitle}%"));

        $matches = $query->limit(5)->get();

        if ($matches->isEmpty()) {
            return 'Error: lesson not found in this workspace.';
        }

        if ($lessonId <= 0 && $matches->count() > 1) {
            $options = $matches->map(fn (Lesson $lesson) => "{$lesson->title} (#{$lesson->id})")->implode(', ');

            return "Error: multiple lessons match \"{$lessonTitle}\": {$options}. Use lesson_id instead.";
        }

        return $matches->first();
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'lesson_id' => $schema->integer()->description('The lesson ID whose quiz should be created or replaced.'),
            'lesson_title' => $schema->string()->description('Lesson title (or partial), alternative to lesson_id.'),
            'questions' => $schema->string()->description('JSON array of questions: [{"question": "text", "options": [{"text": "answer", "is_correct": true}, {"text": "other", "is_correct": false}]}]. Exactly one correct option per question.')->required(),
            'pass_score' => $schema->integer()->description('Minimum score (0-100) required to pass. Defaults to 75.'),
            'allowed_attempts' => $schema->integer()->description('Maximum attempts; 0 means unlimited. Defaults to unlimited.'),
        ];
    }
}
