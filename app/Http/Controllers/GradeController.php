<?php

namespace App\Http\Controllers;

use App\Models\Section;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GradeController extends Controller
{
    /**
     * Show the student's grades page (Inertia SSR).
     * Passes subjectGrades via SSR so the page renders immediately
     * without waiting for the client-side API fetch to complete.
     */
    public function index(Request $request)
    {
        return inertia('Grades', [
            'subjectGrades' => $this->buildGradesData($request->user()),
        ]);
    }

    /**
     * JSON API endpoint for grades — used by the Vue frontend.
     */
    public function apiIndex(Request $request): JsonResponse
    {
        return response()->json([
            'subjectGrades' => $this->buildGradesData($request->user()),
        ]);
    }

    /**
     * Debug endpoint to inspect raw grade data.
     */
    public function debug(Request $request): JsonResponse
    {
        $user = $request->user();

        $sections = $user->sections()->orderBy('name')->get()->map(fn ($s) => [
            'id' => $s->id,
            'name' => $s->name,
            'school_level' => $s->school_level,
        ]);

        $grades = $user->grades()->with('section')->get()->map(fn ($g) => [
            'id' => $g->id,
            'user_id' => $g->user_id,
            'section_id' => $g->section_id,
            'subject' => $g->subject,
            'period' => $g->period,
            'score' => $g->score,
            'max_score' => $g->max_score,
        ]);

        $built = $this->buildGradesData($user);

        return response()->json([
            'user_id' => $user->id,
            'user_name' => $user->name,
            'user_is_admin' => $user->is_admin,
            'sections_count' => $sections->count(),
            'sections' => $sections,
            'grades_count' => $grades->count(),
            'grades' => $grades,
            'built_data_ict12a' => collect($built)->firstWhere('subject', 'ICT 12-A (2026)'),
        ]);
    }

    /**
     * Build the subject grades data structure for a given user.
     */
    private function buildGradesData(User $user): array
    {
        // Get all sections the user is enrolled in
        $sections = $user->sections()->orderBy('name')->get();

        // Get all grades for the user
        $grades = $user->grades()->with('section')->get();

        // Group grades by section ID for easy lookup
        $gradesBySection = [];
        foreach ($grades as $grade) {
            $sectionId = $grade->section_id;
            $period = $grade->period;

            if (! isset($gradesBySection[$sectionId])) {
                $gradesBySection[$sectionId] = [];
            }

            $percentage = (float) $grade->max_score > 0
                ? round(((float) $grade->score / (float) $grade->max_score) * 100, 2)
                : 0;

            $gradesBySection[$sectionId][$period] = [
                'id' => $grade->id,
                'score' => number_format((float) $grade->score, 2),
                'maxScore' => number_format((float) $grade->max_score, 2),
                'percentage' => $percentage,
                'remarks' => $grade->remarks,
                'updatedAt' => $grade->updated_at->format('M d, Y'),
            ];
        }

        // Build subject grades array for all enrolled sections
        $subjectGrades = [];
        foreach ($sections as $section) {
            $sectionId = $section->id;
            $subjectName = $section->name;
            $periods = $section->gradePeriods();
            $isSeniorHigh = $section->school_level === Section::SCHOOL_LEVEL_SENIOR_HIGH;
            $semesterGrades = [];

            if ($isSeniorHigh) {
                $semesterGrades = collect(Section::seniorHighGradeSemesters())
                    ->map(function (array $semester) use ($gradesBySection, $sectionId) {
                        $quarters = collect($semester['quarters'])
                            ->map(fn (array $quarter) => [
                                'key' => $quarter['key'],
                                'label' => $quarter['label'],
                                'grade' => $gradesBySection[$sectionId][$quarter['key']] ?? null,
                            ])
                            ->values()
                            ->all();

                        $scores = collect($quarters)
                            ->pluck('grade')
                            ->filter()
                            ->pluck('percentage')
                            ->all();

                        return [
                            'key' => $semester['key'],
                            'label' => $semester['label'],
                            'quarters' => $quarters,
                            'finalGrade' => count($scores) === 2
                                ? round(array_sum($scores) / count($scores), 2)
                                : null,
                        ];
                    })
                    ->values()
                    ->all();
            }

            $subjectGrades[$subjectName] = [
                'subject' => $subjectName,
                'section' => [
                    'id' => $section->id,
                    'name' => $section->name,
                    'schoolLevel' => $section->school_level,
                    'schoolLevelLabel' => Section::schoolLevelOptions()[$section->school_level] ?? 'College',
                ],
                'periods' => collect($periods)
                    ->map(fn (string $label, string $key) => [
                        'key' => $key,
                        'label' => $label,
                    ])
                    ->values()
                    ->all(),
                'periodGrades' => collect($periods)
                    ->map(fn (string $label, string $key) => [
                        'key' => $key,
                        'label' => $label,
                        'grade' => $gradesBySection[$sectionId][$key] ?? null,
                    ])
                    ->values()
                    ->all(),
                'semesterGrades' => $semesterGrades,
            ];
        }

        // Calculate semester grades
        foreach ($subjectGrades as &$subjectData) {
            if (($subjectData['section']['schoolLevel'] ?? null) === Section::SCHOOL_LEVEL_SENIOR_HIGH) {
                $scores = collect($subjectData['semesterGrades'])
                    ->pluck('finalGrade')
                    ->filter(fn ($grade) => $grade !== null)
                    ->all();
            } else {
                $scores = collect($subjectData['periodGrades'])
                    ->pluck('grade')
                    ->filter()
                    ->pluck('percentage')
                    ->all();
            }

            if (count($scores) > 0) {
                $subjectData['semesterGrade'] = round(array_sum($scores) / count($scores), 2);
            } else {
                $subjectData['semesterGrade'] = null;
            }
        }

        return array_values($subjectGrades);
    }
}
