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
        $examsData = $exams->map(function ($exam) use ($userId) {
            $submittedPartsCount = ExamSubmission::where('user_id', $userId)
                ->where('exam_id', $exam->id)
                ->distinct('exam_part_id')
                ->count();
            
            return [
                ...($exam->toArray()),
                'submitted_parts_count' => $submittedPartsCount,
                'total_parts' => count($exam->parts),
                'is_locked' => $submittedPartsCount === count($exam->parts) && count($exam->parts) > 0,
            ];
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
            ->pluck('status', 'exam_part_id')
            ->toArray();

        return Inertia::render('Exams/Show', [
            'exam' => $exam,
            'submissions' => $submissions,
        ]);
    }

    public function submitPart(Request $request, Exam $exam, ExamPart $examPart)
    {
        // Validate the request
        $validated = $request->validate([
            'answers' => 'required|array',
        ]);

        // Create or update submission
        $submission = ExamSubmission::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'exam_id' => $exam->id,
                'exam_part_id' => $examPart->id,
            ],
            [
                'answers' => json_encode($validated['answers']),
                'status' => 'submitted',
            ]
        );

        return redirect()->back();
    }
}
