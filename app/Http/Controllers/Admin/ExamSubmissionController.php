<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSubmission;
use Inertia\Inertia;

class ExamSubmissionController extends Controller
{
    public function index()
    {
        // Ensure user is admin
        if (! auth()->user()->is_admin) {
            abort(403, 'Unauthorized');
        }

        // Get all exams with submission counts
        $exams = Exam::withCount('submissions')
            ->where('status', '!=', 'draft')
            ->get();

        return Inertia::render('Admin/ExamSubmissions', [
            'exams' => $exams,
        ]);
    }

    public function examSubmissions(Exam $exam)
    {
        // Ensure user is admin
        if (! auth()->user()->is_admin) {
            abort(403, 'Unauthorized');
        }

        // Get all submissions for this exam
        $submissions = ExamSubmission::where('exam_id', $exam->id)
            ->with(['user', 'examPart'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function ($submission) {
                // Ensure answers is always an array
                $answers = is_array($submission->answers) ? $submission->answers : json_decode($submission->answers, true) ?? [];

                return [
                    'id' => $submission->id,
                    'user_name' => $submission->user->name,
                    'user_id' => $submission->user->id,
                    'part_title' => $submission->examPart->title,
                    'part_id' => $submission->examPart->id,
                    'answers' => $answers,
                    'status' => $submission->status,
                    'submitted_at' => $submission->created_at->format('M d, Y H:i'),
                    'created_at' => $submission->created_at,
                ];
            });

        return Inertia::render('Admin/ExamSubmissionsDetail', [
            'exam' => [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
            ],
            'submissions' => $submissions,
        ]);
    }
}
