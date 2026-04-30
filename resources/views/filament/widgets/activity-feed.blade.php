<x-filament-widgets::widget class="activity-feed-widget">
    <div class="activity-feed">
        <div class="activity-feed__header">
            <h3 class="activity-feed__title">Recent Activity</h3>
            <span class="activity-feed__subtitle">Last 24 hours</span>
        </div>

        <div class="activity-feed__timeline">
            @forelse ($activities as $activity)
                <div class="activity-feed__event activity-feed__event--{{ $activity['type'] }}">
                    <div class="activity-feed__event-dot"></div>
                    <div class="activity-feed__event-body">
                        <div class="activity-feed__event-content">
                            <span class="activity-feed__event-user">{{ $activity['user_name'] }}</span>
                            <span class="activity-feed__event-description">{{ $activity['description'] }}</span>
                        </div>
                        <time class="activity-feed__event-time">{{ $activity['timestamp'] }}</time>
                    </div>
                </div>
            @empty
                <div class="activity-feed__empty">
                    <x-filament::icon icon="heroicon-o-clock" class="activity-feed__empty-icon" />
                    <p>No activity in the last 24 hours.</p>
                </div>
            @endforelse
        </div>
    </div>
</x-filament-widgets::widget>
