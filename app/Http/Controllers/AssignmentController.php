<?php

namespace App\Http\Controllers;

use App\Models\Assignment;
use App\Support\PublicFileUrl;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AssignmentController extends Controller
{
    public const ALLOWED_MIMES = 'pdf,doc,docx,ppt,pptx,xls,xlsx,jpg,jpeg,png';

    public function index()
    {
        $user = auth()->user();
        $sectionIds = $user->sections()->pluck('sections.id');

        // Assignments are targeted at sections. A student only sees the ones
        // given to a section they belong to; an assignment with no sections
        // is unassigned and reaches nobody.
        $assignments = Assignment::query()
            ->visibleToSections($sectionIds)
            ->with(['course:id,name', 'sections:id,name'])
            ->orderByRaw('due_date IS NULL')
            ->orderBy('due_date')
            ->get()
            ->map(function ($assignment) use ($user) {
                $submission = $user->assignments()->where('assignment_id', $assignment->id)->first();
                $pivot = $submission?->pivot;
                $filePath = $pivot?->file_path;

                return [
                    'id' => $assignment->id,
                    'title' => $assignment->title,
                    'description' => $assignment->description,
                    'due_date' => $assignment->due_date?->toIso8601String(),
                    'course' => $assignment->course,
                    'sections' => $assignment->sections->map(fn ($section) => [
                        'id' => $section->id,
                        'name' => $section->name,
                    ])->values(),
                    'submission' => $submission ? [
                        'submitted' => $pivot->submitted,
                        'status' => $pivot->status,
                        'grade' => $pivot->grade,
                        'file_path' => $filePath,
                        'file_url' => PublicFileUrl::resolve($filePath),
                        'submitted_at' => $pivot->submitted_at,
                        'points' => $pivot->points ?? 0,
                        'xp_earned' => $pivot->xp_earned ?? 0,
                        'feedback' => $pivot->feedback,
                        'graded_at' => $pivot->graded_at,
                        'graded_by' => $pivot->graded_by,
                        'file_extension' => $filePath ? strtolower(pathinfo($filePath, PATHINFO_EXTENSION)) : null,
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
            'file' => 'required|file|mimes:'.self::ALLOWED_MIMES.'|max:10240',
        ]);

        $user = auth()->user();

        // Never accept work for an assignment the student was not given.
        if (! $assignment->isVisibleTo($user)) {
            abort(403, 'This assignment was not assigned to your section.');
        }

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
