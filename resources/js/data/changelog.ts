export interface ChangelogFeature {
    title: string;
    description: string;
    tag: 'New' | 'Improved' | 'Fix';
    icon?: string;
}

export interface ChangelogRelease {
    version: string;
    title: string;
    date: string;
    isCurrent?: boolean;
    summary: string;
    features: ChangelogFeature[];
}

export const CHANGELOG_RELEASES: ChangelogRelease[] = [
    {
        version: 'v6.2.0',
        title: 'Voice Study Companion & Merch Bento',
        date: 'October 2026',
        isCurrent: true,
        summary:
            'Interactive speech recognition for Echo AI, official student merchandise bento showcase, and responsive mobile bottom sheets.',
        features: [
            {
                title: 'Hands-Free Voice Dictation',
                description:
                    'Talk directly to your AI study companion using fast browser speech recognition without typing.',
                tag: 'New',
                icon: 'Mic',
            },
            {
                title: 'Official Merchandise Showcase',
                description:
                    'Browse official apparel and campus gear with interactive colorway previews, edition switchers, and quick-view modals.',
                tag: 'New',
                icon: 'ShoppingBag',
            },
            {
                title: 'Fast Mobile Bottom Sheets & Navigation',
                description:
                    'Smoother sheet transitions, responsive touch drawers, and instant tab bar switches designed for mobile screens.',
                tag: 'Improved',
                icon: 'Zap',
            },
            {
                title: 'Interactive Notification Hub',
                description:
                    'Instant alerts for coursework milestones, XP gains, and level-ups delivered right to your top bar.',
                tag: 'Improved',
                icon: 'Sparkles',
            },
        ],
    },
    {
        version: 'v6.1.0',
        title: 'Activities Hub, Streaks & Soundtrack',
        date: 'September 2026',
        summary:
            'Consolidated activities workspace with streak freeze protections, submission tracking, and ambient study music.',
        features: [
            {
                title: 'Unified Activities Hub',
                description:
                    'View assignments, prelims, midterms, and finals in a unified gradebook stream with real-time submission tracking.',
                tag: 'New',
                icon: 'GraduationCap',
            },
            {
                title: 'Streak Protection & Rewards',
                description:
                    'Protect your daily study streak with streak freezes and earn bonus XP multipliers for regular participation.',
                tag: 'Improved',
                icon: 'Flame',
            },
            {
                title: 'Focus Audio Soundtrack',
                description:
                    'Listen to curated lo-fi and ambient study tracks directly inside the student workspace.',
                tag: 'New',
                icon: 'Music',
            },
        ],
    },
    {
        version: 'v6.0.0',
        title: 'LSI Student Platform Launch',
        date: 'August 2026',
        summary:
            'The Learning Systems Intelligence (LSI) student experience with interactive gamification, faster lessons, and modern mobile views.',
        features: [
            {
                title: 'Interactive Gamification & Leaderboards',
                description:
                    'Level up your student profile, collect achievement badges, and climb section rankings with each completed lesson.',
                tag: 'New',
                icon: 'Award',
            },
            {
                title: 'Lightning-Fast Course Navigation',
                description:
                    'Browse courses, lesson modules, and interactive quizzes with zero reload latency on any device.',
                tag: 'New',
                icon: 'Rocket',
            },
        ],
    },
];
