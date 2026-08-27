<?php

namespace App\Http\Controllers;

use App\Enums\EssayGradingMethod;
use App\Enums\ExamStatus;
use App\Enums\QuestionType;
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
use App\Models\User;
use App\Services\AIService;
use App\Services\ExamAnswerDraftService;
use App\Services\ExamXpAwardService;
use App\Support\AiQueueWorker;
use App\Support\ExamPartSerializer;
use App\Support\IdentificationAnswerMatcher;
use App\Support\MatchingAnswerMatcher;
use Carbon\CarbonInterface as Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Cursor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExamController extends Controller
{
    public function __construct(
        protected AIService $aiService,
        protected ExamXpAwardService $examXpAwardService,
    ) {}

    /**
     * Phase 3.6 — Fixed N+1: batch-load all user submissions for all visible
     * exams in a single query instead of one per exam inside the map.
     */
    public function index(Request $request)
    {
        $page = $this->examPage($request->user());

        return Inertia::render('Exam', [
            'examsBySeason' => $page['data'],
            'examPagination' => $page['meta'],
        ]);
    }

    /**
     * Fetch another bounded page of exam summaries.
     */
    public function listing(Request $request): JsonResponse
    {
        return response()->json($this->examPage(
            $request->user(),
            $request->query('cursor'),
        ));
    }

    /**
     * Load the answer-heavy review payload only when a student opens it.
     * The exam listing intentionally carries neither question JSON nor answers.
     */
    public function review(Request $request, Exam $exam): JsonResponse
    {
        $this->assertCanAccess($exam);

        // Results stay sealed until the exam closes. Finishing early must not
        // hand a student their paper back while classmates are still working —
        // they could pass the questions (and their answers) along.
        abort_unless(
            $request->user()->is_admin || $exam->status === 'closed',
            403,
            'Results unlock once this exam is closed.',
        );

        $submissions = ExamSubmission::query()
            ->where('user_id', $request->user()->id)
            ->where('exam_id', $exam->id)
            ->orderBy('exam_part_id')
            ->get(['id', 'exam_id', 'exam_part_id', 'answers', 'status', 'score', 'is_late', 'grading_failed']);

        abort_unless(
            $request->user()->is_admin || $submissions->isNotEmpty(),
            403,
            'There are no results to review for this exam.',
        );

        $parts = Cache::remember("exam_structure_{$exam->id}", 3600, function () use ($exam) {
            return $exam->parts()->orderBy('sort_order')->get();
        });
        $revealAnswers = ExamPartSerializer::mayRevealAnswers(
            $exam,
            (bool) $request->user()->is_admin,
            $submissions->isNotEmpty(),
        );

        return response()->json([
            'exam' => array_merge($exam->withoutRelations()->toArray(), [
                'parts' => ExamPartSerializer::many($parts, $revealAnswers),
            ]),
            'submissions' => $submissions->values()->all(),
        ]);
    }

    /**
     * Return lightweight cards in pages of 24. Full question and answer JSON is
     * deferred to review() or show(), keeping the polling response predictable.
     *
     * @return array{data: array<int, array<string, mixed>>, meta: array{hasMore: bool, nextCursor: string|null}}
     */
    private function examPage(User $user, ?string $cursor = null): array
    {
        $paginator = Exam::query()
            ->with([
                'section.season',
                'parts' => fn ($query) => $query
                    ->select(['id', 'exam_id', 'title', 'instructions', 'type', 'sort_order', 'points'])
                    ->orderBy('sort_order'),
            ])
            ->where('status', '!=', 'draft')
            ->visibleTo($user)
            ->latest('created_at')
            ->latest('id')
            ->cursorPaginate(24, ['*'], 'cursor', Cursor::fromEncoded($cursor));

        $exams = collect($paginator->items());
        $examIds = $exams->pluck('id');
        $allSubmissions = ExamSubmission::query()
            ->where('user_id', $user->id)
            ->whereIn('exam_id', $examIds)
            ->get(['id', 'exam_id', 'exam_part_id', 'status', 'score', 'is_late', 'grading_failed'])
            ->groupBy('exam_id');

        $examsData = $exams->map(function (Exam $exam) use ($allSubmissions) {
            $submissions = $allSubmissions->get($exam->id, collect());
            $submittedPartsCount = $submissions->unique('exam_part_id')->count();

            return array_merge($exam->withoutRelations()->toArray(), [
                // Cards only need part metadata and counts. Questions are the
                // dominant payload and are fetched only for taking/reviewing.
                'parts' => ExamPartSerializer::many($exam->parts, false, false),
                'submitted_parts_count' => $submittedPartsCount,
                'total_parts' => $exam->parts->count(),
                'is_locked' => ($submittedPartsCount === $exam->parts->count() && $exam->parts->isNotEmpty())
                    || $exam->status === 'closed',
                'has_submissions' => $submissions->isNotEmpty(),
                // Drives the "Review results" affordance: the student took part
                // AND the exam is closed. `is_locked` is also true for a student
                // who merely finished every part, which is not enough.
                'results_available' => $exam->status === 'closed' && $submissions->isNotEmpty(),
                'submissions' => $submissions->values()->all(),
                'section_name' => $exam->section?->name,
                'season_name' => $exam->section?->season?->name,
                'exam_date_iso' => $exam->exam_date?->toIso8601String(),
            ]);
        });

        $seasonRank = Season::query()
            ->whereIn('id', $exams->pluck('section.season_id')->filter()->unique())
            ->orderBy('start_date', 'desc')
            ->pluck('id', 'name');

        $groups = $examsData
            ->groupBy(fn ($exam) => $exam['season_name'] ?? 'Other')
            ->map(fn ($group, $seasonName) => [
                'seasonName' => $seasonName,
                'exams' => $group->values()->all(),
            ])
            ->sortBy(fn ($group) => $seasonRank->keys()->search(
                fn ($name) => $name === $group['seasonName']
            ) ?? 999)
            ->values()
            ->all();

        return [
            'data' => $groups,
            'meta' => [
                'hasMore' => $paginator->hasMorePages(),
                'nextCursor' => $paginator->nextCursor()?->encode(),
            ],
        ];
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

        // ⚠️ Answer keys are only serialized once the exam is closed AND the
        // student has actually participated. A student who never answered a
        // closed exam must not be able to open "review results" and read the
        // questions (or the key) after the fact.
        $hasSubmissions = $userSubmissions->isNotEmpty();
        $reveal = ExamPartSerializer::mayRevealAnswers(
            $exam,
            (bool) auth()->user()->is_admin,
            $hasSubmissions,
        );

        // For a closed exam the student never took, drop the questions entirely
        // so the payload carries nothing for them to review.
        $includeQuestions = $exam->status !== 'closed' || $reveal;

        return Inertia::render('Exams/Show', [
            'exam' => array_merge($exam->withoutRelations()->toArray(), [
                'parts' => ExamPartSerializer::many($parts, $reveal, $includeQuestions),
            ]),
            'submissions' => $submissions,
            'submittedPartId' => $submittedPartId,
            'partDeadlines' => $partDeadlines,
            'answerDrafts' => $answerDrafts,
            'xpAward' => $this->examXpAwardService->serialize(
                $this->examXpAwardService->awardIfEligible(auth()->user(), $exam)
            ),
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
        $this->assertCanAccess($exam);

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
        $hasAutomaticEssay = false;
        $hasManualEssay = false;

        // Create a lookup for submitted answers by question number
        $submittedAnswers = collect($answers)->keyBy('question_number');

        // Essays are graded only after the submission is safely persisted. AI
        // essays are queued; manual essays remain pending for the teacher.
        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;
            $submittedAnswer = $submittedAnswers->get($questionNumber)['answer'] ?? null;
            $questionType = QuestionType::tryFromStored($question['type'] ?? null);

            if ($this->isBlankAnswer($submittedAnswer) || $questionType !== QuestionType::Essay) {
                continue;
            }

            if (EssayGradingMethod::forQuestion($question) === EssayGradingMethod::Manual) {
                $hasManualEssay = true;
            } else {
                $hasAutomaticEssay = true;
            }
        }

        $enrichedAnswers = collect($answers)->map(function ($answer) use ($questions) {
            $questionNumber = $answer['question_number'] ?? null;

            if ($questionNumber === null) {
                return $answer;
            }

            $question = $questions[$questionNumber - 1] ?? null;

            if ($question) {
                $questionType = QuestionType::tryFromStored($question['type'] ?? null);
                $answer['question_type'] = $questionType?->value ?? QuestionType::MultipleChoice->value;
                $answer['question_text'] = $question['text'] ?? '';
                $answer['points'] = $this->questionPoints($question, (int) ($examPart->points ?? 1));

                if ($questionType === QuestionType::Essay) {
                    $answer['grading_method'] = EssayGradingMethod::forQuestion($question)->value;
                }
            }

            return $answer;
        })->keyBy('question_number');

        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;
            $questionType = QuestionType::tryFromStored($question['type'] ?? null) ?? QuestionType::MultipleChoice;
            $questionPoints = $this->questionPoints($question, (int) ($examPart->points ?? 1));
            $totalPossible += $questionPoints;

            $submittedAnswerData = $enrichedAnswers->get($questionNumber);
            $submittedAnswer = $submittedAnswerData['answer'] ?? null;

            if ($this->isBlankAnswer($submittedAnswer)) {
                continue;
            }

            if ($questionType === QuestionType::Essay) {
                // Scored asynchronously by GradeExamSubmissionEssays, which adds
                // its marks to $score once the provider responds.
                continue;
            }

            if ($questionType === QuestionType::Enumeration) {
                $score += $this->scoreEnumeration($question, $submittedAnswer);

                continue;
            }

            if ($questionType === QuestionType::Matching) {
                $score += MatchingAnswerMatcher::score($question, $submittedAnswer);

                continue;
            }

            $isCorrect = false;
            if ($questionType->usesChoiceAnswer()) {
                $correctIndex = collect($question['options'] ?? [])->search(fn ($opt) => ($opt['is_correct'] ?? false) === true);
                if ($correctIndex !== false && (int) $submittedAnswer === (int) $correctIndex) {
                    $isCorrect = true;
                }
            } elseif ($questionType === QuestionType::Identification) {
                $isCorrect = IdentificationAnswerMatcher::matches($submittedAnswer, $question);
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
                'status' => match (true) {
                    $hasAutomaticEssay => 'pending_ai',
                    $hasManualEssay => 'pending_review',
                    default => 'submitted',
                },
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
        if ($hasAutomaticEssay) {
            GradeExamSubmissionEssays::dispatch($submission->id);

            if (! app()->isProduction()) {
                // Spawns a detached `queue:work --stop-when-empty` if none is
                // running, so the teacher doesn't have to remember to start one
                // alongside `octane:start`.
                AiQueueWorker::ensureRunning();
            }
        }

        // This grants completion/on-time XP as soon as the last part exists.
        // Accuracy XP waits until every essay has finished grading.
        $this->examXpAwardService->awardIfEligible($request->user(), $exam);

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

        $awaitingTeacherReview = $submission->status === 'pending_review'
            && $this->hasManualEssayGrading($submission);

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
            && ! $awaitingTeacherReview
            && $this->hasPendingAutomaticEssayGrading($submission)) {
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

        // Automatic essay marks are visible as soon as AI finishes, even when
        // another essay in the same part is still waiting for manual grading.
        $automaticEssays = collect($submission->answers ?? [])
            ->filter(fn ($answer): bool => is_array($answer)
                && QuestionType::tryFromStored($answer['question_type'] ?? null) === QuestionType::Essay
                && EssayGradingMethod::forAnswer($answer) === EssayGradingMethod::Ai
                && trim((string) ($answer['answer'] ?? '')) !== '');
        $essaysScored = $submission->status === 'graded'
            || ($automaticEssays->isNotEmpty()
                && $automaticEssays->every(fn ($answer): bool => array_key_exists('ai_score', $answer)));

        $xpAward = $this->examXpAwardService->awardIfEligible($request->user(), $exam);

        return response()->json([
            'status' => $submission->status,
            'scored' => $essaysScored,
            'score' => $essaysScored ? (float) $submission->score : null,
            'is_late' => (bool) $submission->is_late,
            'grading_failed' => (bool) $submission->grading_failed,
            'awaiting_teacher_review' => $awaitingTeacherReview,
            'xp_award' => $this->examXpAwardService->serialize($xpAward),
        ]);
    }

    /**
     * Return the maximum points for a question, including each Enumeration item.
     *
     * @param  array<string, mixed>  $question
     */
    private function questionPoints(array $question, int $default): float
    {
        $type = QuestionType::tryFromStored($question['type'] ?? null);

        if ($type === QuestionType::Enumeration) {
            return (float) collect($question['enumeration_items'] ?? [])
                ->filter(fn ($item): bool => is_array($item))
                ->sum(fn (array $item): float => (float) ($item['points'] ?? 0));
        }

        if ($type === QuestionType::Matching) {
            return MatchingAnswerMatcher::maxPoints($question);
        }

        return (float) ($question['points'] ?? $default);
    }

    /**
     * Score each submitted Enumeration item at most once, regardless of order.
     *
     * @param  array<string, mixed>  $question
     */
    private function scoreEnumeration(array $question, mixed $submittedAnswer): float
    {
        if (! is_array($submittedAnswer)) {
            return 0.0;
        }

        $expectedItems = collect($question['enumeration_items'] ?? [])
            ->filter(fn ($item): bool => is_array($item))
            ->map(fn (array $item): array => [
                'answer' => $this->normalizeEnumerationText((string) ($item['answer'] ?? '')),
                'points' => (float) ($item['points'] ?? 0),
            ])
            ->filter(fn (array $item): bool => $item['answer'] !== '')
            ->values();
        $matchedIndexes = [];
        $score = 0.0;

        foreach ($submittedAnswer as $answer) {
            $normalizedAnswer = $this->normalizeEnumerationText((string) $answer);
            if ($normalizedAnswer === '') {
                continue;
            }

            foreach ($expectedItems as $expectedIndex => $expectedItem) {
                if (in_array($expectedIndex, $matchedIndexes, true)) {
                    continue;
                }

                if ($normalizedAnswer === $expectedItem['answer']) {
                    $score += $expectedItem['points'];
                    $matchedIndexes[] = $expectedIndex;

                    break;
                }
            }
        }

        return $score;
    }

    private function isBlankAnswer(mixed $answer): bool
    {
        if (is_array($answer)) {
            return count($answer) === 0 || collect($answer)->every(fn ($item): bool => $this->isBlankAnswer($item));
        }

        return $answer === null || (is_string($answer) && trim($answer) === '');
    }

    private function normalizeEnumerationText(string $text): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/\\s+/', ' ', $text) ?? '';
        $text = preg_replace('/[^\\w\\s]/u', '', $text) ?? '';

        return trim($text);
    }

    /**
     * A student may only touch exams that are unassigned or in one of their sections.
     *
     * The check reads the `section_user` pivot directly: the workspace-scoped
     * `sections()` relation can miss legitimate enrollments when the student's
     * workspace bookkeeping is absent or points at another tenant.
     */
    private function assertCanAccess(Exam $exam): void
    {
        $user = auth()->user();

        if ($user->is_admin || ! $exam->section_id) {
            return;
        }

        abort_unless(
            DB::table('section_user')
                ->where('user_id', $user->id)
                ->where('section_id', $exam->section_id)
                ->exists(),
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

    /** Whether any non-blank automatic essay still needs an AI result. */
    private function hasPendingAutomaticEssayGrading(ExamSubmission $submission): bool
    {
        foreach ($submission->answers ?? [] as $answer) {
            if (
                ! is_array($answer)
                || ($answer['question_type'] ?? null) !== 'essay'
                || EssayGradingMethod::forAnswer($answer) !== EssayGradingMethod::Ai
                || trim((string) ($answer['answer'] ?? '')) === ''
            ) {
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

    /** Whether this submission contains a non-blank teacher-graded essay. */
    private function hasManualEssayGrading(ExamSubmission $submission): bool
    {
        return collect($submission->answers ?? [])->contains(
            fn ($answer): bool => is_array($answer)
                && QuestionType::tryFromStored($answer['question_type'] ?? null) === QuestionType::Essay
                && EssayGradingMethod::forAnswer($answer) === EssayGradingMethod::Manual
                && trim((string) ($answer['answer'] ?? '')) !== '',
        );
    }
}
