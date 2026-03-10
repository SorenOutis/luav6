<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\Exam;
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
}
