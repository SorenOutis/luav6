<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use App\Services\AIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class ExamController extends Controller
{
    public function __construct(protected AIService $aiService) {}

    public function index()
    {
        $user = auth()->user();
        $exams = Exam::with([
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
            ->get();

        // Get submission counts and details for the current user
        $userId = $user->id;
        $examsData = $exams->map(function (Exam $exam) use ($userId) {
            $submissions = ExamSubmission::where('user_id', $userId)
                ->where('exam_id', $exam->id)
                ->get();

            $submittedPartsCount = $submissions->unique('exam_part_id')->count();

            return array_merge($exam->toArray(), [
                'submitted_parts_count' => $submittedPartsCount,
                'total_parts' => $exam->parts->count(),
                'is_locked' => ($submittedPartsCount === $exam->parts->count() && $exam->parts->count() > 0) || $exam->status === 'closed',
                'submissions' => $submissions->toArray(),
            ]);
        });

        return Inertia::render('Exam', [
            'exams' => $examsData,
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
            ->keyBy('exam_part_id')
            ->toArray();

        return Inertia::render('Exams/Show', [
            'exam' => $exam,
            'submissions' => $submissions,
        ]);
    }

    public function preWarmAI()
    {
        $this->aiService->preWarm();

        return response()->json(['status' => 'ok']);
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

        // Batch process essays if any
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
                $assessment = $essayAssessments[$questionNumber] ?? ['score' => 0.0, 'feedback' => ''];

                // Add AI score to the total score
                $score += $assessment['score'];

                // Update the answer data with AI results
                if ($submittedAnswerData) {
                    $submittedAnswers[$questionNumber] = array_merge($submittedAnswerData, [
                        'ai_score' => $assessment['score'],
                        'ai_feedback' => $assessment['feedback'],
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
                if (trim(strtolower($submittedAnswer)) === trim(strtolower($question['correct_answer'] ?? ''))) {
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

        return redirect()->back();
    }
}
