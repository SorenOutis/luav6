<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamSet;
use App\Services\ExamAnswerReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

/**
 * Renders the printable answer report for an exam ("View Answer" in the admin
 * panel). The page is a standalone, print-optimised HTML document: the browser
 * turns it into a PDF via the print dialog / "Save as PDF".
 */
class ExamAnswerReportController extends Controller
{
    public function show(Request $request, Exam $exam, ExamAnswerReportService $reports): View
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        $validated = $request->validate([
            'mode' => ['nullable', 'in:key,students'],
            'students' => ['nullable', 'array'],
            'students.*' => ['integer'],
            'include_key' => ['nullable', 'boolean'],
            'print' => ['nullable', 'boolean'],
            'set' => ['nullable', 'integer'],
        ]);

        $mode = $validated['mode'] ?? ExamAnswerReportService::MODE_STUDENTS;
        $studentIds = array_values(array_unique(array_map('intval', $validated['students'] ?? [])));
        $set = filled($validated['set'] ?? null)
            ? ExamSet::query()->where('exam_id', $exam->getKey())->find((int) $validated['set'])
            : null;

        $report = $reports->build(
            exam: $exam,
            mode: $mode,
            studentIds: $studentIds,
            includeKey: (bool) ($validated['include_key'] ?? true),
            set: $set,
        );

        return view('admin.exams.answer-report', [
            'report' => $report,
            'autoPrint' => (bool) ($validated['print'] ?? true),
            'backUrl' => Route::has('filament.admin.resources.exams.edit')
                ? route('filament.admin.resources.exams.edit', ['record' => $exam->id])
                : url('/admin/exams/'.$exam->id.'/edit'),
        ]);
    }
}
