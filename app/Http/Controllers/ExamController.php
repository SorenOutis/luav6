<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Exam;
use App\Models\ExamPart;
use App\Models\ExamSubmission;
use Inertia\Inertia;

class ExamController extends Controller
{
    public function index()
    {
        $exams = Exam::with([
            'parts' => function ($query) {
                $query->orderBy('sort_order');
            }
        ])->where('status', '!=', 'draft')->get();

        // Get submission counts for the current user
        $userId = auth()->id();
        $examsData = $exams->map(function (\App\Models\Exam $exam) use ($userId) {
            $submittedPartsCount = ExamSubmission::where('user_id', $userId)
                ->where('exam_id', $exam->id)
                ->distinct('exam_part_id')
                ->count();
            
            return array_merge($exam->toArray(), [
                'submitted_parts_count' => $submittedPartsCount,
                'total_parts' => $exam->parts->count(),
                'is_locked' => ($submittedPartsCount === $exam->parts->count() && $exam->parts->count() > 0) || $exam->status === 'closed',
            ]);
        });

        return Inertia::render('Exam', [
            'exams' => $examsData,
        ]);
    }

    public function show(Exam $exam)
    {
        $exam->load([
            'parts' => function ($query) {
                $query->orderBy('sort_order');
            }
        ]);

        if ($exam->status === 'draft') {
            abort(404);
        }

        // Get submissions for the current user
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

        foreach ($questions as $index => $question) {
            $questionPoints = (int) ($question['points'] ?? $examPart->points ?? 1);
            $totalPossible += $questionPoints;

            if ($question['type'] === 'essay') {
                $hasEssay = true;
                continue;
            }
            
            // Find the answer for this question number (offset 1)
            $submittedAnswer = collect($answers)->firstWhere('question_number', $index + 1)['answer'] ?? null;
            
            if ($submittedAnswer === null) continue;

            $isCorrect = false;
            if ($question['type'] === 'multiple_choice' || $question['type'] === 'true_false') {
                $correctIndex = collect($question['options'] ?? [])->search(fn($opt) => ($opt['is_correct'] ?? false) === true);
                if ($correctIndex !== false && (int)$submittedAnswer === (int)$correctIndex) {
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
                'answers' => json_encode($validated['answers']),
                'status' => $hasEssay ? 'pending_review' : 'submitted',
                'score' => $score,
            ]
        );

        return redirect()->back();
    }
}
