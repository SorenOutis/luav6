<?php

namespace App\Ai\Tools;

use App\Models\Exam;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GenerateExamQuestionsTool extends PendingWriteTool implements Tool
{
    private const TYPE_ORDER = ['multiple_choice', 'true_false', 'identification', 'essay'];

    private const TYPE_LABELS = [
        'multiple_choice' => 'Multiple choice',
        'true_false' => 'True or false',
        'identification' => 'Identification',
        'essay' => 'Essay',
    ];

    public function description(): Stringable|string
    {
        return 'Prepare an AI question-generation job. This tool does not attach questions. The first UI approval starts generation into a private review draft; a teacher must then review the generated content and explicitly approve that saved revision before it can be attached to an exam.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $exam = Exam::query()
            ->withoutGlobalScope('workspace')
            ->whereKey((int) ($request['exam_id'] ?? 0))
            ->where('workspace_id', $this->workspaceId())
            ->first();
        if (! $exam) {
            return 'Error: exam not found in this workspace. Use exams_admin for valid exam IDs.';
        }

        $sourceText = trim((string) ($request['source_text'] ?? ''));
        if ($sourceText === '') {
            return 'Error: source_text is required.';
        }
        if (mb_strlen($sourceText) > 32000) {
            return 'Error: source_text is too long (maximum 32,000 characters). Split the material into a smaller question-generation action.';
        }

        $counts = [];
        foreach (self::TYPE_ORDER as $type) {
            $counts[$type] = max(0, (int) ($request[$type] ?? 0));
        }
        if ($counts['multiple_choice'] > 30 || $counts['true_false'] > 30 || $counts['identification'] > 30 || $counts['essay'] > 10) {
            return 'Error: question counts exceed the per-type limits (30 objective questions and 10 essays).';
        }
        $total = array_sum($counts);
        if ($total <= 0) {
            return 'Error: request at least one question.';
        }
        if ($total > 100) {
            return 'Error: too many questions (max 100 total).';
        }

        $difficulty = (string) ($request['difficulty'] ?? 'medium');
        if (! in_array($difficulty, ['easy', 'medium', 'hard'], true)) {
            $difficulty = 'medium';
        }
        $topic = trim((string) ($request['topic'] ?? '')) ?: null;
        $points = max(1, (int) ($request['points'] ?? 1));
        $instructions = trim((string) ($request['instructions'] ?? '')) ?: null;
        $countSummary = collect($counts)
            ->filter()
            ->map(fn (int $count, string $type): string => self::TYPE_LABELS[$type].": {$count}")
            ->values()
            ->all();

        return $this->stageAction(
            'generate_exam_questions',
            'Generate an exam question review draft',
            "Generate {$total} question(s) as a private teacher-review draft for \"{$exam->title}\". Nothing will be attached until the generated content is separately approved.",
            [
                'exam_id' => $exam->id,
                'expected_updated_at' => $exam->updated_at?->toJSON(),
                'source_text' => $sourceText,
                'topic' => $topic,
                'difficulty' => $difficulty,
                'type_counts' => $counts,
                'points' => $points,
                'instructions' => $instructions,
            ],
            [
                ['field' => 'Target exam', 'before' => "{$exam->title} (#{$exam->id})", 'after' => "Private AI question draft for {$exam->title} (#{$exam->id}) — teacher approval still required"],
                ['field' => 'Question counts', 'before' => null, 'after' => implode("\n", $countSummary)],
                ['field' => 'Difficulty', 'before' => null, 'after' => $difficulty],
                ['field' => 'Topic', 'before' => null, 'after' => $topic],
                ['field' => 'Points per question', 'before' => null, 'after' => $points],
                ['field' => 'Part instructions', 'before' => null, 'after' => $instructions ?: 'Default instructions by question type'],
                ['field' => 'Source material', 'before' => null, 'after' => $sourceText],
            ],
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'exam_id' => $schema->integer()->description('Target exam ID from exams_admin.')->required(),
            'source_text' => $schema->string()->description('Source material for the questions.')->required(),
            'topic' => $schema->string()->description('Optional topic/title to focus on.'),
            'difficulty' => $schema->string()->description('easy, medium, or hard (default medium).'),
            'multiple_choice' => $schema->integer()->description('Multiple choice count (0–30).'),
            'true_false' => $schema->integer()->description('True/false count (0–30).'),
            'identification' => $schema->integer()->description('Identification count (0–30).'),
            'essay' => $schema->integer()->description('Essay count (0–10).'),
            'points' => $schema->integer()->description('Default points per question (default 1).'),
            'instructions' => $schema->string()->description('Optional instructions for each new part.'),
        ];
    }
}
