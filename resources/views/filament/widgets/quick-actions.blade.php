<x-filament-widgets::widget class="quick-actions-widget">
    <div class="quick-actions">
        <div class="quick-actions__header">
            <h3 class="quick-actions__title">Quick Actions</h3>
        </div>

        <div class="quick-actions__grid">
            <a href="{{ url('/admin/exams/create') }}" class="quick-actions__action">
                <x-filament::icon icon="heroicon-o-plus-circle" class="quick-actions__action-icon" />
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Create Exam</span>
                    <span class="quick-actions__action-sub">New exam for students</span>
                </div>
            </a>

            <a href="{{ url('/admin/assignments/create') }}" class="quick-actions__action">
                <x-filament::icon icon="heroicon-o-document-plus" class="quick-actions__action-icon" />
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Create Assignment</span>
                    <span class="quick-actions__action-sub">Add new assignment</span>
                </div>
            </a>

            <a href="{{ url('/admin/exam-submissions') }}" class="quick-actions__action">
                <x-filament::icon icon="heroicon-o-clipboard-document-check" class="quick-actions__action-icon" />
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Review Submissions</span>
                    <span class="quick-actions__action-sub">{{ number_format($pendingSubmissions) }} pending</span>
                </div>
            </a>

            <a href="{{ url('/admin/users') }}" class="quick-actions__action">
                <x-filament::icon icon="heroicon-o-users" class="quick-actions__action-icon" />
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Manage Students</span>
                    <span class="quick-actions__action-sub">{{ number_format($totalStudents) }} students</span>
                </div>
            </a>

            <a href="{{ url('/admin/announcements/create') }}" class="quick-actions__action">
                <x-filament::icon icon="heroicon-o-megaphone" class="quick-actions__action-icon" />
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Post Announcement</span>
                    <span class="quick-actions__action-sub">Notify all students</span>
                </div>
            </a>

            <a href="{{ url('/admin/users') }}?tableSearchType=table&tableFilters%5Bbanned%5D=is_banned%3Atrue" class="quick-actions__action">
                <x-filament::icon icon="heroicon-o-shield-exclamation" class="quick-actions__action-icon" />
                <div class="quick-actions__action-content">
                    <span class="quick-actions__action-label">Review Banned</span>
                    <span class="quick-actions__action-sub">{{ number_format($recentlyBanned) }} this week</span>
                </div>
            </a>
        </div>
    </div>
</x-filament-widgets::widget>
