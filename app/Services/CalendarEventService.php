<?php

namespace App\Services;

use App\Enums\ExamStatus;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Season;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the event payload for the student calendar page.
 *
 * Exams, assignments, and season ranges are normalized into one shape so
 * Calendar.vue can lay out a month grid without caring where each item came
 * from. Visibility mirrors the pages the events link back to: exams follow the
 * exam-list rule (visible statuses, own sections or global) with one
 * exception — closed exams only appear when the viewer answered them, since a
 * missed closed exam is no longer an actionable deadline; assignments use
 * section targeting (visibleToSections), seasons come from the student's own
 * enrollments — the same source the dashboard season picker uses.
 *
 * Dates are shipped as `dateKey` (Y-m-d, app timezone) rather than relying on
 * client-side ISO parsing so bucketing matches the dates shown on the
 * dashboard, which formats server-side.
 */
class CalendarEventService
{
    /**
     * @return array<string, mixed>
     */
    public function forUser(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        // All of the student's sections, across seasons — the calendar shows
        // everything they are enrolled in, not just the selected season.
        $sectionIds = $user->sections()->pluck('sections.id');

        return [
            // toBase(): examEvents()/assignmentEvents() return collections of
            // arrays, not models. Eloquent\Collection::merge() chokes merging
            // a plain collection of arrays (TypeError), so normalize both
            // sides to base collections before combining.
            'events' => $this->examEvents($user, $sectionIds, $from, $to)
                ->merge($this->assignmentEvents($user, $sectionIds, $from, $to))
                ->sortBy('dateKey')
                ->values()
                ->all(),
            'seasons' => $this->seasonRanges($user, $from, $to),
        ];
    }

    /**
     * Exams the user may see, in the given window.
     *
     * Drafts never leak. Published exams follow the exam-list rule (own
     * sections or global). Closed exams only stay on the calendar when the
     * viewer submitted answers to them — a closed exam the student never took
     * is no longer an actionable deadline, so it would only clutter the grid.
     * Admins keep the full historical view.
     *
     * @param  Collection<int, int>  $sectionIds
     * @return Collection<int, array<string, mixed>>
     */
    private function examEvents(User $user, Collection $sectionIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        $exams = Exam::query()
            ->with(['section:id,name'])
            ->withCount(['parts'])
            ->withCount(['submissions as submitted_parts' => fn ($q) => $q->where('user_id', $user->id)])
            ->where(function ($query) use ($user) {
                $query->where('status', ExamStatus::Published);

                if ($user->is_admin) {
                    $query->orWhere('status', ExamStatus::Closed);

                    return;
                }

                $query->orWhere(function ($query) use ($user) {
                    $query->where('status', ExamStatus::Closed)
                        ->whereHas('submissions', fn ($q) => $q->where('user_id', $user->id));
                });
            })
            ->whereBetween('exam_date', [$from, $to])
            ->when(! $user->is_admin, function ($query) use ($sectionIds) {
                $query->where(function ($q) use ($sectionIds) {
                    $q->whereNull('section_id')
                        ->orWhereIn('section_id', $sectionIds);
                });
            })
            ->orderBy('exam_date')
            ->get();

        // toBase(): the mapped items are arrays; keep this a base collection
        // so it can merge with the assignment events.
        return $exams->toBase()->map(function (Exam $exam) {
            return [
                'type' => 'exam',
                'id' => $exam->id,
                'title' => $exam->title,
                'dateKey' => $exam->exam_date->format('Y-m-d'),
                'sectionName' => $exam->section?->name,
                'durationMinutes' => (int) $exam->duration_minutes,
                'status' => $exam->status,
                'startsAtISO' => $exam->starts_at?->toIso8601String(),
                'endsAtISO' => $exam->ends_at?->toIso8601String(),
                'isOpenNow' => $exam->acceptsSubmissions(),
                'isUpcoming' => $exam->scheduleState() === 'upcoming',
                'hasEnded' => $exam->scheduleState() === 'ended',
                'isCompleted' => (int) $exam->parts_count > 0
                    && (int) $exam->submitted_parts === (int) $exam->parts_count,
                'href' => "/exams/{$exam->id}",
            ];
        });
    }

    /**
     * Section-targeted assignments with a due date in the window.
     *
     * @param  Collection<int, int>  $sectionIds
     * @return Collection<int, array<string, mixed>>
     */
    private function assignmentEvents(User $user, Collection $sectionIds, CarbonInterface $from, CarbonInterface $to): Collection
    {
        if ($sectionIds->isEmpty()) {
            return collect();
        }

        $assignments = Assignment::query()
            ->visibleToSections($sectionIds)
            ->with(['course:id,name'])
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [$from, $to])
            ->orderBy('due_date')
            ->get();

        // Same pattern as DashboardController: the roster per student is
        // small, so fetch the pivots once and key them by assignment id
        // instead of constraining the belongsToMany join.
        $pivots = $user->assignments()->get()->keyBy('id');

        return $assignments->toBase()->map(function (Assignment $assignment) use ($pivots) {
            $pivot = $pivots->get($assignment->id)?->pivot;
            $submitted = (bool) ($pivot?->submitted ?? false);
            $due = $assignment->due_date;

            return [
                'type' => 'assignment',
                'id' => $assignment->id,
                'title' => $assignment->title,
                'dateKey' => $due->format('Y-m-d'),
                'courseName' => $assignment->course?->name,
                'submitted' => $submitted,
                'isOverdue' => $due->isPast() && ! $submitted,
                'href' => '/assignments',
            ];
        });
    }

    /**
     * Season ranges overlapping the window, for the calendar header chips.
     *
     * Same source as the dashboard season picker: the seasons the student's
     * own enrollments point to. Super admins see every season with
     * enrollments.
     *
     * @return array<int, array<string, mixed>>
     */
    private function seasonRanges(User $user, CarbonInterface $from, CarbonInterface $to): array
    {
        $seasonIds = $user->isSuperAdmin()
            ? DB::table('section_user')
                ->whereNotNull('season_id')
                ->distinct()
                ->pluck('season_id')
            : DB::table('section_user')
                ->where('user_id', $user->id)
                ->whereNotNull('season_id')
                ->distinct()
                ->pluck('season_id');

        if ($seasonIds->isEmpty()) {
            return [];
        }

        return Season::query()
            ->whereIn('id', $seasonIds)
            ->where('start_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('end_date')->orWhere('end_date', '>=', $from))
            ->orderBy('start_date')
            ->get()
            ->map(fn (Season $season) => [
                'id' => $season->id,
                'name' => $season->name,
                'startDateKey' => $season->start_date->format('Y-m-d'),
                'endDateKey' => $season->end_date?->format('Y-m-d'),
                'isActive' => (bool) $season->is_active,
            ])
            ->all();
    }
}
