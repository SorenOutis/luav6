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

            @if ($streakLeaders->isNotEmpty())
                <div class="admin-command-center__streaks">
                    <span class="admin-command-center__streak-label">Streak leaders</span>
                    <div class="admin-command-center__streak-leaders">
                        @foreach ($streakLeaders as $leader)
                            <span class="admin-command-center__streak-badge">
                                {{ $leader['name'] }} — {{ $leader['streak'] }}d
                            </span>
                        @endforeach
                    </div>
                </div>
            @endif

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
                <span class="admin-command-center__metric-label">New this week</span>
                <strong>+{{ number_format($newStudentsThisWeek) }}</strong>
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
                <span class="admin-command-center__metric-label">Avg score (7d)</span>
                <strong>{{ $avgScore }}%</strong>
            </div>

            <div class="admin-command-center__metric">
                <span class="admin-command-center__metric-label">Total XP earned</span>
                <strong>{{ number_format($totalXpEarned) }}</strong>
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
                    $isUrgent = $dueDate && $dueDate->diffInHours(now()) <= 24;
                @endphp

                <div class="admin-command-center__deadline @if ($isUrgent) admin-command-center__deadline--urgent @endif">
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
