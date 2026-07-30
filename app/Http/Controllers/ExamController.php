<?php

namespace App\Http\Controllers;

use App\Http\Requests\SubmitExamPartRequest;
use App\Jobs\GradeExamSubmissionEssays;
use App\Models\Exam;
use App\Models\ExamLiveSession;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Services\AIService;
use App\Support\AiQueueWorker;
use App\Support\ExamPartSerializer;
use Carbon\CarbonInterface as Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExamController extends Controller
{
    public function __construct(protected AIService $aiService) {}

    /**
     * Phase 3.6 — Fixed N+1: batch-load all user submissions for all visible
     * exams in a single query instead of one per exam inside the map.
     */
    public function index()
    {
        $user = auth()->user();
        $exams = Exam::with([
            'section.season',
            'parts' => function ($query) {
                $query->orderBy('sort_order');
            },
        ])
            ->where('status', '!=', 'draft')
            ->when(! $user->is_admin, function ($query) use ($user) {
                $sectionIds = $user->sections()->pluck('sections.id')->toArray();
                $query->where(function ($query) use ($sectionIds) {
                    $query->whereNull('section_id')
                        ->orWhereIn('section_id', $sectionIds);
                });
            })
            ->latest()
            ->get();

        // Phase 3.6 — batch-load all user submissions in one query
        $examIds = $exams->pluck('id');
        $allSubmissions = ExamSubmission::where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->get()
            ->groupBy('exam_id');

        $examsData = $exams->map(function (Exam $exam) use ($allSubmissions) {
            $submissions = $allSubmissions->get($exam->id, collect());

            $submittedPartsCount = $submissions->unique('exam_part_id')->count();

            $seasonName = $exam->section?->season?->name;

            // ⚠️ Answer keys are only serialized once the exam is closed.
            $reveal = ExamPartSerializer::mayRevealAnswers($exam);

            return array_merge($exam->withoutRelations()->toArray(), [
                'parts' => ExamPartSerializer::many($exam->parts, $reveal),
                'submitted_parts_count' => $submittedPartsCount,
                'total_parts' => $exam->parts->count(),
                'is_locked' => ($submittedPartsCount === $exam->parts->count() && $exam->parts->count() > 0) || $exam->status === 'closed',
                'submissions' => $submissions->toArray(),
                'section_name' => $exam->section?->name,
                'season_name' => $seasonName,
                'exam_date_iso' => $exam->exam_date?->toIso8601String(),
            ]);
        });

        // Group exams by season name, ordered by most recent season first
        $seasonRank = Season::query()
            ->whereIn('id', $exams->pluck('section.season_id')->filter()->unique())
            ->orderBy('start_date', 'desc')
            ->pluck('id', 'name');

        $examsBySeason = collect($examsData)
            ->groupBy(fn ($e) => $e['season_name'] ?? 'Other')
            ->map(fn ($exams, $seasonName) => [
                'seasonName' => $seasonName,
                'exams' => $exams->values()->all(),
            ])
            ->sortBy(fn ($group) => $seasonRank->keys()->search(fn ($name) => $name === $group['seasonName']) ?? 999)
            ->values()
            ->all();

        return Inertia::render('Exam', [
            'examsBySeason' => $examsBySeason,
        ]);
    }

    public function show(Exam $exam)
    {
        $this->assertCanAccess($exam);

        if ($exam->status === 'draft') {
            abort(404);
        }

        // Cache only the raw structure. The serialized payload is per-user
        // (answer visibility depends on the viewer), so it must never be cached.
        $parts = Cache::remember("exam_structure_{$exam->id}", 3600, function () use ($exam) {
            return $exam->parts()->orderBy('sort_order')->get();
        });

        $userId = auth()->id();
        $submissions = ExamSubmission::where('user_id', $userId)
            ->where('exam_id', $exam->id)
            ->get(['exam_part_id', 'status', 'score'])
            ->mapWithKeys(function ($item) {
                return [(string) $item['exam_part_id'] => $item];
            })
            ->toArray();

        // Check if we just submitted a part (from flash session)
        $submittedPartId = session()->pull('submitted_part');

        $reveal = ExamPartSerializer::mayRevealAnswers($exam);

        return Inertia::render('Exams/Show', [
            'exam' => array_merge($exam->withoutRelations()->toArray(), [
                'parts' => ExamPartSerializer::many($parts, $reveal),
            ]),
            'submissions' => $submissions,
            'submittedPartId' => $submittedPartId,
        ]);
    }

    /**
     * Start (or resume) the server-side clock for a part.
     *
     * `started_at` is written once and never overwritten, so a student cannot
     * reset their own timer by reloading the page.
     */
    public function startPart(Request $request, Exam $exam, ExamPart $examPart)
    {
        abort_if($examPart->exam_id !== $exam->id, 404);

        $this->assertCanAccess($exam);

        abort_if($exam->status === 'closed', 403, 'This exam is currently closed.');

        $session = ExamLiveSession::firstOrCreate(
            [
                'user_id' => $request->user()->id,
                'exam_id' => $exam->id,
                'exam_part_id' => $examPart->id,
            ],
            [
                'status' => 'in_progress',
                'started_at' => now(),
                'last_seen_at' => now(),
            ],
        );

        if (! $session->started_at) {
            $session->forceFill(['started_at' => now()])->save();
        }

        return response()->json([
            'started_at' => $session->started_at?->toIso8601String(),
            'deadline' => $this->deadlineFor($exam, $session)?->toIso8601String(),
        ]);
    }

    public function preWarmAI()
    {
        $this->aiService->preWarm();

        return response()->json(['status' => 'ok']);
    }

    public function monitorProgress(Request $request, Exam $exam)
    {
        $validated = $request->validate([
            'status' => ['required', 'string', Rule::in(['starting', 'in_progress', 'submitting', 'finished'])],
            'exam_part_id' => [
                'nullable',
                Rule::exists('exam_parts', 'id')->where(fn ($query) => $query->where('exam_id', $exam->id)),
            ],
            'submitted_parts_count' => ['required', 'integer', 'min:0'],
            'current_part_answered_count' => ['required', 'integer', 'min:0'],
            'current_part_total_questions' => ['required', 'integer', 'min:0'],
        ]);

        if ($validated['status'] === 'finished') {
            ExamLiveSession::query()
                ->where('user_id', $request->user()->id)
                ->where('exam_id', $exam->id)
                ->delete();

            return response()->json(['ok' => true]);
        }

        // The unique key is now (user_id, exam_id, exam_part_id) so the clock is
        // per part. `started_at` is intentionally NOT written here — only
        // startPart() may set it, otherwise a ping would reset the countdown.
        ExamLiveSession::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'exam_id' => $exam->id,
                'exam_part_id' => $validated['exam_part_id'] ?? null,
            ],
            [
                'status' => $validated['status'],
                'submitted_parts_count' => $validated['submitted_parts_count'],
                'current_part_answered_count' => $validated['current_part_answered_count'],
                'current_part_total_questions' => $validated['current_part_total_questions'],
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function submitPart(SubmitExamPartRequest $request, Exam $exam, ExamPart $examPart)
    {
        abort_if($examPart->exam_id !== $exam->id, 404);

        // Prevent submissions if exam is closed
        if ($exam->status === 'closed') {
            abort(403, 'This exam is currently closed.');
        }

        // Phase 1.5 — the student must actually have access to this exam.
        $this->assertCanAccess($exam);

        // Phase 1.3 — single attempt per part.
        $alreadySubmitted = ExamSubmission::where('user_id', $request->user()->id)
            ->where('exam_id', $exam->id)
            ->where('exam_part_id', $examPart->id)
            ->exists();

        abort_if($alreadySubmitted, 409, 'You have already submitted this part.');

        // Phase 1.4 — late submissions are accepted, then flagged for the teacher.
        $isLate = $this->isLate($request->user()->id, $exam, $examPart);

        $validated = $request->validated();

        // Calculate score
        $score = 0;
        $totalPossible = 0;
        $questions = is_array($examPart->questions) ? $examPart->questions : $examPart->questions ?? [];
        $answers = $validated['answers'];
        $hasEssay = false;

        // Create a lookup for submitted answers by question number
        $submittedAnswers = collect($answers)->keyBy('question_number');

        // ⚠️ Phase 1.0.2 — essays are NOT graded here.
        //
        // The AI provider is a ≤45s network call per essay, fired concurrently
        // via Http::pool. Doing that inline held a RoadRunner worker for the
        // whole duration and, because the save happened afterwards, a request
        // that died mid-call lost the student's answers entirely.
        //
        // We only detect that essays exist; GradeExamSubmissionEssays scores
        // them after the submission is safely persisted.
        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;
            $submittedAnswer = $submittedAnswers->get($questionNumber)['answer'] ?? null;

            if ($submittedAnswer !== null && $submittedAnswer !== '' && $question['type'] === 'essay') {
                $hasEssay = true;
                break;
            }
        }

        $enrichedAnswers = collect($answers)->map(function ($answer) use ($questions) {
            $questionNumber = $answer['question_number'] ?? null;

            if ($questionNumber === null) {
                return $answer;
            }

            $question = $questions[$questionNumber - 1] ?? null;

            if ($question) {
                $answer['question_type'] = $question['type'] ?? '';
                $answer['question_text'] = $question['text'] ?? '';
                $answer['points'] = (int) ($question['points'] ?? 1);
            }

            return $answer;
        })->keyBy('question_number');

        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;
            $questionPoints = (int) ($question['points'] ?? $examPart->points ?? 1);
            $totalPossible += $questionPoints;

            $submittedAnswerData = $enrichedAnswers->get($questionNumber);
            $submittedAnswer = $submittedAnswerData['answer'] ?? null;

            if ($submittedAnswer === null) {
                continue;
            }

            if ($question['type'] === 'essay') {
                // Scored asynchronously by GradeExamSubmissionEssays, which adds
                // its marks to $score once the provider responds.
                continue;
            }

            $isCorrect = false;
            if ($question['type'] === 'multiple_choice' || $question['type'] === 'true_false') {
                $correctIndex = collect($question['options'] ?? [])->search(fn ($opt) => ($opt['is_correct'] ?? false) === true);
                if ($correctIndex !== false && (int) $submittedAnswer === (int) $correctIndex) {
                    $isCorrect = true;
                }
            } elseif ($question['type'] === 'identification') {
                $normalize = function (string $text): string {
                    // Convert to lowercase, trim, collapse multiple spaces, remove common punctuation
                    $text = strtolower(trim($text));
                    $text = preg_replace('/\s+/', ' ', $text); // collapse multiple spaces
                    $text = preg_replace('/[^\w\s]/u', '', $text); // remove punctuation except word chars and spaces

                    return trim($text);
                };

                $normalizedSubmitted = $normalize((string) $submittedAnswer);
                $normalizedCorrect = $normalize((string) ($question['correct_answer'] ?? ''));

                if ($normalizedSubmitted === $normalizedCorrect) {
                    $isCorrect = true;
                }
            }

            if ($isCorrect) {
                $score += $questionPoints;
            }
        }

        // Single attempt per part, so this is always a create.
        $submission = ExamSubmission::create([
            'user_id' => $request->user()->id,
            'exam_id' => $exam->id,
            'exam_part_id' => $examPart->id,
            'answers' => json_encode($enrichedAnswers->values()->toArray()),
            'status' => $hasEssay ? 'pending_review' : 'submitted',
            'is_late' => $isLate,
            'score' => $score,
        ]);

        // The clock for this part is done.
        ExamLiveSession::where('user_id', $request->user()->id)
            ->where('exam_id', $exam->id)
            ->where('exam_part_id', $examPart->id)
            ->delete();

        // Answers are now durable. Grade the essays off-request.
        if ($hasEssay) {
            GradeExamSubmissionEssays::dispatch($submission->id);

            // Spawns a detached `queue:work --stop-when-empty` if none is
            // running, so the teacher doesn't have to remember to start one
            // alongside `octane:start`.
            AiQueueWorker::ensureRunning();
        }

        return redirect('/exams/'.$exam->id)->with('submitted_part', $examPart->id);
    }

    /**
     * Poll the grading state of a submitted part.
     *
     * Lets the frontend show an honest "grading your essays" indicator instead
     * of holding the HTTP request open while the AI provider works.
     */
    public function partStatus(Request $request, Exam $exam, ExamPart $examPart)
    {
        abort_if($examPart->exam_id !== $exam->id, 404);

        $this->assertCanAccess($exam);

        $submission = ExamSubmission::where('user_id', $request->user()->id)
            ->where('exam_id', $exam->id)
            ->where('exam_part_id', $examPart->id)
            ->first(['id', 'status', 'score', 'is_late', 'grading_failed', 'answers']);

        if (! $submission) {
            return response()->json(['status' => 'not_submitted']);
        }

        // `scored` means the AI has finished marking. Automatic feedback is
        // produced by the same queued job and successful submissions become
        // `graded`; the student's progress indicator should stop at `scored`.
        $essaysScored = $submission->status === 'graded'
            || collect($submission->answers ?? [])
                ->where('question_type', 'essay')
                ->every(fn ($answer) => array_key_exists('ai_score', $answer));

        return response()->json([
            'status' => $submission->status,
            'scored' => $essaysScored,
            'score' => $essaysScored ? (float) $submission->score : null,
            'is_late' => (bool) $submission->is_late,
            'grading_failed' => (bool) $submission->grading_failed,
        ]);
    }

    /**
     * A student may only touch exams that are unassigned or in one of their sections.
     */
    private function assertCanAccess(Exam $exam): void
    {
        $user = auth()->user();

        if ($user->is_admin || ! $exam->section_id) {
            return;
        }

        abort_unless(
            $user->sections()->where('sections.id', $exam->section_id)->exists(),
            403,
            'You do not have access to this exam.',
        );
    }

    /**
     * Whether the student is past the per-part deadline.
     *
     * Returns false when no clock was ever started, so a missing session can
     * never cost a student their marks.
     */
    private function isLate(int $userId, Exam $exam, ExamPart $examPart): bool
    {
        $session = ExamLiveSession::where('user_id', $userId)
            ->where('exam_id', $exam->id)
            ->where('exam_part_id', $examPart->id)
            ->first();

        $deadline = $this->deadlineFor($exam, $session);

        return $deadline !== null && now()->greaterThan($deadline);
    }

    private function deadlineFor(Exam $exam, ?ExamLiveSession $session): ?Carbon
    {
        if (! $session?->started_at || ! $exam->duration_minutes) {
            return null;
        }

        // 30s grace absorbs clock skew and slow uploads on a LAN.
        return $session->started_at
            ->copy()
            ->addMinutes($exam->duration_minutes)
            ->addSeconds(30);
    }
}
