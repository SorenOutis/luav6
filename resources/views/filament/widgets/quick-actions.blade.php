<x-filament-widgets::widget class="quick-actions-widget">
    <x-filament::section>
        <x-slot name="heading">
            <div class="flex items-center gap-2">
                <x-filament::icon icon="heroicon-o-bolt" class="h-5 w-5 text-amber-500" />
                Quick Actions
            </div>
        </x-slot>
        <x-slot name="description">
            Jump to the most common admin tasks. Counts are cached for 60s.
        </x-slot>

        <div class="quick-actions__grid">
            <a href="/admin/exams/create" class="quick-actions__action quick-actions__action--primary">
                <div class="quick-actions__action-icon-wrap bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400">
                    <x-filament::icon icon="heroicon-o-plus-circle" class="quick-actions__action-icon" />
                </div>
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Create Exam</span>
                    <span class="quick-actions__action-sub">New exam for students</span>
                </div>
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400 ml-auto" />
            </a>

            <a href="/admin/assignments/create" class="quick-actions__action">
                <div class="quick-actions__action-icon-wrap bg-sky-100 dark:bg-sky-500/20 text-sky-600 dark:text-sky-400">
                    <x-filament::icon icon="heroicon-o-document-plus" class="quick-actions__action-icon" />
                </div>
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Create Assignment</span>
                    <span class="quick-actions__action-sub">{{ number_format($totalAssignments) }} total</span>
                </div>
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400 ml-auto" />
            </a>

            <a href="/admin/exam-submissions" class="quick-actions__action @if($pendingSubmissions > 0) quick-actions__action--urgent @endif">
                <div class="quick-actions__action-icon-wrap bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400">
                    <x-filament::icon icon="heroicon-o-clipboard-document-check" class="quick-actions__action-icon" />
                </div>
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Review Submissions</span>
                    <span class="quick-actions__action-sub">
                        @if($pendingSubmissions > 0)
                            <span class="font-semibold text-amber-600 dark:text-amber-400">{{ number_format($pendingSubmissions) }} pending review</span>
                        @else
                            All caught up ✓
                        @endif
                    </span>
                </div>
                @if($pendingSubmissions > 0)
                    <span class="quick-actions__badge">{{ $pendingSubmissions }}</span>
                @endif
            </a>

            <a href="/admin/users" class="quick-actions__action">
                <div class="quick-actions__action-icon-wrap bg-violet-100 dark:bg-violet-500/20 text-violet-600 dark:text-violet-400">
                    <x-filament::icon icon="heroicon-o-users" class="quick-actions__action-icon" />
                </div>
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Manage Students</span>
                    <span class="quick-actions__action-sub">{{ number_format($totalStudents) }} students</span>
                </div>
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400 ml-auto" />
            </a>

            <a href="/admin/announcements/create" class="quick-actions__action">
                <div class="quick-actions__action-icon-wrap bg-pink-100 dark:bg-pink-500/20 text-pink-600 dark:text-pink-400">
                    <x-filament::icon icon="heroicon-o-megaphone" class="quick-actions__action-icon" />
                </div>
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Post Announcement</span>
                    <span class="quick-actions__action-sub">Notify all students</span>
                </div>
                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400 ml-auto" />
            </a>

            <a href="/admin/support-tickets" class="quick-actions__action @if($pendingTickets > 0) quick-actions__action--urgent @endif">
                <div class="quick-actions__action-icon-wrap bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400">
                    <x-filament::icon icon="heroicon-o-chat-bubble-left-right" class="quick-actions__action-icon" />
                </div>
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Support Tickets</span>
                    <span class="quick-actions__action-sub">
                        @if($pendingTickets > 0)
                            {{ number_format($pendingTickets) }} open tickets
                        @else
                            No open tickets
                        @endif
                    </span>
                </div>
                @if($pendingTickets > 0)
                    <span class="quick-actions__badge quick-actions__badge--blue">{{ $pendingTickets }}</span>
                @endif
            </a>

            <a href="/admin/ai-question-drafts" class="quick-actions__action @if($pendingAiDrafts > 0) quick-actions__action--urgent @endif">
                <div class="quick-actions__action-icon-wrap bg-indigo-100 dark:bg-indigo-500/20 text-indigo-600 dark:text-indigo-400">
                    <x-filament::icon icon="heroicon-o-sparkles" class="quick-actions__action-icon" />
                </div>
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">AI Drafts</span>
                    <span class="quick-actions__action-sub">
                        @if($pendingAiDrafts > 0)
                            {{ number_format($pendingAiDrafts) }} pending review
                        @else
                            All reviewed
                        @endif
                    </span>
                </div>
                @if($pendingAiDrafts > 0)
                    <span class="quick-actions__badge quick-actions__badge--indigo">{{ $pendingAiDrafts }}</span>
                @endif
            </a>

            <a href="/admin/users?tableFilters[banned][value]=1" class="quick-actions__action @if($recentlyBanned > 0) quick-actions__action--danger @endif">
                <div class="quick-actions__action-icon-wrap bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400">
                    <x-filament::icon icon="heroicon-o-shield-exclamation" class="quick-actions__action-icon" />
                </div>
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Review Banned</span>
                    <span class="quick-actions__action-sub">{{ number_format($recentlyBanned) }} this week</span>
                </div>
                @if($recentlyBanned > 0)
                    <span class="quick-actions__badge quick-actions__badge--rose">{{ $recentlyBanned }}</span>
                @endif
            </a>

            @if($failedJobs > 0)
                <a href="/admin/jobs-monitor" class="quick-actions__action quick-actions__action--danger">
                    <div class="quick-actions__action-icon-wrap bg-red-100 dark:bg-red-500/20 text-red-600 dark:text-red-400">
                        <x-filament::icon icon="heroicon-o-exclamation-triangle" class="quick-actions__action-icon" />
                    </div>
                    <div class="quick-actions__action-content">
                        <span class="quick-actions__action-label">Failed Jobs</span>
                        <span class="quick-actions__action-sub">{{ number_format($failedJobs) }} need attention</span>
                    </div>
                    <span class="quick-actions__badge quick-actions__badge--rose">{{ $failedJobs }}</span>
                </a>
            @endif
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
