<x-filament-widgets::widget class="admin-command-center-widget">
    <x-filament::section>
        <div class="admin-command-center">
            {{-- Left: Welcome + actions --}}
            <div class="admin-command-center__content">
                <div class="admin-command-center__eyebrow">
                    <span class="admin-command-center__status-dot"></span>
                    Admin control center
                    @if($activeSeason)
                        <span class="admin-command-center__season-badge">{{ $activeSeason->name }}</span>
                    @endif
                </div>

                <h2 class="admin-command-center__title">
                    Welcome back, {{ $adminName }}
                </h2>

                <p class="admin-command-center__description">
                    @if($activeSeason)
                        <strong>{{ $activeSeason->name }}</strong> is active with
                    @else
                        No active season — 
                    @endif
                    {{ number_format($totalStudents) }} students across {{ number_format($sectionsCount) }} sections.
                    @if($pendingSubmissions > 0)
                        <span class="text-amber-600 dark:text-amber-400 font-semibold">{{ number_format($pendingSubmissions) }} submissions need review.</span>
                    @endif
                </p>

                @if ($streakLeaders->isNotEmpty())
                    <div class="admin-command-center__streaks">
                        <span class="admin-command-center__streak-label">🔥 Streak leaders</span>
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
                        <x-filament::icon icon="heroicon-o-document-chart-bar" class="h-4 w-4" />
                        Review submissions
                        @if($pendingSubmissions > 0)
                            <span class="admin-command-center__action-count">{{ $pendingSubmissions }}</span>
                        @endif
                    </a>

                    <a class="admin-command-center__secondary-action" href="/admin/exams">
                        <x-filament::icon icon="heroicon-o-academic-cap" class="h-4 w-4" />
                        Manage exams
                    </a>

                    <a class="admin-command-center__secondary-action" href="/admin/assignments">
                        <x-filament::icon icon="heroicon-o-clipboard-document-list" class="h-4 w-4" />
                        Assignments
                    </a>

                    @if($pendingTickets > 0)
                        <a class="admin-command-center__secondary-action admin-command-center__secondary-action--warning" href="/admin/support-tickets">
                            <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="h-4 w-4" />
                            {{ $pendingTickets }} tickets
                        </a>
                    @endif
                </div>

                @if($failedJobs > 0 || $pendingAiDrafts > 0)
                    <div class="admin-command-center__alerts">
                        @if($failedJobs > 0)
                            <a href="/admin/jobs-monitor" class="admin-command-center__alert admin-command-center__alert--danger">
                                <x-filament::icon icon="heroicon-o-exclamation-triangle" class="h-4 w-4" />
                                {{ $failedJobs }} failed jobs
                            </a>
                        @endif
                        @if($pendingAiDrafts > 0)
                            <a href="/admin/ai-question-drafts" class="admin-command-center__alert admin-command-center__alert--info">
                                <x-filament::icon icon="heroicon-o-sparkles" class="h-4 w-4" />
                                {{ $pendingAiDrafts }} AI drafts pending
                            </a>
                        @endif
                    </div>
                @endif
            </div>

            {{-- Middle: Metrics --}}
            <div class="admin-command-center__metrics" aria-label="Admin dashboard summary">
                <a href="/admin/users" class="admin-command-center__metric admin-command-center__metric--link">
                    <span class="admin-command-center__metric-label">Active today</span>
                    <strong>{{ number_format($activeToday) }}</strong>
                    <span class="admin-command-center__metric-trend">{{ $totalStudents > 0 ? round(($activeToday / max($totalStudents,1))*100,1).'% of total' : '—' }}</span>
                </a>

                <div class="admin-command-center__metric">
                    <span class="admin-command-center__metric-label">New this week</span>
                    <strong class="text-emerald-600 dark:text-emerald-400">+{{ number_format($newStudentsThisWeek) }}</strong>
                    <span class="admin-command-center__metric-trend">registrations</span>
                </div>

                <a href="/admin/exams" class="admin-command-center__metric admin-command-center__metric--link">
                    <span class="admin-command-center__metric-label">Live exams</span>
                    <strong>{{ number_format($liveExamsCount) }}</strong>
                    <span class="admin-command-center__metric-trend">published</span>
                </a>

                <a href="/admin/exam-submissions" class="admin-command-center__metric admin-command-center__metric--link">
                    <span class="admin-command-center__metric-label">Today submissions</span>
                    <strong>{{ number_format($submissionsToday) }}</strong>
                    <span class="admin-command-center__metric-trend">today</span>
                </a>

                <div class="admin-command-center__metric">
                    <span class="admin-command-center__metric-label">Avg score (7d)</span>
                    <strong class="{{ $avgScore >= 70 ? 'text-emerald-600' : ($avgScore >= 50 ? 'text-amber-600' : 'text-rose-600') }}">{{ $avgScore }}%</strong>
                    <span class="admin-command-center__metric-trend">average</span>
                </div>

                <div class="admin-command-center__metric">
                    <span class="admin-command-center__metric-label">Total XP earned</span>
                    <strong>{{ number_format($totalXpEarned) }}</strong>
                    <span class="admin-command-center__metric-trend">{{ number_format($gamesPlayedWeek) }} plays</span>
                </div>
            </div>

            {{-- Right: Queue --}}
            <div class="admin-command-center__queue">
                <div class="admin-command-center__queue-header">
                    <span class="flex items-center gap-2">
                        <x-filament::icon icon="heroicon-o-clock" class="h-4 w-4" />
                        Upcoming deadlines
                    </span>
                    <a href="/admin/assignments" class="text-xs font-semibold hover:underline">View all →</a>
                </div>

                @forelse ($upcomingAssignments as $assignment)
                    @php
                        $dueDate = $assignment->due_date ? \Illuminate\Support\Carbon::parse($assignment->due_date) : null;
                        $isUrgent = $dueDate && $dueDate->diffInHours(now()) <= 24;
                        $isOverdue = $dueDate && $dueDate->isPast();
                    @endphp

                    <a href="/admin/assignments/{{ $assignment->id }}/edit" class="admin-command-center__deadline @if ($isUrgent) admin-command-center__deadline--urgent @endif @if($isOverdue) admin-command-center__deadline--overdue @endif">
                        <span class="admin-command-center__deadline-title">{{ $assignment->title }}</span>
                        <time>
                            @if($dueDate)
                                @if($isOverdue)
                                    Overdue • {{ $dueDate->diffForHumans() }}
                                @elseif($isUrgent)
                                    Due {{ $dueDate->diffForHumans() }} • {{ $dueDate->format('M d, g:i A') }}
                                @else
                                    {{ $dueDate->format('M d, g:i A') }} • {{ $dueDate->diffForHumans() }}
                                @endif
                            @else
                                No deadline
                            @endif
                        </time>
                    </a>
                @empty
                    <div class="admin-command-center__empty">
                        <x-filament::icon icon="heroicon-o-check-circle" class="h-8 w-8 mx-auto mb-2 opacity-50" />
                        <p>No upcoming deadlines.</p>
                        <a href="/admin/assignments/create" class="text-xs text-primary-600 hover:underline mt-1 inline-block">Create assignment</a>
                    </div>
                @endforelse

                <div class="admin-command-center__queue-footer">
                    <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400">
                        <span>{{ number_format($pendingAssignments) }} pending assignments</span>
                        <a href="/admin/assignments" class="hover:text-primary-600">Manage →</a>
                    </div>
                    <div class="mt-2 h-1.5 w-full bg-gray-100 dark:bg-white/10 rounded-full overflow-hidden">
                        @php
                            $total = max($totalStudents * 1, 1); // rough, but we have real pending
                            $progress = $pendingAssignments > 0 ? max(5, 100 - min(100, ($pendingAssignments / max($total,1))*100)) : 100;
                        @endphp
                        <div class="h-full bg-primary-500 rounded-full transition-all" style="width: {{ $progress }}%"></div>
                    </div>
                </div>
            </div>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
