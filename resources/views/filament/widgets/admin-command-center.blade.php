<x-filament-widgets::widget class="admin-command-center-widget">
    <section class="admin-command-center">
        <div class="admin-command-center__content">
            <div class="admin-command-center__eyebrow">
                <span class="admin-command-center__status-dot"></span>
                Admin control center
            </div>

            <h2 class="admin-command-center__title">
                Welcome back, {{ $adminName }}
            </h2>

            <p class="admin-command-center__description">
                {{ $activeSeason ? $activeSeason->name : 'No active season' }} is running with
                {{ number_format($totalStudents) }} students across {{ number_format($sectionsCount) }} sections.
            </p>

            <div class="admin-command-center__actions">
                <a class="admin-command-center__primary-action" href="/admin/exam-submissions">
                    <x-filament::icon icon="heroicon-o-document-chart-bar" />
                    Review submissions
                </a>

                <a class="admin-command-center__secondary-action" href="/admin/exams">
                    <x-filament::icon icon="heroicon-o-plus-circle" />
                    Manage exams
                </a>
            </div>
        </div>

        <div class="admin-command-center__metrics" aria-label="Admin dashboard summary">
            <div class="admin-command-center__metric">
                <span class="admin-command-center__metric-label">Active today</span>
                <strong>{{ number_format($activeToday) }}</strong>
            </div>

            <div class="admin-command-center__metric">
                <span class="admin-command-center__metric-label">Live exams</span>
                <strong>{{ number_format($liveExamsCount) }}</strong>
            </div>

            <div class="admin-command-center__metric">
                <span class="admin-command-center__metric-label">Today submissions</span>
                <strong>{{ number_format($submissionsToday) }}</strong>
            </div>

            <div class="admin-command-center__metric">
                <span class="admin-command-center__metric-label">Pending assignments</span>
                <strong>{{ number_format($pendingAssignments) }}</strong>
            </div>
        </div>

        <div class="admin-command-center__queue">
            <div class="admin-command-center__queue-header">
                <span>Upcoming deadlines</span>
                <a href="/admin/assignments">View all</a>
            </div>

            @forelse ($upcomingAssignments as $assignment)
                @php
                    $dueDate = $assignment->due_date ? \Illuminate\Support\Carbon::parse($assignment->due_date) : null;
                @endphp

                <div class="admin-command-center__deadline">
                    <span>{{ $assignment->title }}</span>
                    <time>{{ $dueDate?->format('M d, g:i A') ?? 'No deadline' }}</time>
                </div>
            @empty
                <div class="admin-command-center__empty">
                    No upcoming assignment deadlines.
                </div>
            @endforelse
        </div>
    </section>
</x-filament-widgets::widget>
