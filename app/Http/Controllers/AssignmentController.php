<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    public function index()
    {
        $user = auth()->user();
        $assignments = Assignment::with(['course'])->get()->map(function ($assignment) use ($user) {
            $submission = $user->assignments()->where('assignment_id', $assignment->id)->first();

            return [
                'id' => $assignment->id,
                'title' => $assignment->title,
                'description' => $assignment->description,
                'due_date' => $assignment->due_date,
                'course' => $assignment->course,
                'submission' => $submission ? [
                    'submitted' => $submission->pivot->submitted,
                    'status' => $submission->pivot->status,
                    'grade' => $submission->pivot->grade,
                    'file_path' => $submission->pivot->file_path,
                    'submitted_at' => $submission->pivot->submitted_at,
                ] : null,
            ];
        });

        return Inertia::render('Assignments', [
            'assignments' => $assignments,
        ]);
    }

    public function store(Request $request, Assignment $assignment)
    {
        $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,txt,png,jpg,jpeg|max:10240', // 10MB limit, restricted types
        ]);

        $user = auth()->user();
        $path = $request->file('file')->store('assignments/'.$user->id, 'public');

        $user->assignments()->syncWithoutDetaching([
            $assignment->id => [
                'submitted' => true,
                'status' => 'Submitted',
                'file_path' => $path,
                'submitted_at' => now(),
            ],
        ]);

        return back()->with('success', 'Assignment submitted successfully!');
    }
}
