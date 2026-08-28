<?php

namespace App\Services;

use App\Enums\QuestionType;
use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSet;
use App\Models\ExamSubmission;
use App\Models\User;
use App\Support\IdentificationAnswerMatcher;
use App\Support\MatchingAnswerMatcher;
use Illuminate\Support\Collection;

/**
 * Builds the printable "View Answer" report for an exam.
 *
 * The report has two halves, either of which can be omitted:
 *
 *  - the answer key  : every question with its correct answer and points
 *  - student reports : per student, every question with what they answered,
 *                      whether it was right or wrong, and the overall score
 *
 * Grading here MIRRORS App\Http\Controllers\ExamController::submitPart():
 * multiple choice / true-false compare the stored option index against the
 * option flagged `is_correct`, identification compares normalised text, and
 * essays carry no fixed key (AI score / teacher marks are shown instead).
 */
class ExamAnswerReportService
{
    public const MODE_KEY = 'key';

    public const MODE_STUDENTS = 'students';

    /**
     * @param  list<int>  $studentIds  Empty means "every student with a submission".
     * @param  ExamSet|null  $set  Restrict the report to one exam set. Null
     *                             reports on every set at once.
     * @return array<string, mixed>
     */
    public function build(Exam $exam, string $mode = self::MODE_STUDENTS, array $studentIds = [], bool $includeKey = true, ?ExamSet $set = null): array
    {
        $exam->loadMissing('section');

        // A set-scoped report mirrors what the students on that set saw: only
        // its questions, and only the students who were handed that set.
        $parts = ($set !== null
            ? $exam->parts()->where('exam_set_id', $set->getKey())
            : $exam->parts())
            ->with('examSet')
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
        $structure = $parts->map(fn (ExamPart $part, int $index): array => $this->normalizePart($part, $index))->all();

        $totalPoints = collect($structure)->sum('total_points');
        $questionCount = collect($structure)->sum(fn (array $part): int => count($part['questions']));

        $students = $mode === self::MODE_KEY
            ? []
            : $this->buildStudentReports($exam, $structure, $studentIds);

        return [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'section' => $exam->section?->name,
                'exam_date' => $exam->exam_date,
                'duration_minutes' => $exam->duration_minutes,
                'status' => $exam->status,
                'total_points' => $totalPoints,
                'question_count' => $questionCount,
                'part_count' => count($structure),
                // Null means "every set at once"; the report header prints
                // "All sets" in that case, and hides the row entirely while
                // the exam only ever had one set.
                'set' => $set?->title,
                'set_id' => $set?->getKey(),
                'set_count' => $exam->sets()->count(),
            ],
            'mode' => $mode,
            'include_key' => $mode === self::MODE_KEY ? true : $includeKey,
            'parts' => $structure,
            'students' => $students,
            'class_summary' => $this->buildClassSummary($students),
            'generated_at' => now(),
            'generated_by' => auth()->user()?->name,
        ];
    }

    /**
     * Students who have at least one submission for this exam, for the picker.
     *
     * Passing a set narrows the picker to the students who were handed that
     * set, so a per-set report can never be asked for a student who never saw
     * those questions.
     *
     * @return array<int, string>
     */
    public function studentOptions(Exam $exam, ?ExamSet $set = null): array
    {
        return ExamSubmission::query()
            ->where('exam_id', $exam->id)
            ->when(
                $set !== null,
                fn ($query) => $query->whereIn('exam_part_id', $this->partIdsFor($exam, $set))
            )
            ->with('user:id,name')
            ->get()
            ->pluck('user')
            ->filter()
            ->unique('id')
            ->sortBy('name')
            ->mapWithKeys(fn (User $user): array => [$user->id => $user->name])
            ->all();
    }

    /**
     * The exam parts that belong to one set.
     *
     * @return list<int>
     */
    private function partIdsFor(Exam $exam, ExamSet $set): array
    {
        return $exam->parts()
            ->where('exam_set_id', $set->getKey())
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $structure
     * @param  list<int>  $studentIds
     * @return array<int, array<string, mixed>>
     */
    private function buildStudentReports(Exam $exam, array $structure, array $studentIds): array
    {
        // Only submissions for the parts in this report: with several sets,
        // a student's answers belong to the set they were handed, so the other
        // sets' students (and their blanks) stay out of the report.
        $partIds = collect($structure)->pluck('id')->all();

        $submissions = ExamSubmission::query()
            ->where('exam_id', $exam->id)
            ->when($partIds !== [], fn ($query) => $query->whereIn('exam_part_id', $partIds))
            ->when($studentIds !== [], fn ($query) => $query->whereIn('user_id', $studentIds))
            ->with('user:id,name,email')
            ->orderBy('created_at')
            ->get();

        $partsById = collect($structure)->keyBy('id');

        return $submissions
            ->filter(fn (ExamSubmission $submission): bool => $submission->user !== null)
            ->groupBy('user_id')
            ->map(function (Collection $rows) use ($partsById): array {
                $studentParts = $rows
                    ->map(fn (ExamSubmission $submission): ?array => $partsById->get($submission->exam_part_id))
                    ->filter()
                    ->unique('id')
                    ->values();
                $setIds = $studentParts->pluck('set_id')->filter()->unique()->values();
                $setTitle = $setIds->count() === 1 ? $studentParts->first()['set'] : null;
                $studentStructure = $setIds->count() === 1
                    ? $partsById
                        ->filter(fn (array $part): bool => (int) ($part['set_id'] ?? 0) === (int) $setIds->first())
                        ->values()
                        ->all()
                    : $studentParts->all();

                return $this->buildStudentReport(
                    $rows->first()->user,
                    $rows,
                    $studentStructure,
                    $setTitle,
                );
            })
            ->sortBy(fn (array $report): string => (string) $report['student']['name'])
            ->values()
            ->all();
    }

    /**
     * @param  Collection<int, ExamSubmission>  $submissions
     * @param  array<int, array<string, mixed>>  $structure
     * @return array<string, mixed>
     */
    private function buildStudentReport(User $student, Collection $submissions, array $structure, ?string $setTitle): array
    {
        $byPart = $submissions->keyBy('exam_part_id');

        $parts = [];
        $correct = 0;
        $wrong = 0;
        $unanswered = 0;
        $partial = 0;
        $pending = 0;
        $essaysScored = 0;
        $essayPoints = 0.0;
        $awarded = 0.0;
        $totalPoints = 0;

        foreach ($structure as $part) {
            /** @var ExamSubmission|null $submission */
            $submission = $byPart->get($part['id']);
            $answers = $submission ? $this->answersByQuestionNumber($submission) : collect();

            $items = [];
            foreach ($part['questions'] as $question) {
                $row = $answers->get($question['number']);
                $item = $this->buildItem($question, is_array($row) ? $row : null, $submission);

                if ($item['result'] === 'scored') {
                    $essaysScored++;
                    $essayPoints += (float) $item['earned'];
                } else {
                    match ($item['result']) {
                        'correct' => $correct++,
                        'wrong' => $wrong++,
                        'unanswered' => $unanswered++,
                        'partial' => $partial++,
                        default => $pending++,
                    };
                }

                $items[] = $item;
                $totalPoints += $question['points'];
            }

            // The teacher's comment lives on the submission, not per question.
            // Show it under the part's first essay (where it belongs in context)
            // and skip the part-level block so it is never printed twice.
            $teacherFeedback = $this->teacherFeedback($submission);
            $teacherFeedbackShownInline = false;

            if ($teacherFeedback !== null) {
                foreach ($items as $index => $candidate) {
                    if (QuestionType::tryFromStored($candidate['question']['type'] ?? null) === QuestionType::Essay) {
                        $items[$index]['teacher_feedback'] = $teacherFeedback;
                        $teacherFeedbackShownInline = true;

                        break;
                    }
                }
            }

            $partAwarded = $submission ? (float) $submission->score : 0.0;
            $awarded += $partAwarded;

            $parts[] = [
                'part' => $part,
                'submitted' => $submission !== null,
                'status' => $submission?->status,
                'status_label' => $this->statusLabel($submission?->status),
                'score' => $submission ? round($partAwarded, 2) : null,
                'total_points' => $part['total_points'],
                'is_late' => (bool) $submission?->is_late,
                'submitted_at' => $submission?->created_at,
                'feedback' => $teacherFeedbackShownInline ? null : $teacherFeedback,
                'items' => $items,
            ];
        }

        $percentage = $totalPoints > 0 ? round(($awarded / $totalPoints) * 100, 1) : null;

        return [
            'student' => [
                'id' => $student->id,
                'name' => $student->name,
                'email' => $student->email,
                'set' => $setTitle,
            ],
            'parts' => $parts,
            'summary' => [
                'score' => round($awarded, 2),
                'total_points' => $totalPoints,
                'percentage' => $percentage,
                'correct' => $correct,
                'wrong' => $wrong,
                'essays_scored' => $essaysScored,
                'essay_points' => round($essayPoints, 2),
                'unanswered' => $unanswered,
                'partial' => $partial,
                'pending' => $pending,
                'parts_submitted' => $submissions->count(),
                'parts_total' => count($structure),
                'is_late' => $submissions->contains(fn (ExamSubmission $submission): bool => (bool) $submission->is_late),
                'submitted_at' => $submissions->max('created_at'),
                'has_pending_grading' => $submissions->contains(
                    fn (ExamSubmission $submission): bool => in_array($submission->status, ['pending_ai', 'pending_review', 'submitted'], true)
                ),
            ],
        ];
    }

    /**
     * @param  array<string, mixed>  $question
     * @param  array<string, mixed>|null  $row
     * @return array<string, mixed>
     */
    private function buildItem(array $question, ?array $row, ?ExamSubmission $submission): array
    {
        $answer = $row['answer'] ?? null;
        $hasAnswer = ! $this->isBlankAnswer($answer);

        $item = [
            'question' => $question,
            'student_answer' => null,
            'result' => 'unanswered',
            'earned' => 0.0,
            'earned_known' => true,
            'feedback' => null,
            'feedback_source' => null,
            'teacher_feedback' => null,
        ];

        $type = QuestionType::tryFromStored($question['type'] ?? null) ?? QuestionType::MultipleChoice;

        if ($type === QuestionType::Essay) {
            // Essays are reported as: question, answer, feedback, score.
            // There is no key to compare against, so no correct/wrong verdict.
            $item['student_answer'] = $hasAnswer ? (string) $answer : null;
            $item['feedback'] = $this->essayFeedback($row);
            $item['feedback_source'] = $item['feedback'] === null
                ? null
                : ($this->isTeacherReviewed($row) ? 'teacher' : 'ai');

            if (! $hasAnswer) {
                return $item;
            }

            if (is_array($row) && array_key_exists('ai_score', $row) && $row['ai_score'] !== null) {
                $item['earned'] = round((float) $row['ai_score'], 2);
                $item['result'] = 'scored';

                return $item;
            }

            $item['earned_known'] = false;
            $item['result'] = $submission?->status === 'graded' ? 'graded_manually' : 'pending';

            return $item;
        }

        if (! $hasAnswer) {
            return $item;
        }

        if ($type === QuestionType::Enumeration) {
            $breakdown = $this->enumerationBreakdown($question['enumeration_items'] ?? [], $answer);
            $earned = array_sum(array_column($breakdown, 'earned'));
            $item['student_answer'] = is_array($answer)
                ? implode(', ', array_values(array_map(fn ($value): string => trim((string) $value), $answer)))
                : null;
            $item['enumeration_breakdown'] = $breakdown;
            $item['earned'] = $earned;
            $item['result'] = $earned >= (float) $question['points']
                ? 'correct'
                : ($earned > 0 ? 'partial' : 'wrong');

            return $item;
        }

        if ($type === QuestionType::Matching) {
            $breakdown = MatchingAnswerMatcher::breakdown($question, $answer);
            $earned = array_sum(array_column($breakdown, 'earned'));
            $item['student_answer'] = collect($breakdown)
                ->map(fn (array $pair): string => $pair['prompt'].': '.($pair['submitted'] !== '' ? $pair['submitted'] : 'No answer'))
                ->implode('; ');
            $item['matching_breakdown'] = $breakdown;
            $item['earned'] = $earned;
            $item['result'] = $earned >= (float) $question['points']
                ? 'correct'
                : ($earned > 0 ? 'partial' : 'wrong');

            return $item;
        }

        if ($type === QuestionType::Identification) {
            $item['student_answer'] = (string) $answer;
            $item['result'] = IdentificationAnswerMatcher::matches($answer, $question)
                ? 'correct'
                : 'wrong';
        } elseif ($type->usesChoiceAnswer()) {
            $chosen = (int) $answer;
            $item['student_answer'] = $this->optionLabel($question['options'], $chosen);
            $item['result'] = ($question['correct_index'] !== null && $chosen === $question['correct_index'])
                ? 'correct'
                : 'wrong';
        } else {
            $item['student_answer'] = (string) $answer;
            $item['result'] = $this->normalizeText((string) $answer) === $this->normalizeText((string) ($question['correct_answer'] ?? ''))
                ? 'correct'
                : 'wrong';
        }

        $item['earned'] = $item['result'] === 'correct' ? (float) $question['points'] : 0.0;

        return $item;
    }

    /**
     * @return Collection<int|string, array<string, mixed>>
     */
    private function answersByQuestionNumber(ExamSubmission $submission): Collection
    {
        return collect($submission->answers)
            ->filter(fn ($row): bool => is_array($row))
            ->keyBy(fn (array $row): int => (int) ($row['question_number'] ?? 0));
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizePart(ExamPart $part, int $index): array
    {
        $questions = is_array($part->questions) ? $part->questions : [];
        $defaultPoints = (int) ($part->points ?? 1);

        $normalized = [];
        $totalPoints = 0;

        foreach (array_values($questions) as $questionIndex => $question) {
            if (! is_array($question)) {
                continue;
            }

            $type = QuestionType::tryFromStored($question['type'] ?? null) ?? QuestionType::MultipleChoice;
            $enumerationItems = $type === QuestionType::Enumeration
                ? collect($question['enumeration_items'] ?? [])
                    ->filter(fn ($item): bool => is_array($item))
                    ->map(fn (array $item): array => [
                        'answer' => (string) ($item['answer'] ?? ''),
                        'points' => (float) ($item['points'] ?? 0),
                    ])
                    ->filter(fn (array $item): bool => trim($item['answer']) !== '')
                    ->values()
                    ->all()
                : [];
            $matchingItems = $type === QuestionType::Matching
                ? MatchingAnswerMatcher::items($question)
                : [];
            $points = $type === QuestionType::Enumeration
                ? array_sum(array_column($enumerationItems, 'points'))
                : ($type === QuestionType::Matching
                    ? array_sum(array_column($matchingItems, 'points'))
                    : (int) ($question['points'] ?? $defaultPoints));
            $totalPoints += $points;

            $options = [];
            $correctIndex = null;

            foreach (array_values($question['options'] ?? []) as $optionIndex => $option) {
                $text = is_array($option)
                    ? (string) ($option['text'] ?? 'Option '.($optionIndex + 1))
                    : (string) $option;
                $isCorrect = is_array($option) && (bool) ($option['is_correct'] ?? false);

                if ($isCorrect && $correctIndex === null) {
                    $correctIndex = $optionIndex;
                }

                $options[] = [
                    'index' => $optionIndex,
                    'letter' => $this->letter($optionIndex),
                    'text' => $text,
                    'is_correct' => $isCorrect,
                ];
            }

            $normalized[] = [
                'number' => $questionIndex + 1,
                'text' => (string) ($question['text'] ?? ''),
                'type' => $type->value,
                'type_label' => $type->label(),
                'points' => $points,
                'options' => $options,
                'enumeration_items' => $enumerationItems,
                'matching_items' => $matchingItems,
                'correct_index' => $correctIndex,
                'correct_answer' => $type === QuestionType::Identification
                    ? (string) ($question['correct_answer'] ?? '')
                    : null,
                'accepted_answers' => $type === QuestionType::Identification
                    ? IdentificationAnswerMatcher::acceptedAnswers($question)
                    : [],
                'correct_display' => $this->correctDisplay($type, $options, $correctIndex, $question),
                'grading_method' => $type === QuestionType::Essay
                    ? (string) ($question['grading_method'] ?? 'ai')
                    : null,
            ];
        }

        return [
            'id' => $part->id,
            'number' => $index + 1,
            'title' => $part->title,
            // Set titles disambiguate a report that covers several sets: the
            // parts of different sets usually carry the same names.
            'set' => $part->examSet?->title,
            'set_id' => $part->exam_set_id,
            'instructions' => $part->instructions,
            'total_points' => $totalPoints,
            'questions' => $normalized,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     * @param  array<string, mixed>  $question
     */
    private function correctDisplay(QuestionType $type, array $options, ?int $correctIndex, array $question): string
    {
        return match ($type) {
            QuestionType::MultipleChoice, QuestionType::TrueFalse => $correctIndex !== null
                ? $this->optionLabel($options, $correctIndex)
                : 'No correct option marked',
            QuestionType::Identification => IdentificationAnswerMatcher::display($question),
            QuestionType::Enumeration => collect($question['enumeration_items'] ?? [])
                ->map(fn (array $item): string => $item['answer'].' ('.$item['points'].' pts)')
                ->implode(', ') ?: 'No answer key set',
            QuestionType::Matching => collect(MatchingAnswerMatcher::items($question))
                ->map(fn (array $item): string => $item['prompt'].' → '.$item['answer'].' ('.$item['points'].' pts)')
                ->implode('; ') ?: 'No answer key set',
            QuestionType::Essay => ($question['grading_method'] ?? 'ai') === 'manual'
                ? 'Graded by the teacher (no fixed key)'
                : 'Graded automatically by AI (no fixed key)',
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array{answer: string, points: float, earned: float, matched: bool}>
     */
    private function enumerationBreakdown(array $items, mixed $submittedAnswer): array
    {
        $submitted = is_array($submittedAnswer) ? array_values($submittedAnswer) : [];
        $matchedIndexes = [];

        return collect($items)
            ->filter(fn ($item): bool => is_array($item))
            ->map(function (array $item) use ($submitted, &$matchedIndexes): array {
                $expectedAnswer = (string) ($item['answer'] ?? '');
                $points = (float) ($item['points'] ?? 0);
                $matchedAnswer = null;

                foreach ($submitted as $submittedIndex => $answer) {
                    if (in_array($submittedIndex, $matchedIndexes, true)) {
                        continue;
                    }

                    if ($this->normalizeText((string) $answer) === $this->normalizeText($expectedAnswer)
                        && $this->normalizeText($expectedAnswer) !== '') {
                        $matchedIndexes[] = $submittedIndex;
                        $matchedAnswer = trim((string) $answer);

                        break;
                    }
                }

                return [
                    'answer' => $expectedAnswer,
                    'points' => $points,
                    'earned' => $matchedAnswer !== null ? $points : 0.0,
                    'matched' => $matchedAnswer !== null,
                ];
            })
            ->values()
            ->all();
    }

    private function isBlankAnswer(mixed $answer): bool
    {
        if (is_array($answer)) {
            return count($answer) === 0 || collect($answer)->every(fn ($item): bool => $this->isBlankAnswer($item));
        }

        return $answer === null || (is_string($answer) && trim($answer) === '');
    }

    /**
     * @param  array<int, array<string, mixed>>  $options
     */
    private function optionLabel(array $options, int $index): string
    {
        foreach ($options as $option) {
            if ((int) $option['index'] === $index) {
                return $option['letter'].'. '.$option['text'];
            }
        }

        return 'Choice #'.($index + 1).' (no matching option)';
    }

    /**
     * @param  array<string, mixed>|null  $row
     */
    private function essayFeedback(?array $row): ?string
    {
        $feedback = trim((string) ($row['ai_feedback'] ?? ''));

        return $feedback === '' ? null : $feedback;
    }

    /** Feedback the teacher left on the whole submission, shown next to essays. */
    private function teacherFeedback(?ExamSubmission $submission): ?string
    {
        $feedback = trim((string) ($submission?->feedback ?? ''));

        return $feedback === '' ? null : $feedback;
    }

    /**
     * AI feedback that a teacher reviewed/edited is attributed to the teacher.
     *
     * @param  array<string, mixed>|null  $row
     */
    private function isTeacherReviewed(?array $row): bool
    {
        $source = (string) ($row['ai_feedback_source'] ?? '');

        return $source !== '' && $source !== 'automatic';
    }

    /** Same normalisation ExamController applies when grading identification. */
    private function normalizeText(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? '';
        $text = preg_replace('/[^\w\s]/u', '', $text) ?? '';

        return trim($text);
    }

    private function letter(int $index): string
    {
        return $index < 26
            ? chr(65 + $index)
            : (string) ($index + 1);
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'graded' => 'Graded',
            'pending_ai' => 'Automatic AI grading pending',
            'pending_review' => 'Pending teacher grading',
            'submitted' => 'Submitted',
            null => 'Not submitted',
            default => ucfirst(str_replace('_', ' ', $status)),
        };
    }

    /**
     * @param  array<int, array<string, mixed>>  $students
     * @return array<string, mixed>|null
     */
    private function buildClassSummary(array $students): ?array
    {
        if (count($students) < 2) {
            return null;
        }

        $scores = collect($students)->pluck('summary.score')->map(fn ($score): float => (float) $score);
        $percentages = collect($students)->pluck('summary.percentage')->filter(fn ($value): bool => $value !== null);

        return [
            'count' => count($students),
            'average_score' => round((float) $scores->avg(), 2),
            'highest_score' => round((float) $scores->max(), 2),
            'lowest_score' => round((float) $scores->min(), 2),
            'average_percentage' => $percentages->isEmpty() ? null : round((float) $percentages->avg(), 1),
        ];
    }
}
