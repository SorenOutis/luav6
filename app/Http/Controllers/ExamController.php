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

        return Inertia::render('Exam', [
            'exams' => $exams,
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

        return Inertia::render('Exams/Show', [
            'exam' => $exam,
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
