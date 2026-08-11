<?php

namespace App\Ai\Tools;

use App\Models\Exam;
use App\Models\ExamPart;
use App\Services\AiQuestionGeneratorService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GenerateExamQuestionsTool implements Tool
{
    /** Canonical question-type order used when grouping questions into parts. */
    protected const TYPE_ORDER = ['multiple_choice', 'true_false', 'identification', 'essay'];

    protected const TYPE_LABELS = [
        'multiple_choice' => 'Multiple Choice',
        'true_false' => 'True or False',
        'identification' => 'Identification',
        'essay' => 'Essay',
    ];

    protected const DEFAULT_INSTRUCTIONS = [
        'multiple_choice' => 'Choose the best answer for each item.',
        'true_false' => 'Write TRUE if the statement is correct, otherwise write FALSE.',
        'identification' => 'Write the term or phrase being described.',
        'essay' => 'Answer the following in complete sentences.',
    ];

    protected const DIFFICULTIES = ['easy', 'medium', 'hard'];

    public function __construct(protected ?AiQuestionGeneratorService $service = null)
    {
        $this->service ??= app(AiQuestionGeneratorService::class);
    }

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'Generate AI exam questions from source material and attach them to an exam in the admin\'s workspace as new question parts. IMPORTANT: present the plan (exam, source, question counts, difficulty) to the admin first and only call this with confirm=true after they explicitly approve.';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $admin = auth()->user();

        if (! $admin?->is_admin) {
            return 'Only admins can use this tool.';
        }

        if (! $request['confirm']) {
            return 'NOT EXECUTED — confirmation missing. Present the generation plan to the admin and ask them to confirm; then call this tool again with confirm=true.';
        }

        // The workspace global scope makes this null for exams owned by
        // another admin.
        $exam = Exam::query()->find((int) ($request['exam_id'] ?? 0));

        if (! $exam) {
            return 'Error: exam not found in this workspace. Use the exams_admin tool to list valid exam IDs.';
        }

        $sourceText = trim((string) ($request['source_text'] ?? ''));

        if ($sourceText === '') {
            return 'Error: source_text is required. Paste the material the questions should be based on.';
        }

        $typeCounts = $this->normalizeCounts($request);
        $total = array_sum($typeCounts);

        if ($total <= 0) {
            return 'Error: request at least one question (multiple_choice, true_false, identification, or essay).';
        }

        if ($total > 100) {
            return 'Error: too many questions (max 100 total). Please reduce some counts.';
        }

        $difficulty = in_array((string) ($request['difficulty'] ?? ''), self::DIFFICULTIES, true)
            ? (string) $request['difficulty']
            : 'medium';

        $topic = trim((string) ($request['topic'] ?? '')) ?: null;

        try {
            $questions = $this->service->generate($sourceText, $typeCounts, $difficulty, $topic);
        } catch (\Throwable $e) {
            return "Error generating questions: {$e->getMessage()}";
        }

        if (empty($questions)) {
            return 'The AI returned no usable questions. Try a shorter/cleaner source or reduce the requested counts.';
        }

        $created = $this->attach($exam, $questions, $request);

        return "Attached {$created} question part(s) to \"{$exam->title}\" (exam ID {$exam->id}).";
    }

    /**
     * @return array<string, int>
     */
    protected function normalizeCounts(Request $request): array
    {
        $counts = [];

        foreach (self::TYPE_ORDER as $type) {
            $counts[$type] = max(0, (int) ($request[$type] ?? 0));
        }

        return $counts;
    }

    /**
     * Group the generated questions by type and create one ExamPart per type.
     */
    protected function attach(Exam $exam, array $questions, Request $request): int
    {
        $default = max(1, (int) ($request['points'] ?? 1) ?: 1);
        $customInstructions = trim((string) ($request['instructions'] ?? ''));

        $grouped = array_fill_keys(self::TYPE_ORDER, []);

        foreach ($questions as $q) {
            if (! is_array($q)) {
                continue;
            }

            $type = (string) ($q['type'] ?? '');

            if (! isset($grouped[$type])) {
                continue;
            }

            $q['points'] = max(1, (int) ($q['points'] ?? $default) ?: $default);
            $grouped[$type][] = $q;
        }

        $nextOrder = (int) ($exam->parts()->max('sort_order') ?? 0);
        $partIndex = $exam->parts()->count();
        $romans = ['I', 'II', 'III', 'IV', 'V', 'VI', 'VII', 'VIII', 'IX', 'X'];
        $created = 0;

        foreach (self::TYPE_ORDER as $type) {
            $typeQuestions = $grouped[$type];

            if (empty($typeQuestions)) {
                continue;
            }

            $nextOrder++;
            $partIndex++;
            $roman = $romans[$partIndex - 1] ?? (string) $partIndex;

            ExamPart::create([
                'exam_id' => $exam->id,
                'title' => "Part {$roman} - ".self::TYPE_LABELS[$type],
                'instructions' => $customInstructions !== '' ? $customInstructions : self::DEFAULT_INSTRUCTIONS[$type],
                'type' => 'section',
                'sort_order' => $nextOrder,
                'points' => $default,
                'questions' => $typeQuestions,
            ]);

            $created++;
        }

        return $created;
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'exam_id' => $schema->integer()->description('Target exam ID from the exams_admin tool.')->required(),
            'source_text' => $schema->string()->description('The source material the questions should be based on.')->required(),
            'topic' => $schema->string()->description('Optional topic/title to focus the questions on.'),
            'difficulty' => $schema->string()->description('Difficulty: easy, medium, or hard (default medium).'),
            'multiple_choice' => $schema->integer()->description('Number of multiple choice questions (0–30).'),
            'true_false' => $schema->integer()->description('Number of true/false questions (0–30).'),
            'identification' => $schema->integer()->description('Number of identification questions (0–30).'),
            'essay' => $schema->integer()->description('Number of essay questions (0–10).'),
            'points' => $schema->integer()->description('Default points per question (default 1).'),
            'instructions' => $schema->string()->description('Optional custom instructions applied to each new part.'),
            'confirm' => $schema->boolean()->description('Must be true, and only after the admin explicitly approved the plan.')->required(),
        ];
    }
}
