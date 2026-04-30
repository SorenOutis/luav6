<?php

namespace App\Filament\Widgets;

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\ExamSubmission;
use App\Models\User;
use Filament\Widgets\Widget;

class QuickActionsWidget extends Widget
{
    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.quick-actions';

    protected function getViewData(): array
    {
        $totalStudents = User::query()->where('is_admin', false)->count();
        $totalExams = Exam::query()->where('status', '!=', 'draft')->count();
        $totalAssignments = Assignment::query()->count();
        $pendingReview = Exam::query()->where('status', 'published')->sum(
            \DB::raw('(SELECT COUNT(*) FROM exam_submissions WHERE exam_submissions.exam_id = exams.id)')
        );

        $recentlyBanned = User::query()
            ->where('is_admin', false)
            ->where('is_banned', true)
            ->where('banned_at', '>=', now()->subDays(7))
            ->count();

        // Compute pending exam submissions (no score yet)
        $pendingSubmissions = ExamSubmission::query()
            ->whereNull('score')
            ->count();

        return [
            'totalStudents' => $totalStudents,
            'totalExams' => $totalExams,
            'totalAssignments' => $totalAssignments,
            'pendingReview' => $pendingReview,
            'pendingSubmissions' => $pendingSubmissions,
            'recentlyBanned' => $recentlyBanned,
        ];
    }
}
