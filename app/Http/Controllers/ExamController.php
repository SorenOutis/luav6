<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamLiveSession;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Models\Season;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ExamController extends Controller
{
    public function __construct(protected AIService $aiService) {}

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

        // Get submission counts and details for the current user
        $userId = $user->id;
        $examsData = $exams->map(function (Exam $exam) use ($userId) {
            $submissions = ExamSubmission::where('user_id', $userId)
                ->where('exam_id', $exam->id)
                ->get();

            $submittedPartsCount = $submissions->unique('exam_part_id')->count();

            $seasonName = $exam->section?->season?->name;

            return array_merge($exam->toArray(), [
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
        $user = auth()->user();

        // Check section access
        if (! $user->is_admin && $exam->section_id && ! $user->sections()->where('sections.id', $exam->section_id)->exists()) {
            abort(403, 'You do not have access to this exam.');
        }

        // Cache the exam structure for 1 hour to optimize LAN traffic
        $exam = Cache::remember("exam_structure_{$exam->id}", 3600, function () use ($exam) {
            return $exam->load([
                'parts' => function ($query) {
                    $query->orderBy('sort_order');
                },
            ]);
        });

        if ($exam->status === 'draft') {
            abort(404);
        }

        // Get submissions for the current user (don't cache this as it's user-specific)
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

        return Inertia::render('Exams/Show', [
            'exam' => $exam,
            'submissions' => $submissions,
            'submittedPartId' => $submittedPartId,
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

        ExamLiveSession::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'exam_id' => $exam->id,
            ],
            [
                'exam_part_id' => $validated['exam_part_id'] ?? null,
                'status' => $validated['status'],
                'submitted_parts_count' => $validated['submitted_parts_count'],
                'current_part_answered_count' => $validated['current_part_answered_count'],
                'current_part_total_questions' => $validated['current_part_total_questions'],
                'last_seen_at' => now(),
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function submitPart(Request $request, Exam $exam, ExamPart $examPart)
    {
        // Prevent submissions if exam is closed
        if ($exam->status === 'closed') {
            abort(403, 'This exam is currently closed.');
        }

        // Validate the request
        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        // Calculate score
        $score = 0;
        $totalPossible = 0;
        $questions = is_array($examPart->questions) ? $examPart->questions : $examPart->questions ?? [];
        $answers = $validated['answers'];
        $hasEssay = false;

        // Create a lookup for submitted answers by question number
        $submittedAnswers = collect($answers)->keyBy('question_number');

        // Collect essays for batch processing
        $essaysToProcess = [];
        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;
            $submittedAnswerData = $submittedAnswers->get($questionNumber);
            $submittedAnswer = $submittedAnswerData['answer'] ?? null;

            if ($submittedAnswer !== null && $question['type'] === 'essay') {
                $hasEssay = true;
                $essaysToProcess[$questionNumber] = [
                    'essayText' => (string) $submittedAnswer,
                    'questionText' => (string) $question['text'],
                    'maxPoints' => (int) ($question['points'] ?? $examPart->points ?? 1),
                ];
            }
        }

        // Batch process essays (always score and feedback)
        $essayAssessments = [];
        if (! empty($essaysToProcess)) {
            $essayAssessments = $this->aiService->batchAssessEssays($essaysToProcess);
        }

        foreach ($questions as $index => $question) {
            $questionNumber = $index + 1;
            $questionPoints = (int) ($question['points'] ?? $examPart->points ?? 1);
            $totalPossible += $questionPoints;

            $submittedAnswerData = $submittedAnswers->get($questionNumber);
            $submittedAnswer = $submittedAnswerData['answer'] ?? null;

            if ($submittedAnswer === null) {
                continue;
            }

            if ($question['type'] === 'essay') {
                $assessment = $essayAssessments[$questionNumber] ?? ['score' => 0.0];

                // Always add AI score
                $score += $assessment['score'];

                if ($submittedAnswerData) {
                    $submittedAnswers[$questionNumber] = array_merge($submittedAnswerData, [
                        'ai_score' => $assessment['score'],
                    ]);
                }

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

        // Create or update submission
        $submission = ExamSubmission::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'exam_id' => $exam->id,
                'exam_part_id' => $examPart->id,
            ],
            [
                'answers' => json_encode($submittedAnswers->values()->toArray()),
                'status' => $hasEssay ? 'pending_review' : 'submitted',
                'score' => $score,
            ]
        );

        return redirect('/exams/'.$exam->id)->with('submitted_part', $examPart->id);
    }
}
