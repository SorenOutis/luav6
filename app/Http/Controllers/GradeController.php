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

            $periodGrades = collect($periods)
                ->map(fn (string $label, string $key) => [
                    'key' => $key,
                    'label' => $label,
                    'grade' => $gradesBySection[$sectionId][$key] ?? null,
                ])
                ->values()
                ->all();

            $availableScores = collect($periodGrades)
                ->pluck('grade')
                ->filter()
                ->pluck('percentage')
                ->all();
            $gradedPeriods = count($availableScores);
            $totalPeriods = count($periodGrades);
            $isComplete = $totalPeriods > 0 && $gradedPeriods === $totalPeriods;
            $currentAverage = $gradedPeriods > 0
                ? round(array_sum($availableScores) / $gradedPeriods, 2)
                : null;

            // Keep every enrolled section as its own row. Indexing this array by
            // section name used to silently overwrite one course when a student
            // was enrolled in two sections with the same display name.
            $subjectGrades[] = [
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
                'periodGrades' => $periodGrades,
                'semesterGrades' => $semesterGrades,
                'gradedPeriods' => $gradedPeriods,
                'totalPeriods' => $totalPeriods,
                'isComplete' => $isComplete,
                // A current average is explicitly provisional and uses only the
                // periods already entered. An official final grade is exposed
                // only after every required period has been graded.
                'currentAverage' => $currentAverage,
                'semesterGrade' => $isComplete ? $currentAverage : null,
            ];
        }

        return $subjectGrades;
    }
}
