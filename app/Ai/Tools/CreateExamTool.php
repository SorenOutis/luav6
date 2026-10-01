<?php

namespace App\Ai\Tools;

use App\Models\Section;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class CreateExamTool extends PendingWriteTool implements Tool
{
    public function name(): string
    {
        return 'create_exam';
    }

    public function description(): Stringable|string
    {
        return 'Prepare a new DRAFT exam for human review. This tool never writes the exam; it creates a server-issued approval card showing the exact values. Call it once after gathering the required details. The administrator must approve in the UI.';
    }

    public function handle(Request $request): Stringable|string
    {
        if ($error = $this->adminError()) {
            return $error;
        }

        $title = Str::limit(trim((string) ($request['title'] ?? '')), 255, '');
        if ($title === '') {
            return 'Error: a title is required.';
        }

        $sectionId = $request['section_id'] ?? null;
        $sectionName = trim((string) ($request['section_name'] ?? ''));
        $section = null;
        if ($sectionId !== null) {
            $section = Section::query()
                ->withoutGlobalScope('workspace')
                ->whereKey((int) $sectionId)
                ->where('workspace_id', $this->workspaceId())
                ->first();
            if (! $section) {
                return "Error: section [{$sectionId}] does not exist in this workspace. Use workspace_overview for valid section IDs.";
            }
        } elseif ($sectionName !== '') {
            $section = Section::query()
                ->withoutGlobalScope('workspace')
                ->where('name', $sectionName)
                ->where('workspace_id', $this->workspaceId())
                ->first();
            if ($section) {
                $sectionId = $section->id;
            }
        }

        $rawDate = (string) ($request['exam_date'] ?? '');
        if ($rawDate === '') {
            $examDate = now()->addDay()->setTime(9, 0);
        } else {
            try {
                $examDate = Carbon::parse($rawDate);
            } catch (\Throwable) {
                $examDate = now()->addDay()->setTime(9, 0);
            }
        }

        $endsAt = null;
        if (($request['ends_at'] ?? null) !== null) {
            try {
                $endsAt = Carbon::parse((string) $request['ends_at']);
            } catch (\Throwable) {
                return 'Error: ends_at must be a valid date/time, e.g. "2026-08-20 10:00".';
            }

            if ($endsAt->lte($examDate)) {
                return 'Error: ends_at must be after exam_date.';
            }
        }

        $duration = min(max((int) ($request['duration_minutes'] ?? 60), 5), 600);
        $description = trim((string) ($request['description'] ?? '')) ?: null;
        if ($description !== null && mb_strlen($description) > 20000) {
            return 'Error: exam description is too long (maximum 20,000 characters).';
        }

        $rawQuestions = $request['questions'] ?? [];
        $questions = [];
        if (is_array($rawQuestions)) {
            foreach ($rawQuestions as $q) {
                if (! is_array($q)) {
                    continue;
                }
                $text = trim((string) ($q['text'] ?? $q['question'] ?? ''));
                if ($text === '') {
                    continue;
                }
                $type = (string) ($q['type'] ?? 'multiple_choice');
                $points = max(1, (int) ($q['points'] ?? 1));
                $options = [];
                if (! empty($q['options']) && is_array($q['options'])) {
                    foreach ($q['options'] as $opt) {
                        if (is_array($opt)) {
                            $options[] = [
                                'text' => (string) ($opt['text'] ?? ''),
                                'is_correct' => (bool) ($opt['is_correct'] ?? false),
                            ];
                        } elseif (is_string($opt)) {
                            $isCorrect = isset($q['correct_answer']) && (string) $q['correct_answer'] === $opt;
                            $options[] = [
                                'text' => $opt,
                                'is_correct' => $isCorrect,
                            ];
                        }
                    }
                }
                $questions[] = [
                    'text' => $text,
                    'type' => $type,
                    'points' => $points,
                    'options' => $options,
                    'correct_answer' => $q['correct_answer'] ?? null,
                ];
            }
        }

        $term = trim((string) ($request['term'] ?? ''));
        if ($term === '') {
            $titleLower = strtolower($title);
            if (str_contains($titleLower, 'prelim')) {
                $term = 'Prelim';
            } elseif (str_contains($titleLower, 'midterm')) {
                $term = 'Midterm';
            } elseif (str_contains($titleLower, 'semi-final') || str_contains($titleLower, 'semifinal')) {
                $term = 'Semi-Final';
            } elseif (str_contains($titleLower, 'final')) {
                $term = 'Final';
            } elseif (str_contains($titleLower, '1st quarter') || str_contains($titleLower, 'quarter 1') || str_contains($titleLower, 'q1')) {
                $term = 'First Semester - 1st Quarter';
            } elseif (str_contains($titleLower, '2nd quarter') || str_contains($titleLower, 'quarter 2') || str_contains($titleLower, 'q2')) {
                $term = 'First Semester - 2nd Quarter';
            } elseif (str_contains($titleLower, '3rd quarter') || str_contains($titleLower, 'quarter 3') || str_contains($titleLower, 'q3')) {
                $term = 'Second Semester - 1st Quarter';
            } elseif (str_contains($titleLower, '4th quarter') || str_contains($titleLower, 'quarter 4') || str_contains($titleLower, 'q4')) {
                $term = 'Second Semester - 2nd Quarter';
            } elseif ($section?->school_level === Section::SCHOOL_LEVEL_SENIOR_HIGH) {
                $term = 'First Semester - 1st Quarter';
            } else {
                $term = 'Midterm';
            }
        }

        $rawBlockedIds = $request['blocked_user_ids'] ?? [];
        if (! is_array($rawBlockedIds)) {
            $rawBlockedIds = is_string($rawBlockedIds) ? explode(',', $rawBlockedIds) : [];
        }
        $blockedUserIds = collect($rawBlockedIds)
            ->map(fn ($id) => (int) trim((string) $id))
            ->filter(fn (int $id) => $id > 0)
            ->unique()
            ->values()
            ->all();

        $rawBlockedStudents = $request['blocked_students'] ?? [];
        if (is_string($rawBlockedStudents)) {
            $rawBlockedStudents = explode(',', $rawBlockedStudents);
        }
        if (is_array($rawBlockedStudents) && ! empty($rawBlockedStudents)) {
            $names = collect($rawBlockedStudents)->map(fn ($n) => trim((string) $n))->filter()->values();
            if ($names->isNotEmpty()) {
                $matchedUserIds = User::query()
                    ->where(function ($query) use ($names) {
                        foreach ($names as $name) {
                            $query->orWhere('name', 'like', "%{$name}%");
                        }
                    })
                    ->pluck('id')
                    ->all();
                $blockedUserIds = array_values(array_unique(array_merge($blockedUserIds, $matchedUserIds)));
            }
        }

        $sectionLabel = $section
            ? "{$section->name} (#{$section->id})"
            : ($sectionName !== '' ? "{$sectionName} (new section)" : 'All sections');

        $payload = [
            'title' => $title,
            'description' => $description,
            'term' => $term,
            'exam_date' => $examDate->toIso8601String(),
            'ends_at' => $endsAt?->toIso8601String(),
            'duration_minutes' => $duration,
            'section_id' => $section?->id,
            'section_name' => $sectionName ?: null,
            'section_expected_updated_at' => $section?->updated_at?->toJSON(),
            'blocked_user_ids' => $blockedUserIds,
            'questions' => $questions,
        ];

        $blockedLabel = count($blockedUserIds) > 0
            ? count($blockedUserIds).' student(s) blocked'
            : 'None (open to all students)';

        $preview = [
            ['field' => 'Record', 'before' => null, 'after' => 'New draft exam'],
            ['field' => 'Title', 'before' => null, 'after' => $title],
            ['field' => 'Term / Period', 'before' => null, 'after' => $term],
            ['field' => 'Description', 'before' => null, 'after' => $description],
            ['field' => 'Starts at', 'before' => null, 'after' => $examDate->format('M d, Y g:i A')],
            ['field' => 'Ends at', 'before' => null, 'after' => $endsAt?->format('M d, Y g:i A') ?? 'Open until manually closed'],
            ['field' => 'Duration', 'before' => null, 'after' => "{$duration} minutes"],
            ['field' => 'Section', 'before' => null, 'after' => $sectionLabel],
            ['field' => 'Blocked Students', 'before' => null, 'after' => $blockedLabel],
            ['field' => 'Status', 'before' => null, 'after' => 'draft'],
        ];

        if ($questions !== []) {
            $preview[] = [
                'field' => 'Questions',
                'before' => null,
                'after' => count($questions).' question(s) with answer keys attached',
            ];
        }

        return $this->stageAction(
            'create_exam',
            'Create draft exam',
            "Create the draft exam \"{$title}\" for {$sectionLabel}.",
            $payload,
            $preview,
        );
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'title' => $schema->string()->description('Exam title.')->required(),
            'term' => $schema->string()->description('Optional grading period or term, e.g. "Prelim", "Midterm", "Final" for College, or "First Semester - 1st Quarter" for Senior High. Auto-inferred from title or defaults to "Midterm". NEVER ask the teacher for this.'),
            'exam_date' => $schema->string()->description('Optional start date and time, e.g. "2026-08-20 09:00". Defaults to tomorrow at 9:00 AM.'),
            'ends_at' => $schema->string()->description('Optional end date/time, e.g. "2026-08-20 10:00". When set, the exam closes to students at this time. Defaults to open-ended.'),
            'duration_minutes' => $schema->integer()->description('Duration in minutes (5–600, default 60). NEVER ask for duration; use 60 by default.'),
            'section_id' => $schema->integer()->description('Optional section ID from workspace_overview. Omit for all sections.'),
            'section_name' => $schema->string()->description('Optional section name (e.g. "Section Z") when creating an exam for a new or recently staged section.'),
            'blocked_user_ids' => $schema->array()->description('Optional array of user IDs of students to block from taking this exam. Defaults to [] (no students blocked; open to all students). NEVER ask who to block unless the teacher explicitly asks to block someone.'),
            'blocked_students' => $schema->array()->description('Optional list of student names to block from taking this exam.'),
            'description' => $schema->string()->description('Optional exam description.'),
            'questions' => $schema->array()->description('Optional array of question objects to attach to the exam. Each question has: text (string), type (multiple_choice, true_false, identification, essay), points (int), options (array of {text, is_correct}), or correct_answer (string).'),
        ];
    }
}
