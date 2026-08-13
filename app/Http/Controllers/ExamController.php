<?php

namespace App\Http\Controllers;

use App\Enums\ExamStatus;
use App\Events\ExamAnswersSaved;
use App\Http\Requests\SaveExamAnswersRequest;
use App\Http\Requests\SubmitExamPartRequest;
use App\Jobs\GradeExamSubmissionEssays;
use App\Models\Exam;
use App\Models\ExamAnswerDraft;
use App\Models\ExamLiveSession;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Services\AIService;
use App\Services\ExamAnswerDraftService;
use App\Support\AiQueueWorker;
use App\Support\ExamPartSerializer;
use Carbon\CarbonInterface as Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
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
        $userSubmissions = ExamSubmission::where('user_id', $userId)
            ->where('exam_id', $exam->id)
            ->get(['exam_part_id', 'status', 'score']);
        $submittedPartIds = $userSubmissions->pluck('exam_part_id');
        $submissions = $userSubmissions
            ->mapWithKeys(function ($item) {
                return [(string) $item['exam_part_id'] => $item];
            })
            ->toArray();

        // Active per-part clocks the student has started but not yet submitted.
        // The frontend resumes the countdown from these authoritative deadlines
        // on reload instead of resetting to a fresh `duration_minutes`.
        $partDeadlines = ExamLiveSession::where('user_id', $userId)
            ->where('exam_id', $exam->id)
            ->whereNotNull('exam_part_id')
            ->whereNotNull('started_at')
            ->whereNotIn('exam_part_id', $submittedPartIds)
            ->get()
            ->mapWithKeys(fn (ExamLiveSession $session) => [
                (string) $session->exam_part_id => $this->deadlineFor($exam, $session)?->toIso8601String(),
            ])
            ->filter()
            ->toArray();

        // Database-backed drafts survive reloads, browser storage clearing, and
        // device crashes. Only the authenticated student's own drafts are sent.
        $answerDrafts = ExamAnswerDraft::where('user_id', $userId)
            ->where('exam_id', $exam->id)
            ->whereNotIn('exam_part_id', $submittedPartIds)
            ->get(['exam_part_id', 'answers', 'saved_at'])
            ->mapWithKeys(fn (ExamAnswerDraft $draft) => [
                (string) $draft->exam_part_id => [
                    'answers' => $draft->answers ?? [],
                    'saved_at' => $draft->saved_at?->toIso8601String(),
                ],
            ])
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
            'partDeadlines' => $partDeadlines,
            'answerDrafts' => $answerDrafts,
            'realtimeChannel' => "exam.{$exam->id}.student.{$userId}",
        ]);
    }

    /**
     * Persist changed answers before the student submits the part.
     *
     * The HTTP write is the durable operation. Pusher broadcasts a private,
     * answer-free acknowledgement after the database commit so the UI can
     * update in real time without exposing response text over the channel.
     */
    public function saveAnswers(
        SaveExamAnswersRequest $request,
        Exam $exam,
        ExamPart $examPart,
        ExamAnswerDraftService $draftService,
    ): JsonResponse {
        abort_if($examPart->exam_id !== $exam->id, 404);

        $this->assertCanAccess($exam);
        abort_unless(
            ExamStatus::tryFrom($exam->status)?->acceptsSubmissions(),
            403,
            'This exam is not accepting answers.',
        );

        $alreadySubmitted = ExamSubmission::where('user_id', $request->user()->id)
            ->where('exam_id', $exam->id)
            ->where('exam_part_id', $examPart->id)
            ->exists();

        abort_if($alreadySubmitted, 409, 'You have already submitted this part.');

        $validated = $request->validated();
        $changedAnswers = $validated['answers'];
        $draft = $draftService->save(
            $request->user(),
            $exam,
            $examPart,
            $changedAnswers,
        );
        $answeredCount = $draftService->answeredCount($draft);
        $savedAt = $draft->saved_at->toIso8601String();
        $questionNumbers = collect($changedAnswers)
            ->pluck('question_number')
            ->map(fn (mixed $questionNumber): int => (int) $questionNumber)
            ->values()
            ->all();

        ExamAnswersSaved::dispatch(
            $request->user()->id,
            $exam->id,
            $examPart->id,
            $questionNumbers,
            $answeredCount,
            $savedAt,
        );

        return response()->json([
            'saved_at' => $savedAt,
            'question_numbers' => $questionNumbers,
            'answered_count' => $answeredCount,
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

        // Single attempt per part, so this is always a create. The
        // (user_id, exam_id, exam_part_id) unique index closes the TOCTOU gap
        // the exists() check above leaves open: a concurrent duplicate insert
        // fails here and is surfaced as the same 409 the guard returns.
        try {
            $submission = ExamSubmission::create([
                'user_id' => $request->user()->id,
                'exam_id' => $exam->id,
                'exam_part_id' => $examPart->id,
                // JSON_INVALID_UTF8_SUBSTITUTE: a pasted/legacy string with
                // broken UTF-8 used to make json_encode() return false, which
                // then crashed the answers cast and lost the ENTIRE part's
                // answers — the submission never persisted and the student
                // had to answer everything again. Invalid bytes are now
                // replaced instead of silently dropping the payload.
                'answers' => json_encode(
                    $enrichedAnswers->values()->toArray(),
                    JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE
                ),
                'status' => $hasEssay ? 'pending_review' : 'submitted',
                'is_late' => $isLate,
                'score' => round((float) $score, 2),
            ]);
        } catch (QueryException $e) {
            // Only a genuine duplicate attempt (the unique index) is a 409.
            // Any other DB failure (e.g. the score exceeding the decimal(5,2)
            // column range) used to be masked as "already submitted" — the
            // frontend showed no error, the part stayed open, and the student
            // had to answer the whole part again. Rethrow real failures so
            // they surface in the logs and the student sees an error instead.
            $alreadyRecorded = ExamSubmission::where('user_id', $request->user()->id)
                ->where('exam_id', $exam->id)
                ->where('exam_part_id', $examPart->id)
                ->exists();

            if ($alreadyRecorded) {
                abort(409, 'You have already submitted this part.');
            }

            throw $e;
        }

        // The clock for this part is done.
        ExamLiveSession::where('user_id', $request->user()->id)
            ->where('exam_id', $exam->id)
            ->where('exam_part_id', $examPart->id)
            ->delete();
        ExamAnswerDraft::where('user_id', $request->user()->id)
            ->where('exam_id', $exam->id)
            ->where('exam_part_id', $examPart->id)
            ->delete();

        // Answers are now durable. Grade the essays asynchronously in every
        // environment: the submission is already persisted (Phase 1.0.2), so a
        // dead request can no longer lose work, and the request returns
        // instantly instead of holding a RoadRunner worker for a ≤45s AI
        // provider call.
        //
        // Production relies on the persistent queue worker started by the
        // Docker CMD (`queue:work --queue=ai --processes=4`). The on-demand
        // spawner (AiQueueWorker) stays a local-dev convenience only — spawning
        // OS processes inside the container is not wanted. Anything that still
        // slips through (e.g. submissions created before this change, or a
        // worker outage) is healed by partStatus() on the next poll.
        if ($hasEssay) {
            GradeExamSubmissionEssays::dispatch($submission->id);

            if (! app()->isProduction()) {
                // Spawns a detached `queue:work --stop-when-empty` if none is
                // running, so the teacher doesn't have to remember to start one
                // alongside `octane:start`.
                AiQueueWorker::ensureRunning();
            }
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

        // Self-heal: submissions left pending (created before async grading
        // shipped, or whenever no queue worker is consuming the "ai" queue)
        // are re-queued here. The modal polls this endpoint every 2s, so a
        // stuck "Reviewing your essay..." resolves by itself.
        //
        // ⚠️ This used to run the job inline with dispatchSync(), which held
        // the poll request (and a worker) open for the entire ≤45s AI call.
        // Worse, the 60s cache lock expired mid-call for slow providers, so
        // the next poll acquired it and fired a SECOND concurrent grading run
        // — duplicate AI calls that could trip the provider's own rate limit,
        // and a provider that hung left the poll stuck forever. Dispatching to
        // the "ai" queue keeps the poll non-blocking. The lock is held (not
        // manually released) for the whole grading window so concurrent polls
        // can't queue duplicates, and the job itself is idempotent.
        if (in_array($submission->status, ['pending_review', 'pending_ai'], true)
            && ! $submission->grading_failed
            && $this->hasPendingEssayGrading($submission)) {
            // Matches GradeExamSubmissionEssays::$timeout (300s) so the lock
            // can never expire while the job is still running.
            $lock = Cache::lock('essay_grading_'.$submission->id, 300);

            if ($lock->get()) {
                GradeExamSubmissionEssays::dispatch($submission->id);

                if (! app()->isProduction()) {
                    // Spawn a detached `queue:work --stop-when-empty` so the
                    // re-queued job actually runs on a local dev machine.
                    AiQueueWorker::ensureRunning();
                }
            }
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

    /**
     * Whether any non-blank essay answer in the submission is still awaiting
     * AI grading (no score or no feedback yet).
     */
    private function hasPendingEssayGrading(ExamSubmission $submission): bool
    {
        foreach ($submission->answers ?? [] as $answer) {
            if (! is_array($answer) || ($answer['question_type'] ?? null) !== 'essay') {
                continue;
            }

            $text = trim((string) ($answer['answer'] ?? ''));
            if ($text === '') {
                continue;
            }

            $hasScore = array_key_exists('ai_score', $answer);
            $hasFeedback = trim((string) ($answer['ai_feedback'] ?? '')) !== '';

            if (! ($hasScore && $hasFeedback)) {
                return true;
            }
        }

        return false;
    }
}
