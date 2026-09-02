<?php

namespace App\Services;

use App\Enums\ExamStatus;
use App\Models\Exam;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Phase 3.4 — Extract the duplicated exam-mapping block from the dashboard
 * closure and api/dashboard-exams endpoint.
 *
 * Both places ran the same query + map, and both had an N+1: a per-exam
 * submission count inside the map. This service fixes the N+1 with
 * withCount() and provides one method that both call sites use.
 */
class UpcomingExamsService
{
    public function __construct(protected ExamSetAssignmentService $examSets) {}

    /**
     * Get open exams for the user, scoped to the given section IDs.
     *
     * Only published exams are returned. Closed exams no longer accept
     * submissions, so they must not appear as Today / Overdue / Next 24h /
     * Next exam work. They would also occupy the result limit and hide
     * exams the student can still take.
     *
     * @param  Collection<int>  $sectionIds
     * @return Collection<int, array<string, mixed>>
     */
    public function forUser(User $user, Collection $sectionIds, int $limit = 3): Collection
    {
        $exams = Exam::withCount(['parts'])
            ->withCount(['submissions as submitted_parts' => fn ($q) => $q->where('user_id', $user->id)])
            ->where('status', ExamStatus::Published)
            // Scheduled exams stop appearing as an actionable deadline once the
            // window ends; legacy exams without ends_at stay visible.
            ->where(fn ($query) => $query
                ->whereNull('ends_at')
                ->orWhere('ends_at', '>', now()))
            ->when(! $user->is_admin, function ($query) use ($sectionIds) {
                $query->where(function ($q) use ($sectionIds) {
                    $q->whereNull('section_id')
                        ->orWhereIn('section_id', $sectionIds);
                });
            })
            ->orderBy('exam_date', 'asc')
            ->limit($limit)
            ->get();

        // An exam can ship as several interchangeable sets: the student only
        // ever answers one of them, so "3 parts" must mean 3 parts of THEIR
        // set, not of every set the teacher wrote.
        $summaries = $this->examSets->summariesFor($user, $exams->pluck('id')->all());

        return $exams->map(function ($exam) use ($summaries) {
            $partsCount = (int) ($summaries[$exam->id]['total_parts'] ?? $exam->parts_count);

            return [
                'id' => $exam->id,
                'title' => $exam->title,
                'description' => $exam->description,
                'exam_date' => $exam->exam_date->format('M d, Y'),
                'exam_date_iso' => $exam->exam_date->toIso8601String(),
                'starts_at_iso' => $exam->starts_at?->toIso8601String(),
                'ends_at_iso' => $exam->ends_at?->toIso8601String(),
                'duration_minutes' => $exam->duration_minutes,
                'status' => $exam->status,
                'parts_count' => $partsCount,
                'submitted_parts' => (int) $exam->submitted_parts,
                'is_completed' => (int) $exam->submitted_parts === $partsCount && $partsCount > 0,
                'is_open_now' => $exam->acceptsSubmissions(),
                'is_upcoming' => $exam->scheduleState() === 'upcoming',
                'has_ended' => $exam->scheduleState() === 'ended',
                'set' => $summaries[$exam->id]['set']?->title ?? null,
            ];
        });
    }
}
