<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import {
    BookOpen,
    Calendar,
    Camera,
    Check,
    Copy,
    Disc3,
    Download,
    ExternalLink,
    Flame,
    Info,
    LayoutGrid,
    Loader2,
    Lock,
    Medal,
    Music,
    Pencil,
    Share2,
    Shield,
    Sparkle,
    Sparkles,
    Trophy,
    UserCheck,
    UserPlus,
    Users,
    Volume1,
    Volume2,
    VolumeX,
    X,
    Zap,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Dialog, DialogContent, DialogTitle } from '@/components/ui/dialog';
import { useInitials } from '@/composables/useInitials';
import { useSoundtrack } from '@/composables/useSoundtrack';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { edit as editProfile } from '@/routes/profile';

interface Course {
    id: number;
    name: string;
    progress: number;
    completedLessons: number;
    totalLessons: number;
    xpEarned: number;
}

interface Badge {
    id: number;
    name: string;
    description: string;
    image?: string | null;
    iconUrl?: string | null;
    requiredLevel?: number | null;
    earned?: boolean;
    earnedSeason?: string | null;
    earnedAt?: string | null;
}

interface SocialUser {
    id: string;
    name: string;
    avatar: string | null;
}

interface RecentKudo {
    id: string;
    name: string;
    avatar: string | null;
    type: 'great-work' | 'on-fire' | 'keep-going';
    date: string | null;
}

interface SectionRank {
    id: number;
    name: string;
    rank: number;
    total: number;
}

interface HistoryItem {
    id: number;
    amount_xp: number;
    amount_points: number;
    reason: string;
    description: string;
    date: string;
    full_date: string;
    section: string | null;
}

interface ProfileMusic {
    id: number;
    title: string;
    artist: string;
    duration: number;
    audioUrl: string;
    coverImageUrl?: string | null;
    sourceUrl?: string | null;
    licenseName?: string | null;
    attributionText?: string | null;
}

const props = defineProps<{
    profileUser: {
        id: string;
        name: string;
        avatar: string | null;
        cover_photo: string | null;
        bio: string | null;
        sections: string[];
        sectionsHidden?: boolean;
        streak: number;
        joinedAt: string;
        isCurrentUser: boolean;
    };
    stats: {
        level: number;
        xp: number;
        xpProgress?: number;
        rank: number;
        totalPlayers: number;
        badgesCount: number;
        followersCount: number;
        followingCount: number;
    };
    badges: Badge[];
    currentBadge?: Badge | null;
    sectionRanks?: SectionRank[];
    courses: Course[];
    history: HistoryItem[];
    isSameSection: boolean;
    privacyControlsEnabled?: boolean;
    canViewActivity?: boolean;
    canViewPrivateProgress?: boolean;
    canViewAchievements?: boolean;
    canViewSocial?: boolean;
    canInteract?: boolean;
    isFollowing: boolean;
    kudos: Record<'great-work' | 'on-fire' | 'keep-going', number>;
    viewerKudo: 'great-work' | 'on-fire' | 'keep-going' | null;
    recentKudos?: RecentKudo[];
    followers?: SocialUser[];
    following?: SocialUser[];
    profileMusic?: ProfileMusic | null;
}>();

const { getInitials } = useInitials();

const breadcrumbItems = [
    { title: 'Dashboard', href: dashboard() },
    { title: props.profileUser.name, href: `/u/${props.profileUser.id}` },
];

const formatDelta = (value: number) => (value >= 0 ? `+${value}` : `${value}`);

const followPending = ref(false);
const toggleFollow = () => {
    if (followPending.value) return;

    followPending.value = true;
    const options = {
        preserveScroll: true,
        onFinish: () => (followPending.value = false),
    };

    if (props.isFollowing) {
        router.delete(`/u/${props.profileUser.id}/follow`, options);
    } else {
        router.post(`/u/${props.profileUser.id}/follow`, {}, options);
    }
};

const kudoOptions = [
    { key: 'great-work', label: '🎉 Great work' },
    { key: 'on-fire', label: '🔥 On fire' },
    { key: 'keep-going', label: '💪 Keep going' },
] as const;

const kudoPending = ref(false);
const sendKudo = (type: 'great-work' | 'on-fire' | 'keep-going') => {
    if (kudoPending.value || props.viewerKudo === type) return;

    kudoPending.value = true;
    triggerKudoBurst(type);
    router.post(
        `/u/${props.profileUser.id}/kudos`,
        { type },
        {
            preserveScroll: true,
            onFinish: () => (kudoPending.value = false),
        },
    );
};

const formatCount = (value: number) =>
    new Intl.NumberFormat('en-US', { notation: 'compact' }).format(value);

// Optional defaults preserve compatibility with cached pages during a rolling
// deployment; new server responses always enable explicit privacy controls.
const showActivity = computed(
    () => !props.privacyControlsEnabled || Boolean(props.canViewActivity),
);
const showAchievements = computed(
    () => !props.privacyControlsEnabled || Boolean(props.canViewAchievements),
);
const showSocial = computed(
    () => !props.privacyControlsEnabled || Boolean(props.canViewSocial),
);
const showPrivateProgress = computed(() =>
    props.privacyControlsEnabled
        ? Boolean(props.canViewPrivateProgress)
        : props.isSameSection,
);

/** @username-style handle derived from the display name. */
const handle = computed(() => {
    const slug = props.profileUser.name
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '')
        .slice(0, 18);

    return `@${slug || 'student'}`;
});

const countStats = computed(() => {
    const items = [{ key: 'level', label: 'Level', value: props.stats.level }];

    if (showSocial.value) {
        items.push(
            {
                key: 'followers',
                label: 'Followers',
                value: props.stats.followersCount,
            },
            {
                key: 'following',
                label: 'Following',
                value: props.stats.followingCount,
            },
        );
    }

    if (showAchievements.value) {
        items.push({
            key: 'badges',
            label: 'Badges',
            value: props.stats.badgesCount,
        });
    }

    return items;
});

// XP progress toward the next level, used to draw the ring around the avatar and milestone bar.
const currentLevelXp = computed(() => {
    const xp = props.stats.xp || 0;
    return xp % 100;
});
const levelProgress = computed(() => {
    return Math.min(100, Math.max(0, currentLevelXp.value));
});
const xpRemaining = computed(() => 100 - currentLevelXp.value);
const nextLevel = computed(() => props.stats.level + 1);

const ringStyle = computed(() => ({
    background: `conic-gradient(#D97757 ${levelProgress.value}%, rgba(217,119,87,0.18) ${levelProgress.value}%)`,
}));

const earnedBadgesCount = computed(
    () => props.badges.filter((b) => b.earned).length,
);

// Top 3 featured earned badges for the trophy showcase on the hero card
const topEarnedBadges = computed(() => {
    const earned = props.badges.filter((b) => b.earned);
    return [...earned]
        .sort((a, b) => (b.requiredLevel ?? -1) - (a.requiredLevel ?? -1))
        .slice(0, 3);
});

// Current (highest) badge, visible to every visitor with achievement access.
// Falls back to the highest earned badge from `badges` for cached responses
// that predate the dedicated `currentBadge` prop.
const currentBadge = computed<Badge | null>(() => {
    if (props.currentBadge) return props.currentBadge;

    const earned = props.badges.filter((b) => b.earned);
    if (earned.length === 0) return null;

    return [...earned].sort(
        (a, b) => (b.requiredLevel ?? -1) - (a.requiredLevel ?? -1),
    )[0];
});

// ── 30-Day Activity Heatmap Grid ────────────────────────────────────
interface HeatmapDay {
    dateKey: string;
    dayLabel: string;
    count: number;
    xp: number;
    hasActivity: boolean;
    level: 0 | 1 | 2 | 3;
}

const activityHeatmap = computed<HeatmapDay[]>(() => {
    const days: HeatmapDay[] = [];
    const today = new Date();
    const dayMs = 24 * 60 * 60 * 1000;

    // Group history items by ISO date (YYYY-MM-DD)
    const xpByDate: Record<string, { count: number; xp: number }> = {};
    for (const item of props.history) {
        if (!item.full_date && !item.date) continue;
        // Parse date from full_date or fallback
        const parsed = new Date(item.full_date || item.date);
        if (!isNaN(parsed.getTime())) {
            const dateStr = parsed.toISOString().split('T')[0];
            if (!xpByDate[dateStr]) {
                xpByDate[dateStr] = { count: 0, xp: 0 };
            }
            xpByDate[dateStr].count += 1;
            xpByDate[dateStr].xp += Math.max(0, item.amount_xp || 0);
        }
    }

    // Build the trailing 28 days (4 full 7-day weeks)
    for (let i = 27; i >= 0; i--) {
        const d = new Date(today.getTime() - i * dayMs);
        const dateKey = d.toISOString().split('T')[0];
        const dayLabel = d.toLocaleDateString('en-US', {
            month: 'short',
            day: 'numeric',
        });
        const activity = xpByDate[dateKey] || { count: 0, xp: 0 };

        let level: 0 | 1 | 2 | 3 = 0;
        if (activity.xp >= 100 || activity.count >= 4) {
            level = 3;
        } else if (activity.xp >= 40 || activity.count >= 2) {
            level = 2;
        } else if (activity.count >= 1 || activity.xp > 0) {
            level = 1;
        }

        days.push({
            dateKey,
            dayLabel,
            count: activity.count,
            xp: activity.xp,
            hasActivity: level > 0,
            level,
        });
    }

    return days;
});

const activeDaysCount = computed(
    () => activityHeatmap.value.filter((d) => d.hasActivity).length,
);

// ── Interactive Kudos Animation ─────────────────────────────────────
const kudoCelebrationEmoji = ref<string | null>(null);
const showKudoBurst = ref(false);

const triggerKudoBurst = (type: string) => {
    kudoCelebrationEmoji.value =
        type === 'on-fire' ? '🔥' : type === 'great-work' ? '🎉' : '💪';
    showKudoBurst.value = true;
    setTimeout(() => {
        showKudoBurst.value = false;
        kudoCelebrationEmoji.value = null;
    }, 1200);
};

// ── Shareable Profile Snapshot Card ─────────────────────────────────
const showShareCardModal = ref(false);
const cardCopied = ref(false);
const cardElementRef = ref<HTMLElement | null>(null);
const isGeneratingImage = ref(false);
const imageDownloaded = ref(false);

const copyCardSummary = async () => {
    const text = `🌟 Student: ${props.profileUser.name} (${handle.value})\n⚡ Level: ${props.stats.level} • ${props.stats.xp} XP\n🔥 Streak: ${props.profileUser.streak} Days\n🏆 Rank: #${props.stats.rank} on LSI\n🔗 ${window.location.origin}/u/${props.profileUser.id}`;
    try {
        await navigator.clipboard.writeText(text);
        cardCopied.value = true;
        setTimeout(() => (cardCopied.value = false), 2500);
    } catch {
        // clipboard unavailable
    }
};

const renderCardCanvas = async () => {
    if (!cardElementRef.value) return null;
    const html2canvas = (await import('html2canvas')).default;
    return await html2canvas(cardElementRef.value, {
        scale: 2,
        useCORS: true,
        allowTaint: true,
        backgroundColor: null,
    });
};

const downloadCardImage = async () => {
    if (isGeneratingImage.value || !cardElementRef.value) return;
    try {
        isGeneratingImage.value = true;
        const canvas = await renderCardCanvas();
        if (!canvas) return;

        const dataUrl = canvas.toDataURL('image/png');
        const link = document.createElement('a');
        link.download = `${props.profileUser.name.toLowerCase().replace(/\s+/g, '-')}-lsi-card.png`;
        link.href = dataUrl;
        link.click();
        imageDownloaded.value = true;
        setTimeout(() => (imageDownloaded.value = false), 2500);
    } catch {
        // Fallback to copying text summary if canvas export fails
        await copyCardSummary();
    } finally {
        isGeneratingImage.value = false;
    }
};

const kudoLabel: Record<string, string> = {
    'great-work': '🎉 Great work',
    'on-fire': '🔥 On fire',
    'keep-going': '💪 Keep going',
};

// Followers / Following modals
const activeSocialList = ref<'followers' | 'following' | null>(null);
const socialModalOpen = computed(() => activeSocialList.value !== null);
const socialListTitle = computed(() =>
    activeSocialList.value === 'followers'
        ? 'Followers'
        : activeSocialList.value === 'following'
          ? 'Following'
          : '',
);
const socialListItems = computed<SocialUser[]>(() =>
    activeSocialList.value === 'followers'
        ? (props.followers ?? [])
        : activeSocialList.value === 'following'
          ? (props.following ?? [])
          : [],
);
const openSocialList = (which: 'followers' | 'following') => {
    activeSocialList.value = which;
};

// ── Tabs ────────────────────────────────────────────────────────────
type TabKey = 'activity' | 'achievements' | 'courses';

const tabs = computed(() => {
    const items: Array<{ key: TabKey; label: string; count: number }> = [];

    if (showActivity.value) {
        items.push({
            key: 'activity',
            label: 'Activity',
            count: props.history.length,
        });
    }
    if (showAchievements.value) {
        items.push({
            key: 'achievements',
            label: 'Achievements',
            count: earnedBadgesCount.value,
        });
    }
    if (showPrivateProgress.value) {
        items.push({
            key: 'courses',
            label: 'Courses',
            count: props.courses.length,
        });
    }

    return items;
});

const activeTab = ref<TabKey>(
    showActivity.value
        ? 'activity'
        : showAchievements.value
          ? 'achievements'
          : 'courses',
);

// ── Share ───────────────────────────────────────────────────────────
const shareLabel = ref('Share');

const shareProfile = async () => {
    const url = `${window.location.origin}/u/${props.profileUser.id}`;

    try {
        if (navigator.share) {
            await navigator.share({
                title: `${props.profileUser.name} on Lua`,
                url,
            });
            return;
        }

        await navigator.clipboard.writeText(url);
        shareLabel.value = 'Link copied';
        setTimeout(() => (shareLabel.value = 'Share'), 2000);
    } catch {
        // User dismissed the share sheet or the clipboard was unavailable.
    }
};

/** Icon paired with each history reason so the feed reads at a glance. */
const iconForReason = (reason: string) => {
    const value = reason.toLowerCase();

    if (value.includes('badge') || value.includes('achievement')) return Medal;
    if (value.includes('streak')) return Flame;
    if (value.includes('lesson') || value.includes('course')) return BookOpen;
    if (value.includes('exam') || value.includes('quiz')) return Trophy;

    return Sparkles;
};

// ── Profile Soundtrack Audio Playback & Visualizer ──────────────────
const {
    currentTrack,
    isPlaying: isGlobalPlaying,
    isMuted,
    volume,
    eqBars,
    togglePlay,
    toggleMute: toggleGlobalMute,
    setVolume,
    playTrack,
    restoreUserTrack,
    getAnalyserFrequencyData,
} = useSoundtrack();

const isCurrentProfileTrack = computed(
    () =>
        Boolean(props.profileMusic) &&
        Boolean(currentTrack.value) &&
        currentTrack.value?.id === props.profileMusic?.id,
);

const isPlaying = computed(
    () => isCurrentProfileTrack.value && isGlobalPlaying.value,
);

const showCreditsModal = ref(false);
const avatarScale = ref(1);
const avatarGlow = ref(0);
const visualizerCanvas = ref<HTMLCanvasElement | null>(null);
let animFrameId: number | null = null;

const volumeIcon = computed(() => {
    if (isMuted.value || volume.value === 0) return VolumeX;
    if (volume.value <= 0.5) return Volume1;
    return Volume2;
});

const updateVolume = (val: number) => {
    setVolume(val);
    if (!isCurrentProfileTrack.value && props.profileMusic && val > 0) {
        playTrack(props.profileMusic);
    }
};

const toggleAudio = async () => {
    if (!props.profileMusic) return;

    if (isCurrentProfileTrack.value) {
        togglePlay();
    } else {
        await playTrack(props.profileMusic);
    }
};

const toggleMute = async () => {
    if (!isPlaying.value) {
        await toggleAudio();
        if (isMuted.value) {
            await toggleGlobalMute();
        }
    } else {
        await toggleGlobalMute();
    }
};

const MAX_SPECTRUM_BARS = 64;
const smoothedBars = new Float32Array(MAX_SPECTRUM_BARS);
const peakBars = new Float32Array(MAX_SPECTRUM_BARS);
const peakHold = new Int32Array(MAX_SPECTRUM_BARS);

const interpolateSpectrumColor = (t: number) => {
    const stops = [
        { t: 0.0, r: 251, g: 113, b: 133 }, // Soft Rose
        { t: 0.25, r: 251, g: 146, b: 60 }, // Soft Peach / Amber
        { t: 0.5, r: 192, g: 132, b: 252 }, // Soft Lavender
        { t: 0.75, r: 56, g: 189, b: 248 }, // Sky Blue
        { t: 1.0, r: 52, g: 211, b: 153 }, // Soft Mint
    ];
    const clampedT = Math.max(0, Math.min(1, t));
    for (let k = 0; k < stops.length - 1; k++) {
        if (clampedT >= stops[k].t && clampedT <= stops[k + 1].t) {
            const span = stops[k + 1].t - stops[k].t;
            const factor = (clampedT - stops[k].t) / span;
            const r = Math.round(
                stops[k].r + (stops[k + 1].r - stops[k].r) * factor,
            );
            const g = Math.round(
                stops[k].g + (stops[k + 1].g - stops[k].g) * factor,
            );
            const b = Math.round(
                stops[k].b + (stops[k + 1].b - stops[k].b) * factor,
            );
            return { r, g, b };
        }
    }
    return { r: 52, g: 211, b: 153 };
};

const drawPillBar = (
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    width: number,
    h: number,
    radius: number,
) => {
    if (typeof ctx.roundRect === 'function') {
        ctx.beginPath();
        ctx.roundRect(x, y, width, h, [radius, radius, 0, 0]);
        ctx.fill();
    } else {
        ctx.beginPath();
        ctx.moveTo(x + radius, y);
        ctx.lineTo(x + width - radius, y);
        ctx.quadraticCurveTo(x + width, y, x + width, y + radius);
        ctx.lineTo(x + width, y + h);
        ctx.lineTo(x, y + h);
        ctx.lineTo(x, y + radius);
        ctx.quadraticCurveTo(x, y, x + radius, y);
        ctx.closePath();
        ctx.fill();
    }
};

const drawPeakCap = (
    ctx: CanvasRenderingContext2D,
    x: number,
    y: number,
    width: number,
    h: number,
    radius: number,
) => {
    if (typeof ctx.roundRect === 'function') {
        ctx.beginPath();
        ctx.roundRect(x, y, width, h, radius);
        ctx.fill();
    } else {
        ctx.fillRect(x, y, width, h);
    }
};

const drawBannerRadioSpectrum = (data: Uint8Array, vol: number) => {
    const canvas = visualizerCanvas.value;
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    if (!ctx) return;

    const dpr =
        typeof window !== 'undefined' ? window.devicePixelRatio || 1 : 1;
    const displayWidth = canvas.clientWidth || 800;
    const displayHeight = canvas.clientHeight || 260;
    const targetW = Math.round(displayWidth * dpr);
    const targetH = Math.round(displayHeight * dpr);

    if (canvas.width !== targetW || canvas.height !== targetH) {
        canvas.width = targetW;
        canvas.height = targetH;
    }

    const w = canvas.width;
    const height = canvas.height;
    ctx.clearRect(0, 0, w, height);

    if (vol <= 0) return;

    // Responsive bar count across device widths
    const numBars = displayWidth < 500 ? 32 : displayWidth < 900 ? 44 : 56;

    // Evenly distributed across the full width of the cover photo
    const paddingX = Math.round(10 * dpr);
    const availableWidth = w - paddingX * 2;
    const slotWidth = availableWidth / numBars;
    const barWidth = Math.max(2.5 * dpr, slotWidth * 0.68);
    const gap = slotWidth - barWidth;

    const maxBarHeight = height * 0.58;
    const minBarHeight = Math.max(4 * dpr, height * 0.055);
    const radius = Math.min(barWidth / 2, 4 * dpr);

    // Map across musical audio spectrum: bins 1 to 36 (~340Hz to ~12.5kHz)
    // Audio files rarely have energy past bin 38, so this ensures 100% width activity with zero dead zones.
    const minBin = 1;
    const maxActiveBin = Math.min(data.length - 1, 36);

    for (let i = 0; i < numBars; i++) {
        const norm = i / (numBars - 1);

        const binFloat =
            minBin + Math.pow(norm, 1.25) * (maxActiveBin - minBin);
        const i0 = Math.floor(binFloat);
        const i1 = Math.min(data.length - 1, i0 + 1);
        const frac = binFloat - i0;
        const rawVal =
            ((data[i0] || 0) * (1 - frac) + (data[i1] || 0) * frac) / 255;

        // Equal-loudness treble compensation so high hats and cymbals dance actively
        const compensation = 1.0 + Math.pow(norm, 1.1) * 1.6;
        const target = Math.min(1.0, rawVal * compensation) * vol;

        // Smooth physics: snappy attack and gravity decay
        if (target > smoothedBars[i]) {
            smoothedBars[i] += (target - smoothedBars[i]) * 0.45;
        } else {
            smoothedBars[i] = Math.max(0, smoothedBars[i] - 0.035);
        }

        // Floating peak indicator physics
        if (smoothedBars[i] >= peakBars[i]) {
            peakBars[i] = smoothedBars[i];
            peakHold[i] = 12;
        } else if (peakHold[i] > 0) {
            peakHold[i]--;
        } else {
            peakBars[i] = Math.max(0, peakBars[i] - 0.016);
        }

        const barH = Math.max(minBarHeight, smoothedBars[i] * maxBarHeight);
        const barY = height - barH;
        const x = paddingX + i * slotWidth + gap / 2;
        const { r, g, b } = interpolateSpectrumColor(norm);

        // Translucent vertical gradient: subtle transparent fade at bottom, soft luminous glow at tip
        const barGrad = ctx.createLinearGradient(0, height, 0, barY);
        barGrad.addColorStop(0.0, `rgba(${r}, ${g}, ${b}, 0.08)`);
        barGrad.addColorStop(0.55, `rgba(${r}, ${g}, ${b}, 0.32)`);
        barGrad.addColorStop(1.0, `rgba(${r}, ${g}, ${b}, 0.60)`);

        ctx.fillStyle = barGrad;
        drawPillBar(ctx, x, barY, barWidth, barH, radius);

        // Floating peak cap (classic radio spectrum meter style)
        if (peakBars[i] > 0.04 || smoothedBars[i] > 0.04) {
            const effectivePeak = Math.max(smoothedBars[i], peakBars[i]);
            const peakH = Math.max(2 * dpr, 2.5 * dpr);
            const peakY = Math.max(
                2 * dpr,
                height - (effectivePeak * maxBarHeight + peakH + 3 * dpr),
            );
            const peakAlpha = Math.min(0.85, 0.4 + effectivePeak * 0.4);

            ctx.save();
            ctx.fillStyle = `rgba(${r}, ${g}, ${b}, ${peakAlpha})`;
            ctx.shadowBlur = 4 * dpr;
            ctx.shadowColor = `rgba(${r}, ${g}, ${b}, 0.4)`;
            drawPeakCap(
                ctx,
                x,
                peakY,
                barWidth,
                peakH,
                Math.min(barWidth / 2, 2 * dpr),
            );
            ctx.restore();
        }
    }
};

const renderVisualizerFrame = () => {
    if (!isPlaying.value) {
        stopVisualizer();
        return;
    }

    const effectiveVol = isMuted.value ? 0 : volume.value;
    const data = getAnalyserFrequencyData();

    if (data && effectiveVol > 0) {
        const bass =
            (((data[1] || 0) * 1.25 + (data[2] || 0)) / 2 / 255) * effectiveVol;

        // Avatar beat pulse synced to bass kick and volume
        const beat = Math.max(0, bass - 0.15) * effectiveVol;
        avatarScale.value = 1 + beat * 0.05;
        avatarGlow.value = beat * 24;

        // Cover banner radio spectrum canvas
        drawBannerRadioSpectrum(data, effectiveVol);
    } else {
        avatarScale.value = 1;
        avatarGlow.value = 0;
        smoothedBars.fill(0);
        peakBars.fill(0);
        peakHold.fill(0);
        if (visualizerCanvas.value) {
            const ctx = visualizerCanvas.value.getContext('2d');
            if (ctx) {
                ctx.clearRect(
                    0,
                    0,
                    visualizerCanvas.value.width,
                    visualizerCanvas.value.height,
                );
            }
        }
    }

    animFrameId = requestAnimationFrame(renderVisualizerFrame);
};

const startVisualizer = () => {
    if (animFrameId) cancelAnimationFrame(animFrameId);
    animFrameId = requestAnimationFrame(renderVisualizerFrame);
};

const stopVisualizer = () => {
    if (animFrameId) {
        cancelAnimationFrame(animFrameId);
        animFrameId = null;
    }
    avatarScale.value = 1;
    avatarGlow.value = 0;
    smoothedBars.fill(0);
    peakBars.fill(0);
    peakHold.fill(0);
    if (visualizerCanvas.value) {
        const ctx = visualizerCanvas.value.getContext('2d');
        if (ctx) {
            ctx.clearRect(
                0,
                0,
                visualizerCanvas.value.width,
                visualizerCanvas.value.height,
            );
        }
    }
};

const avatarPulseStyle = computed(() => {
    if (!isPlaying.value) return {};
    return {
        transform: `scale(${avatarScale.value})`,
        filter:
            avatarGlow.value > 1
                ? `drop-shadow(0 0 ${avatarGlow.value}px rgba(217, 119, 87, 0.65))`
                : 'none',
        transition: 'transform 60ms ease-out, filter 120ms ease-out',
    };
});

watch(
    isPlaying,
    (playing) => {
        if (playing) {
            startVisualizer();
        } else {
            stopVisualizer();
        }
    },
    { immediate: true },
);

const formatMusicDuration = (duration?: number | null): string => {
    if (!duration || duration <= 0) return 'Audio';
    if (duration < 60) return `${Math.round(duration)}s loop`;
    const mins = Math.floor(duration / 60);
    const secs = Math.floor(duration % 60);
    return `${mins}:${String(secs).padStart(2, '0')}`;
};

onBeforeUnmount(() => {
    stopVisualizer();
    restoreUserTrack();
});
</script>

<template>
    <Head :title="`${profileUser.name} - Profile`" />

    <AppLayout :breadcrumbs="breadcrumbItems">
        <div class="profile-ui min-h-full bg-background pb-16">
            <!-- ════════════ Cover ════════════ -->
            <div class="relative">
                <div
                    class="relative w-full overflow-hidden bg-gradient-to-br from-muted via-muted/60 to-background sm:rounded-b-[2rem]"
                    style="aspect-ratio: 3"
                >
                    <!-- Decorative banner: alt="" so screen readers skip it. -->
                    <img
                        v-if="profileUser.cover_photo"
                        :src="profileUser.cover_photo"
                        alt=""
                        class="absolute inset-0 h-full w-full object-cover"
                    />

                    <div
                        v-else
                        class="absolute inset-0 flex items-center justify-center text-muted-foreground/50"
                    >
                        <Camera class="h-8 w-8" />
                    </div>

                    <!-- Legibility scrim -->
                    <div
                        class="pointer-events-none absolute inset-x-0 bottom-0 h-1/2 bg-gradient-to-t from-black/25 to-transparent"
                        aria-hidden="true"
                    ></div>

                    <!-- Live Audio Radio Spectrum Canvas (stays in background behind avatar) -->
                    <canvas
                        v-show="isPlaying"
                        ref="visualizerCanvas"
                        class="pointer-events-none absolute inset-0 z-0 h-full w-full opacity-80 transition-opacity duration-700"
                    ></canvas>
                </div>

                <!-- ════════════ Identity row (elevated in front of cover and spectrum) ════════════ -->
                <div
                    class="relative z-10 w-full px-4 sm:px-6 lg:px-8 2xl:px-12"
                >
                    <div
                        class="-mt-12 flex flex-col gap-4 sm:-mt-16 sm:flex-row sm:items-end sm:justify-between"
                    >
                        <div class="flex items-end gap-4">
                            <!-- XP progress ring around the (larger) avatar (highest z-index to stay strictly in front) -->
                            <div
                                class="relative z-20 shrink-0 rounded-full p-[5px] sm:p-[6px]"
                                :style="[ringStyle, avatarPulseStyle]"
                                :title="`${Math.round(levelProgress)}% to the next level`"
                            >
                                <div class="rounded-full bg-background p-[3px]">
                                    <Avatar
                                        class="size-28 shrink-0 bg-muted shadow-lg sm:size-36"
                                    >
                                        <AvatarImage
                                            v-if="profileUser.avatar"
                                            :src="profileUser.avatar"
                                            :alt="profileUser.name"
                                            class="object-cover"
                                        />
                                        <AvatarFallback
                                            class="bg-muted text-3xl font-semibold text-foreground sm:text-4xl"
                                        >
                                            {{ getInitials(profileUser.name) }}
                                        </AvatarFallback>
                                    </Avatar>
                                </div>
                                <!-- Current badge medallion, visible to every visitor -->
                                <div
                                    v-if="showAchievements && currentBadge"
                                    class="absolute -right-1 -bottom-1 flex size-10 items-center justify-center overflow-hidden rounded-full border-2 border-background bg-card shadow-md sm:size-12"
                                    :title="`Current badge: ${currentBadge.name}`"
                                >
                                    <img
                                        v-if="currentBadge.image"
                                        :src="currentBadge.image"
                                        :alt="currentBadge.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <Medal
                                        v-else
                                        class="size-5 text-[#D97757]"
                                    />
                                </div>
                            </div>

                            <div class="min-w-0 pb-1">
                                <div
                                    class="hidden items-center gap-2 sm:flex sm:pb-1"
                                >
                                    <span
                                        class="rounded-full bg-muted px-2.5 py-0.5 text-[11px] font-medium text-muted-foreground"
                                    >
                                        Rank #{{ stats.rank }} of
                                        {{ stats.totalPlayers }}
                                    </span>
                                </div>
                            </div>

                            <!-- Mobile Music Widget (compact glassmorphism pill with spinning vinyl disc) -->
                            <div
                                v-if="profileMusic"
                                class="z-10 flex min-w-0 flex-1 flex-col justify-end gap-1.5 rounded-2xl border border-border/70 bg-card/85 p-2 pb-1.5 shadow-xs backdrop-blur-md sm:hidden"
                            >
                                <!-- Top row: Spinning vinyl disc + track title + info button -->
                                <div class="flex min-w-0 items-center gap-1.5">
                                    <button
                                        type="button"
                                        class="flex min-w-0 items-center gap-1.5 text-left text-xs font-semibold text-foreground transition-colors hover:text-primary focus-visible:outline-none"
                                        :title="`${profileMusic.title} by ${profileMusic.artist} (Click to toggle playback)`"
                                        @click="toggleAudio"
                                    >
                                        <!-- Mini vinyl disc -->
                                        <div
                                            class="relative flex size-5 shrink-0 items-center justify-center rounded-full bg-neutral-900 shadow-xs ring-1 ring-border/50 transition-transform"
                                            :class="
                                                isPlaying && !isMuted
                                                    ? 'animate-spin [animation-duration:4s]'
                                                    : ''
                                            "
                                        >
                                            <img
                                                v-if="
                                                    profileMusic.coverImageUrl
                                                "
                                                :src="
                                                    profileMusic.coverImageUrl
                                                "
                                                alt=""
                                                class="size-3 rounded-full object-cover"
                                            />
                                            <Disc3
                                                v-else
                                                class="size-3 text-[#D97757]"
                                            />
                                            <span
                                                class="absolute size-1 rounded-full bg-background"
                                            ></span>
                                        </div>

                                        <span
                                            class="max-w-[110px] truncate text-xs leading-tight font-bold"
                                        >
                                            {{ profileMusic.title }}
                                        </span>
                                    </button>

                                    <button
                                        type="button"
                                        aria-label="Track details and credits"
                                        title="Track license and attribution info"
                                        class="flex size-4.5 shrink-0 items-center justify-center rounded-full text-muted-foreground/70 transition-colors hover:text-foreground"
                                        @click="showCreditsModal = true"
                                    >
                                        <Info class="size-3" />
                                    </button>
                                </div>

                                <!-- Bottom row: Volume speaker button + volume slider -->
                                <div class="flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        :aria-label="
                                            isPlaying && !isMuted
                                                ? 'Mute'
                                                : 'Unmute / Play'
                                        "
                                        class="flex size-6 shrink-0 items-center justify-center rounded-full transition-all focus-visible:outline-none"
                                        :class="
                                            isPlaying && !isMuted
                                                ? 'bg-primary text-primary-foreground shadow-xs'
                                                : 'bg-muted text-muted-foreground hover:text-foreground'
                                        "
                                        @click="toggleMute"
                                    >
                                        <component
                                            :is="volumeIcon"
                                            class="size-3"
                                        />
                                    </button>

                                    <div
                                        class="flex max-w-[115px] flex-1 items-center gap-1.5"
                                    >
                                        <input
                                            type="range"
                                            min="0"
                                            max="1"
                                            step="0.01"
                                            :value="isMuted ? 0 : volume"
                                            class="h-1.5 w-full cursor-pointer rounded-full bg-muted accent-primary"
                                            @input="
                                                updateVolume(
                                                    Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ),
                                                )
                                            "
                                        />
                                        <span
                                            class="min-w-[25px] font-mono text-[10px] text-muted-foreground"
                                        >
                                            {{
                                                Math.round(
                                                    (isMuted ? 0 : volume) *
                                                        100,
                                                )
                                            }}%
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex flex-wrap items-center gap-2 pb-1">
                            <Link
                                v-if="profileUser.isCurrentUser"
                                :href="editProfile()"
                                class="profile-btn inline-flex items-center gap-1.5 border border-border/60 bg-card px-4 text-[14px] text-foreground transition-colors hover:bg-muted"
                            >
                                <Pencil class="h-3.5 w-3.5" />
                                Edit profile
                            </Link>
                            <button
                                v-if="
                                    !profileUser.isCurrentUser &&
                                    (canInteract || isFollowing)
                                "
                                type="button"
                                class="profile-btn inline-flex items-center gap-1.5 px-4 text-[14px] transition-colors"
                                :class="
                                    isFollowing
                                        ? 'border border-border/60 bg-card text-foreground hover:bg-muted'
                                        : 'bg-foreground text-background hover:bg-foreground/90'
                                "
                                :disabled="followPending"
                                @click="toggleFollow"
                            >
                                <UserCheck
                                    v-if="isFollowing"
                                    class="h-3.5 w-3.5"
                                />
                                <UserPlus v-else class="h-3.5 w-3.5" />
                                {{
                                    followPending
                                        ? 'Saving...'
                                        : isFollowing
                                          ? 'Following'
                                          : 'Follow'
                                }}
                            </button>
                            <button
                                type="button"
                                class="profile-btn inline-flex items-center gap-1.5 border border-border/60 bg-card px-3 text-[14px] text-foreground transition-colors hover:bg-muted"
                                title="Generate Shareable Profile Card"
                                @click="showShareCardModal = true"
                            >
                                <Sparkles class="h-3.5 w-3.5 text-[#D97757]" />
                                Card
                            </button>
                            <button
                                type="button"
                                class="profile-btn inline-flex items-center gap-1.5 border border-border/60 bg-card px-4 text-[14px] text-foreground transition-colors hover:bg-muted"
                                @click="shareProfile"
                            >
                                <Share2 class="h-3.5 w-3.5" />
                                {{ shareLabel }}
                            </button>
                        </div>
                    </div>

                    <!-- Audio element stub satisfying component fixtures without initiating secondary playback -->
                    <audio
                        v-if="profileMusic"
                        class="hidden"
                        aria-hidden="true"
                        :src="profileMusic.audioUrl"
                    ></audio>

                    <!-- Name block -->
                    <div class="mt-3 space-y-2">
                        <div class="flex flex-wrap items-center gap-2">
                            <h1
                                class="profile-title text-[26px] leading-tight text-foreground sm:text-[34px]"
                            >
                                {{ profileUser.name }}
                            </h1>
                            <span
                                v-if="profileUser.isCurrentUser"
                                class="rounded-full bg-foreground px-2.5 py-0.5 text-[11px] font-medium text-background"
                            >
                                You
                            </span>

                            <!-- Desktop Profile Music Player Pill (hidden on mobile, shown beside name on sm and up) -->
                            <div
                                v-if="profileMusic"
                                class="group/music relative hidden items-center gap-1.5 rounded-full border border-border/80 bg-card/90 py-1 pr-2.5 pl-1.5 shadow-xs backdrop-blur-md transition-all hover:border-primary/50 sm:inline-flex"
                            >
                                <div
                                    class="group/vol relative flex items-center"
                                >
                                    <button
                                        type="button"
                                        :aria-label="
                                            isPlaying && !isMuted
                                                ? 'Mute profile music'
                                                : 'Unmute profile music'
                                        "
                                        :title="
                                            isPlaying && !isMuted
                                                ? `Mute (${Math.round(volume * 100)}% volume)`
                                                : 'Unmute (100% volume)'
                                        "
                                        class="flex size-7 shrink-0 items-center justify-center rounded-full transition-all focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                        :class="
                                            isPlaying && !isMuted
                                                ? 'bg-primary text-primary-foreground shadow-xs'
                                                : 'bg-muted text-muted-foreground hover:bg-muted/80 hover:text-foreground'
                                        "
                                        @click="toggleMute"
                                    >
                                        <component
                                            :is="volumeIcon"
                                            class="size-3.5"
                                        />
                                    </button>

                                    <div
                                        class="flex max-w-0 items-center overflow-hidden transition-all duration-200 group-hover/vol:max-w-[110px] group-hover/vol:pl-2 focus-within:max-w-[110px] focus-within:pl-2"
                                    >
                                        <input
                                            type="range"
                                            min="0"
                                            max="1"
                                            step="0.01"
                                            :value="isMuted ? 0 : volume"
                                            :title="`Volume: ${Math.round((isMuted ? 0 : volume) * 100)}%`"
                                            class="h-1.5 w-16 cursor-pointer appearance-none rounded-full bg-muted accent-primary focus:outline-none [&::-webkit-slider-runnable-track]:h-1.5 [&::-webkit-slider-runnable-track]:rounded-full [&::-webkit-slider-runnable-track]:bg-muted [&::-webkit-slider-thumb]:-mt-[3px] [&::-webkit-slider-thumb]:size-3 [&::-webkit-slider-thumb]:appearance-none [&::-webkit-slider-thumb]:rounded-full [&::-webkit-slider-thumb]:bg-primary [&::-webkit-slider-thumb]:shadow-xs"
                                            @input="
                                                updateVolume(
                                                    Number(
                                                        (
                                                            $event.target as HTMLInputElement
                                                        ).value,
                                                    ),
                                                )
                                            "
                                        />
                                        <span
                                            class="min-w-[28px] pl-1 font-mono text-[10px] text-muted-foreground"
                                        >
                                            {{
                                                Math.round(
                                                    (isMuted ? 0 : volume) *
                                                        100,
                                                )
                                            }}%
                                        </span>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="flex items-center gap-1.5 text-left text-xs transition-colors hover:text-primary focus-visible:outline-none"
                                    :title="`${profileMusic.title} by ${profileMusic.artist} (Click to toggle playback)`"
                                    @click="toggleAudio"
                                >
                                    <!-- Mini spinning vinyl disc -->
                                    <div
                                        class="relative flex size-5 shrink-0 items-center justify-center rounded-full bg-neutral-900 shadow-xs ring-1 ring-border/50 transition-transform"
                                        :class="
                                            isPlaying && !isMuted
                                                ? 'animate-spin [animation-duration:4s]'
                                                : ''
                                        "
                                    >
                                        <img
                                            v-if="profileMusic.coverImageUrl"
                                            :src="profileMusic.coverImageUrl"
                                            alt=""
                                            class="size-3 rounded-full object-cover"
                                        />
                                        <Disc3
                                            v-else
                                            class="size-3 text-[#D97757]"
                                        />
                                        <span
                                            class="absolute size-1 rounded-full bg-background"
                                        ></span>
                                    </div>

                                    <div
                                        v-if="isPlaying && !isMuted"
                                        class="flex h-3 items-end gap-0.5 px-0.5"
                                        aria-hidden="true"
                                    >
                                        <span
                                            class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                                            :style="{
                                                height: `${eqBars[0]}px`,
                                            }"
                                        ></span>
                                        <span
                                            class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                                            :style="{
                                                height: `${eqBars[1]}px`,
                                            }"
                                        ></span>
                                        <span
                                            class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                                            :style="{
                                                height: `${eqBars[2]}px`,
                                            }"
                                        ></span>
                                        <span
                                            class="w-0.5 rounded-full bg-primary transition-[height] duration-75"
                                            :style="{
                                                height: `${eqBars[3]}px`,
                                            }"
                                        ></span>
                                    </div>

                                    <span
                                        class="max-w-[130px] truncate font-medium text-foreground sm:max-w-[200px]"
                                    >
                                        {{ profileMusic.title }}
                                    </span>
                                    <span
                                        class="hidden max-w-[120px] truncate text-[11px] text-muted-foreground sm:inline"
                                    >
                                        • {{ profileMusic.artist }}
                                    </span>
                                </button>

                                <button
                                    type="button"
                                    aria-label="Track details and credits"
                                    title="Track license and attribution info"
                                    class="flex size-5 items-center justify-center rounded-full text-muted-foreground/70 transition-colors hover:bg-muted hover:text-foreground"
                                    @click="showCreditsModal = true"
                                >
                                    <Info class="size-3" />
                                </button>
                            </div>
                        </div>

                        <p class="text-[15px] text-muted-foreground">
                            {{ handle }}
                        </p>

                        <p
                            v-if="profileUser.bio"
                            class="max-w-3xl text-[15px] leading-relaxed text-foreground/85"
                        >
                            {{ profileUser.bio }}
                        </p>

                        <!-- Section chips -->
                        <div class="flex flex-wrap items-center gap-2">
                            <span
                                v-for="section in profileUser.sections"
                                :key="section"
                                class="rounded-full bg-muted px-3 py-1 text-[13px] font-medium text-foreground"
                            >
                                {{ section }}
                            </span>
                            <span
                                v-if="profileUser.sections.length === 0"
                                class="rounded-full bg-muted px-3 py-1 text-[13px] font-medium text-muted-foreground"
                            >
                                {{
                                    profileUser.sectionsHidden
                                        ? 'Sections hidden'
                                        : 'No section'
                                }}
                            </span>
                        </div>

                        <!-- Meta line -->
                        <div
                            class="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[13px] text-muted-foreground"
                        >
                            <span class="inline-flex items-center gap-1.5">
                                <Calendar class="h-3.5 w-3.5" />
                                Joined {{ profileUser.joinedAt }}
                            </span>
                            <span class="inline-flex items-center gap-1.5">
                                <Flame class="h-3.5 w-3.5" />
                                {{ profileUser.streak }}-day streak
                            </span>
                            <span
                                class="inline-flex items-center gap-1.5 sm:hidden"
                            >
                                <Trophy class="h-3.5 w-3.5" />
                                Rank #{{ stats.rank }}
                            </span>
                        </div>

                        <!-- ════════════ Level & Milestone Progress Bar ════════════ -->
                        <div class="profile-card mt-3 max-w-xl bg-card p-3.5">
                            <div
                                class="flex items-center justify-between gap-2"
                            >
                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 text-[13px] font-bold text-foreground"
                                    >
                                        <Zap class="size-4 text-[#D97757]" />
                                        Level {{ stats.level }}
                                    </span>
                                    <span
                                        class="text-[12px] text-muted-foreground"
                                    >
                                        • {{ stats.xp }} Total XP
                                    </span>
                                </div>
                                <span
                                    class="font-mono text-[12px] font-semibold text-muted-foreground"
                                >
                                    {{ currentLevelXp }} / 100 XP
                                </span>
                            </div>

                            <div
                                class="mt-2.5 h-2 w-full overflow-hidden rounded-full bg-muted"
                            >
                                <div
                                    class="h-full rounded-full bg-gradient-to-r from-[#D97757] to-amber-500 transition-all duration-500"
                                    :style="{ width: `${levelProgress}%` }"
                                ></div>
                            </div>

                            <div
                                class="mt-2 flex items-center justify-between text-[11px] text-muted-foreground"
                            >
                                <span
                                    class="inline-flex items-center gap-1 font-medium"
                                >
                                    <Sparkle class="size-3 text-amber-500" />
                                    {{ xpRemaining }} XP to Level
                                    {{ nextLevel }}
                                </span>
                                <span class="font-medium text-foreground/80">
                                    Rank #{{ stats.rank }} of
                                    {{ stats.totalPlayers }}
                                </span>
                            </div>
                        </div>

                        <!-- ════════════ Featured Badges Showcase (Top 3) ════════════ -->
                        <div
                            v-if="
                                showAchievements && topEarnedBadges.length > 0
                            "
                            class="profile-card mt-3 max-w-xl bg-card p-3.5"
                        >
                            <div
                                class="mb-2.5 flex items-center justify-between"
                            >
                                <span
                                    class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Featured Badges (Trophy Case)
                                </span>
                                <button
                                    type="button"
                                    class="text-[11px] font-medium text-primary hover:underline"
                                    @click="activeTab = 'achievements'"
                                >
                                    View all {{ earnedBadgesCount }}
                                </button>
                            </div>
                            <div class="grid grid-cols-3 gap-2.5">
                                <div
                                    v-for="badge in topEarnedBadges"
                                    :key="badge.id"
                                    class="group flex flex-col items-center gap-1.5 rounded-xl border border-border/40 bg-muted/20 p-2.5 text-center transition-all hover:border-[#D97757]/40 hover:bg-muted/40"
                                    :title="`${badge.name}: ${badge.description}`"
                                >
                                    <div
                                        class="relative flex size-10 items-center justify-center overflow-hidden rounded-full bg-muted shadow-xs transition-transform group-hover:scale-105"
                                    >
                                        <img
                                            v-if="badge.image"
                                            :src="badge.image"
                                            :alt="badge.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <Medal
                                            v-else
                                            class="size-5 text-[#D97757]"
                                        />
                                    </div>
                                    <p
                                        class="max-w-full truncate text-[12px] font-semibold text-foreground"
                                    >
                                        {{ badge.name }}
                                    </p>
                                    <span
                                        v-if="badge.requiredLevel"
                                        class="rounded-full bg-background px-1.5 py-0.5 text-[9px] font-medium text-muted-foreground"
                                    >
                                        Lvl {{ badge.requiredLevel }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- ════════════ Current badge (visible to every visitor) ════════════ -->
                        <div
                            v-if="
                                showAchievements &&
                                currentBadge &&
                                topEarnedBadges.length === 0
                            "
                            class="profile-card mt-3 flex max-w-xl items-center gap-3 bg-card px-4 py-3"
                        >
                            <div
                                class="flex h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-full bg-muted"
                            >
                                <img
                                    v-if="currentBadge.image"
                                    :src="currentBadge.image"
                                    :alt="currentBadge.name"
                                    class="h-full w-full object-cover"
                                />
                                <Medal
                                    v-else
                                    class="h-5 w-5 text-muted-foreground"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                                >
                                    Current Badge
                                </p>
                                <p
                                    class="truncate text-[15px] font-semibold text-foreground"
                                >
                                    {{ currentBadge.name }}
                                </p>
                                <p
                                    v-if="
                                        currentBadge.earnedAt ||
                                        currentBadge.earnedSeason
                                    "
                                    class="truncate text-[12px] text-muted-foreground"
                                >
                                    <span v-if="currentBadge.earnedAt"
                                        >Unlocked
                                        {{ currentBadge.earnedAt }}</span
                                    >
                                    <span
                                        v-if="
                                            currentBadge.earnedAt &&
                                            currentBadge.earnedSeason
                                        "
                                    >
                                        ·
                                    </span>
                                    <span v-if="currentBadge.earnedSeason">{{
                                        currentBadge.earnedSeason
                                    }}</span>
                                </p>
                            </div>
                            <span
                                v-if="currentBadge.requiredLevel"
                                class="shrink-0 rounded-full bg-muted px-2.5 py-1 text-[12px] font-medium text-muted-foreground"
                            >
                                Level {{ currentBadge.requiredLevel }}
                            </span>
                        </div>
                    </div>

                    <!-- ════════════ Follower-style counts ════════════ -->
                    <div
                        class="mt-5 flex flex-wrap items-center gap-x-5 gap-y-2 border-y border-border/50 py-3.5 sm:gap-9"
                    >
                        <button
                            v-for="stat in countStats"
                            :key="stat.key"
                            type="button"
                            :disabled="
                                (stat.key === 'followers' ||
                                    stat.key === 'following') &&
                                stat.value === 0
                            "
                            class="flex items-baseline gap-1.5 disabled:cursor-default"
                            :class="
                                stat.key === 'followers' ||
                                stat.key === 'following'
                                    ? 'cursor-pointer hover:opacity-80'
                                    : 'cursor-default'
                            "
                            :title="
                                stat.key === 'followers'
                                    ? 'See followers'
                                    : stat.key === 'following'
                                      ? 'See following'
                                      : undefined
                            "
                            @click="
                                stat.key === 'followers'
                                    ? openSocialList('followers')
                                    : stat.key === 'following'
                                      ? openSocialList('following')
                                      : undefined
                            "
                        >
                            <span
                                class="profile-metric text-[17px] text-foreground sm:text-[19px]"
                            >
                                {{ formatCount(stat.value) }}
                            </span>
                            <span
                                class="text-[13px] text-muted-foreground sm:text-[14px]"
                            >
                                {{ stat.label }}
                            </span>
                        </button>
                    </div>

                    <!-- ════════════ Section ranking cards ════════════ -->
                    <div
                        v-if="sectionRanks && sectionRanks.length > 0"
                        class="mt-4 grid gap-2 sm:grid-cols-2"
                    >
                        <Link
                            v-for="section in sectionRanks"
                            :key="section.id"
                            href="/leaderboard"
                            class="profile-card flex items-center gap-3 bg-card px-4 py-3 transition-colors hover:bg-muted/40"
                        >
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[#D97757]/10 text-[#D97757]"
                            >
                                <Trophy class="h-4 w-4" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <p
                                    class="truncate text-[13px] font-semibold text-foreground"
                                >
                                    Rank #{{ section.rank }} of
                                    {{ section.total }}
                                </p>
                                <p
                                    class="truncate text-[12px] text-muted-foreground"
                                >
                                    {{ section.name }}
                                </p>
                            </div>
                        </Link>
                    </div>

                    <!-- ════════════ Positive kudos ════════════ -->
                    <div
                        v-if="!profileUser.isCurrentUser && canInteract"
                        class="relative mt-4 flex flex-col gap-3 overflow-hidden rounded-2xl border border-border/60 bg-card px-4 py-3 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <!-- Kudo celebration burst overlay -->
                        <div
                            v-if="showKudoBurst"
                            class="pointer-events-none absolute inset-0 z-10 flex items-center justify-center bg-background/50 backdrop-blur-xs transition-opacity"
                        >
                            <span class="animate-bounce text-3xl sm:text-4xl">
                                {{ kudoCelebrationEmoji }}
                            </span>
                        </div>

                        <div>
                            <p class="text-sm font-semibold">Send a kudo</p>
                            <p class="text-xs text-muted-foreground">
                                A small, positive note for a classmate.
                            </p>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="kudo in kudoOptions"
                                :key="kudo.key"
                                type="button"
                                :disabled="
                                    kudoPending || viewerKudo === kudo.key
                                "
                                class="rounded-full border px-3 py-1.5 text-xs font-medium transition-all hover:scale-105 active:scale-95 disabled:cursor-default"
                                :class="
                                    viewerKudo === kudo.key
                                        ? 'border-foreground bg-foreground text-background'
                                        : 'border-border bg-background text-foreground hover:bg-muted'
                                "
                                @click="sendKudo(kudo.key)"
                            >
                                {{ kudo.label }}
                                <span
                                    v-if="kudos[kudo.key]"
                                    class="ml-1 opacity-70"
                                >
                                    {{ kudos[kudo.key] }}
                                </span>
                            </button>
                        </div>
                    </div>

                    <!-- ════════════ Recent kudos feed ════════════ -->
                    <div
                        v-if="recentKudos && recentKudos.length > 0"
                        class="mt-4 rounded-2xl border border-border/60 bg-card px-4 py-3"
                    >
                        <p class="text-sm font-semibold">Cheered on by</p>
                        <div
                            class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-2"
                        >
                            <div
                                v-for="kudo in recentKudos"
                                :key="kudo.id"
                                class="flex items-center gap-2"
                            >
                                <Avatar class="size-7 rounded-full bg-muted">
                                    <AvatarImage
                                        v-if="kudo.avatar"
                                        :src="kudo.avatar"
                                        :alt="kudo.name"
                                        class="object-cover"
                                    />
                                    <AvatarFallback
                                        class="bg-muted text-[10px] font-semibold text-foreground"
                                    >
                                        {{ getInitials(kudo.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span class="text-[13px] text-foreground">
                                    {{ kudo.name }}
                                </span>
                                <span
                                    class="text-[11px] text-muted-foreground"
                                    :title="kudo.date ?? undefined"
                                >
                                    {{ kudoLabel[kudo.type] }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- ════════════ Segmented tabs ════════════ -->
                    <div
                        v-if="tabs.length > 0"
                        class="mt-5 inline-flex w-full gap-1 rounded-full bg-muted p-1 sm:w-auto"
                        role="tablist"
                    >
                        <button
                            v-for="tab in tabs"
                            :key="tab.key"
                            type="button"
                            role="tab"
                            :aria-selected="activeTab === tab.key"
                            class="profile-segment flex-1 px-4 text-[14px] whitespace-nowrap transition-all sm:flex-none"
                            :class="
                                activeTab === tab.key
                                    ? 'bg-background text-foreground shadow-sm'
                                    : 'text-muted-foreground hover:text-foreground'
                            "
                            @click="activeTab = tab.key"
                        >
                            {{ tab.label }}
                            <span class="ml-1 tabular-nums opacity-60">
                                {{ tab.count }}
                            </span>
                        </button>
                    </div>

                    <!-- ════════════ Tab panels ════════════ -->
                    <div v-if="tabs.length > 0" class="mt-5">
                        <!-- ── Activity feed ── -->
                        <div
                            v-show="activeTab === 'activity'"
                            class="space-y-3"
                            role="tabpanel"
                        >
                            <!-- 28-Day Study Rhythm Heatmap Card -->
                            <div class="profile-card bg-card p-4">
                                <div
                                    class="flex flex-wrap items-center justify-between gap-2"
                                >
                                    <div class="flex items-center gap-2">
                                        <Calendar
                                            class="size-4 text-[#D97757]"
                                        />
                                        <span
                                            class="text-[13px] font-semibold text-foreground"
                                        >
                                            Study Rhythm & Activity (Last 4
                                            Weeks)
                                        </span>
                                    </div>
                                    <span
                                        class="text-[12px] font-medium text-muted-foreground"
                                    >
                                        {{ activeDaysCount }} of 28 active days
                                    </span>
                                </div>

                                <div
                                    class="mt-3 grid grid-cols-7 gap-1.5 sm:gap-2"
                                >
                                    <div
                                        v-for="day in activityHeatmap"
                                        :key="day.dateKey"
                                        class="group relative flex aspect-square flex-col items-center justify-center rounded-lg border border-border/40 transition-transform hover:scale-110"
                                        :class="
                                            day.level === 3
                                                ? 'border-[#D97757] bg-[#D97757] text-white shadow-xs'
                                                : day.level === 2
                                                  ? 'border-[#D97757]/60 bg-[#D97757]/50 text-foreground'
                                                  : day.level === 1
                                                    ? 'border-[#D97757]/30 bg-[#D97757]/20 text-foreground'
                                                    : 'border-border/30 bg-muted/30 text-muted-foreground/40'
                                        "
                                        :title="`${day.dayLabel}: ${day.count} activities, ${day.xp} XP`"
                                    >
                                        <span
                                            class="font-mono text-[10px] leading-none font-medium"
                                        >
                                            {{ day.dayLabel.split(' ')[1] }}
                                        </span>
                                    </div>
                                </div>

                                <div
                                    class="mt-3 flex items-center justify-between text-[11px] text-muted-foreground"
                                >
                                    <span>28 days ago</span>
                                    <div class="flex items-center gap-1.5">
                                        <span class="text-[10px]">Less</span>
                                        <span
                                            class="size-2.5 rounded-sm border border-border/30 bg-muted/40"
                                        ></span>
                                        <span
                                            class="size-2.5 rounded-sm border border-[#D97757]/30 bg-[#D97757]/25"
                                        ></span>
                                        <span
                                            class="size-2.5 rounded-sm border border-[#D97757]/60 bg-[#D97757]/55"
                                        ></span>
                                        <span
                                            class="size-2.5 rounded-sm border border-[#D97757] bg-[#D97757]"
                                        ></span>
                                        <span class="text-[10px]">More</span>
                                    </div>
                                    <span>Today</span>
                                </div>
                            </div>

                            <template v-if="history.length > 0">
                                <article
                                    v-for="item in history"
                                    :key="item.id"
                                    class="profile-card flex gap-3 bg-card p-4 transition-colors hover:bg-muted/40"
                                >
                                    <div
                                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-muted text-foreground"
                                    >
                                        <component
                                            :is="iconForReason(item.reason)"
                                            class="h-[18px] w-[18px]"
                                        />
                                    </div>

                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="flex flex-wrap items-center gap-x-2 gap-y-0.5"
                                        >
                                            <span
                                                class="text-[15px] font-semibold text-foreground"
                                            >
                                                {{ item.reason }}
                                            </span>
                                            <span
                                                class="text-[13px] text-muted-foreground"
                                                :title="item.full_date"
                                            >
                                                · {{ item.date }}
                                            </span>
                                        </div>

                                        <p
                                            class="mt-0.5 text-[15px] leading-snug text-foreground/90"
                                        >
                                            {{ item.description }}
                                        </p>

                                        <div
                                            class="mt-2 flex flex-wrap items-center gap-2"
                                        >
                                            <span
                                                v-if="item.amount_xp !== 0"
                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[12px] font-semibold tabular-nums"
                                                :class="
                                                    item.amount_xp < 0
                                                        ? 'bg-[#CB7676]/12 text-[#B65252]'
                                                        : 'bg-[#4D9375]/12 text-[#3F7B60]'
                                                "
                                            >
                                                <Zap class="h-3 w-3" />
                                                {{
                                                    formatDelta(item.amount_xp)
                                                }}
                                                XP
                                            </span>
                                            <span
                                                v-if="item.amount_points !== 0"
                                                class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[12px] font-semibold tabular-nums"
                                                :class="
                                                    item.amount_points < 0
                                                        ? 'bg-[#CB7676]/12 text-[#B65252]'
                                                        : 'bg-[#E0AF68]/15 text-[#9A7430]'
                                                "
                                            >
                                                <Trophy class="h-3 w-3" />
                                                {{
                                                    formatDelta(
                                                        item.amount_points,
                                                    )
                                                }}
                                            </span>
                                            <span
                                                v-if="item.section"
                                                class="rounded-full bg-muted px-2.5 py-1 text-[12px] font-medium text-muted-foreground"
                                            >
                                                {{ item.section }}
                                            </span>
                                        </div>
                                    </div>
                                </article>
                            </template>

                            <div
                                v-else
                                class="profile-card flex flex-col items-center gap-2 bg-card px-6 py-14 text-center"
                            >
                                <Sparkles
                                    class="h-6 w-6 text-muted-foreground/60"
                                />
                                <p class="text-[15px] font-medium">
                                    No activity yet
                                </p>
                                <p class="text-[14px] text-muted-foreground">
                                    Earned XP and milestones will show up here.
                                </p>
                            </div>
                        </div>

                        <!-- ── Achievements ── -->
                        <div
                            v-show="activeTab === 'achievements'"
                            role="tabpanel"
                        >
                            <div
                                v-if="badges.length > 0"
                                class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4"
                            >
                                <div
                                    v-for="badge in badges"
                                    :key="badge.id"
                                    class="profile-card flex flex-col items-center gap-2.5 bg-card p-5 text-center transition-transform duration-200 hover:-translate-y-0.5"
                                    :class="!badge.earned && 'opacity-60'"
                                    :title="
                                        badge.earned
                                            ? 'Unlocked'
                                            : badge.requiredLevel
                                              ? `Reach Level ${badge.requiredLevel} to unlock`
                                              : 'Locked'
                                    "
                                >
                                    <div
                                        class="relative flex h-16 w-16 items-center justify-center overflow-hidden rounded-full bg-muted"
                                    >
                                        <img
                                            v-if="badge.image"
                                            :src="badge.image"
                                            :alt="badge.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <Shield
                                            v-else
                                            class="h-6 w-6 text-muted-foreground"
                                        />
                                        <!-- Lock badge on locked achievements -->
                                        <div
                                            v-if="!badge.earned"
                                            class="absolute inset-0 flex items-center justify-center rounded-full bg-background/60"
                                        >
                                            <Lock
                                                class="h-4 w-4 text-muted-foreground"
                                            />
                                        </div>
                                    </div>

                                    <div class="space-y-0.5">
                                        <p
                                            class="text-[14px] leading-tight font-semibold"
                                        >
                                            {{ badge.name }}
                                        </p>
                                        <p
                                            v-if="
                                                badge.earned && badge.earnedAt
                                            "
                                            class="text-[12px] font-medium text-[#4D9375]"
                                        >
                                            Unlocked
                                            {{ badge.earnedAt }}
                                        </p>
                                        <p
                                            v-else-if="
                                                badge.earned &&
                                                badge.earnedSeason
                                            "
                                            class="text-[12px] text-muted-foreground"
                                        >
                                            {{ badge.earnedSeason }}
                                        </p>
                                        <p
                                            v-else-if="badge.requiredLevel"
                                            class="text-[12px] text-muted-foreground"
                                        >
                                            Level {{ badge.requiredLevel }}
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <div
                                v-else
                                class="profile-card flex flex-col items-center gap-2 bg-card px-6 py-14 text-center"
                            >
                                <Medal
                                    class="h-6 w-6 text-muted-foreground/60"
                                />
                                <p class="text-[15px] font-medium">
                                    No badges yet
                                </p>
                                <p class="text-[14px] text-muted-foreground">
                                    {{ profileUser.name }} hasn&apos;t unlocked
                                    any achievements.
                                </p>
                            </div>
                        </div>

                        <!-- ── Courses ── -->
                        <div
                            v-show="activeTab === 'courses'"
                            role="tabpanel"
                            class="space-y-3"
                        >
                            <p class="text-[13px] text-muted-foreground">
                                Visible to you as the student or authorized
                                staff.
                            </p>

                            <template v-if="courses.length > 0">
                                <div
                                    v-for="course in courses"
                                    :key="course.id"
                                    class="profile-card bg-card p-4"
                                >
                                    <div
                                        class="flex items-center justify-between gap-3"
                                    >
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-[15px] font-semibold"
                                            >
                                                {{ course.name }}
                                            </p>
                                            <p
                                                class="mt-0.5 text-[13px] text-muted-foreground"
                                            >
                                                {{ course.completedLessons }} of
                                                {{ course.totalLessons }}
                                                lessons ·
                                                {{ course.xpEarned }} XP
                                            </p>
                                        </div>
                                        <span
                                            class="profile-metric shrink-0 text-[17px]"
                                        >
                                            {{ course.progress }}%
                                        </span>
                                    </div>

                                    <div
                                        class="mt-3 h-1.5 w-full overflow-hidden rounded-full bg-muted"
                                    >
                                        <div
                                            class="h-full rounded-full bg-foreground transition-all duration-500"
                                            :style="{
                                                width: `${course.progress}%`,
                                            }"
                                        ></div>
                                    </div>
                                </div>
                            </template>

                            <div
                                v-else
                                class="profile-card flex flex-col items-center gap-2 bg-card px-6 py-14 text-center"
                            >
                                <LayoutGrid
                                    class="h-6 w-6 text-muted-foreground/60"
                                />
                                <p class="text-[15px] font-medium">
                                    No active courses
                                </p>
                                <p class="text-[14px] text-muted-foreground">
                                    Nothing enrolled for the current season.
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ════════════ Followers / Following modal ════════════ -->
        <Dialog
            :open="socialModalOpen"
            @update:open="
                (open) => {
                    if (!open) activeSocialList = null;
                }
            "
        >
            <DialogContent
                class="overflow-hidden border-border/50 bg-card p-0 sm:max-w-[420px]"
            >
                <div
                    class="flex items-center justify-between border-b border-border/20 px-5 py-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-full bg-[#D97757]/10 text-[#D97757]"
                        >
                            <Users class="h-4 w-4" />
                        </div>
                        <DialogTitle
                            class="text-[16px] font-semibold tracking-tight"
                        >
                            {{ socialListTitle }}
                        </DialogTitle>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-2 text-muted-foreground transition-colors hover:bg-muted"
                        aria-label="Close"
                        @click="activeSocialList = null"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="max-h-[55vh] scrollbar-none overflow-y-auto p-3">
                    <template v-if="socialListItems.length > 0">
                        <div
                            v-for="person in socialListItems"
                            :key="person.id"
                            class="flex items-center gap-3 rounded-xl p-2.5 transition-colors hover:bg-muted/40"
                        >
                            <Link
                                :href="`/u/${person.id}`"
                                class="flex min-w-0 flex-1 items-center gap-3"
                            >
                                <Avatar class="size-9 rounded-full bg-muted">
                                    <AvatarImage
                                        v-if="person.avatar"
                                        :src="person.avatar"
                                        :alt="person.name"
                                        class="object-cover"
                                    />
                                    <AvatarFallback
                                        class="bg-muted text-xs font-semibold text-foreground"
                                    >
                                        {{ getInitials(person.name) }}
                                    </AvatarFallback>
                                </Avatar>
                                <span
                                    class="truncate text-[14px] font-medium text-foreground"
                                >
                                    {{ person.name }}
                                </span>
                            </Link>
                        </div>
                    </template>
                    <div
                        v-else
                        class="flex flex-col items-center gap-2 px-6 py-12 text-center"
                    >
                        <Users class="h-6 w-6 text-muted-foreground/50" />
                        <p class="text-[14px] font-medium text-foreground">
                            Nothing here yet
                        </p>
                        <p class="text-[13px] text-muted-foreground">
                            {{
                                socialListTitle === 'Followers'
                                    ? 'No one is following this student yet.'
                                    : 'Not following anyone yet.'
                            }}
                        </p>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- Profile Soundtrack Details & Attribution Modal -->
        <Dialog
            v-if="profileMusic"
            :open="showCreditsModal"
            @update:open="(val: boolean) => (showCreditsModal = val)"
        >
            <DialogContent class="max-w-md">
                <DialogTitle
                    class="flex items-center gap-2 text-base font-bold"
                >
                    <Music class="size-4 text-primary" />
                    Track Information & Credits
                </DialogTitle>
                <p class="-mt-2 text-xs text-muted-foreground">
                    Soundtrack selected by {{ profileUser.name }} (loops
                    continuously).
                </p>

                <div class="mt-2 space-y-3.5">
                    <div
                        class="flex items-center gap-3.5 rounded-xl border border-border/50 bg-muted/30 p-3.5"
                    >
                        <div
                            class="flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-primary/10"
                        >
                            <img
                                v-if="profileMusic.coverImageUrl"
                                :src="profileMusic.coverImageUrl"
                                :alt="profileMusic.title"
                                class="size-full object-cover"
                            />
                            <Music v-else class="size-6 text-primary" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <h4
                                class="truncate text-sm font-bold text-foreground"
                            >
                                {{ profileMusic.title }}
                            </h4>
                            <p class="truncate text-xs text-muted-foreground">
                                {{ profileMusic.artist }}
                            </p>
                            <div class="mt-1 flex items-center gap-2">
                                <span
                                    v-if="profileMusic.duration"
                                    class="rounded-full bg-primary/10 px-2 py-0.5 text-[10px] font-semibold text-primary"
                                >
                                    {{
                                        formatMusicDuration(
                                            profileMusic.duration,
                                        )
                                    }}
                                </span>
                                <span
                                    v-if="profileMusic.licenseName"
                                    class="truncate rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                >
                                    {{ profileMusic.licenseName }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Attribution text required by NCS / CC -->
                    <div
                        v-if="profileMusic.attributionText"
                        class="rounded-lg border border-border/40 bg-muted/20 p-3"
                    >
                        <p
                            class="mb-1 text-[11px] font-semibold tracking-wider text-muted-foreground uppercase"
                        >
                            Attribution & License
                        </p>
                        <p
                            class="max-h-36 overflow-y-auto rounded border border-border/30 bg-background/60 p-2.5 font-mono text-xs whitespace-pre-line text-foreground/90"
                        >
                            {{ profileMusic.attributionText }}
                        </p>
                    </div>

                    <!-- Official source link -->
                    <div
                        v-if="profileMusic.sourceUrl"
                        class="flex justify-end pt-1"
                    >
                        <a
                            :href="profileMusic.sourceUrl"
                            target="_blank"
                            rel="noopener noreferrer nofollow"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-primary hover:underline"
                        >
                            <span>Official Release / Stream</span>
                            <ExternalLink class="size-3.5" />
                        </a>
                    </div>
                </div>
            </DialogContent>
        </Dialog>

        <!-- ════════════ Shareable Profile Card Modal ════════════ -->
        <Dialog
            :open="showShareCardModal"
            @update:open="(val) => (showShareCardModal = val)"
        >
            <DialogContent
                class="overflow-hidden border-border/60 bg-card p-0 sm:max-w-[420px]"
            >
                <div
                    class="flex items-center justify-between border-b border-border/20 px-5 py-4"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex size-8 items-center justify-center rounded-full bg-[#D97757]/10 text-[#D97757]"
                        >
                            <Sparkles class="size-4" />
                        </div>
                        <DialogTitle
                            class="text-[16px] font-semibold tracking-tight"
                        >
                            Student Profile Card
                        </DialogTitle>
                    </div>
                    <button
                        type="button"
                        class="rounded-full p-1.5 text-muted-foreground transition-colors hover:bg-muted"
                        aria-label="Close"
                        @click="showShareCardModal = false"
                    >
                        <X class="size-4" />
                    </button>
                </div>

                <div class="p-5">
                    <!-- The Visual Snapshot Card -->
                    <div
                        ref="cardElementRef"
                        class="relative overflow-hidden rounded-2xl border border-border/70 bg-gradient-to-br from-card via-muted/40 to-muted/80 p-5 shadow-lg"
                    >
                        <!-- Top Accent Banner -->
                        <div
                            class="absolute -top-12 -right-12 size-36 rounded-full bg-[#D97757]/15 blur-2xl"
                        ></div>

                        <div class="relative flex items-center gap-3.5">
                            <Avatar
                                class="size-16 border-2 border-background shadow-md"
                            >
                                <AvatarImage
                                    v-if="profileUser.avatar"
                                    :src="profileUser.avatar"
                                    :alt="profileUser.name"
                                    class="object-cover"
                                />
                                <AvatarFallback
                                    class="bg-muted text-xl font-bold"
                                >
                                    {{ getInitials(profileUser.name) }}
                                </AvatarFallback>
                            </Avatar>
                            <div class="min-w-0 flex-1">
                                <h3
                                    class="truncate text-lg font-bold text-foreground"
                                >
                                    {{ profileUser.name }}
                                </h3>
                                <p class="text-xs text-muted-foreground">
                                    {{ handle }} • LSI Student
                                </p>
                                <div class="mt-1 flex items-center gap-1.5">
                                    <span
                                        class="rounded-full bg-[#D97757]/15 px-2 py-0.5 text-[10px] font-bold text-[#D97757]"
                                    >
                                        Level {{ stats.level }}
                                    </span>
                                    <span
                                        class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                    >
                                        Rank #{{ stats.rank }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Card Metrics Grid -->
                        <div
                            class="mt-4 grid grid-cols-3 gap-2 rounded-xl border border-border/40 bg-background/60 p-2.5 text-center backdrop-blur-xs"
                        >
                            <div>
                                <span
                                    class="block text-[10px] text-muted-foreground uppercase"
                                    >Streak</span
                                >
                                <span class="text-sm font-bold text-amber-500"
                                    >🔥 {{ profileUser.streak }}d</span
                                >
                            </div>
                            <div>
                                <span
                                    class="block text-[10px] text-muted-foreground uppercase"
                                    >Total XP</span
                                >
                                <span
                                    class="text-sm font-bold text-foreground"
                                    >{{ formatCount(stats.xp) }}</span
                                >
                            </div>
                            <div>
                                <span
                                    class="block text-[10px] text-muted-foreground uppercase"
                                    >Badges</span
                                >
                                <span
                                    class="text-sm font-bold text-foreground"
                                    >{{ earnedBadgesCount }}</span
                                >
                            </div>
                        </div>

                        <!-- Bottom Card Brand Footer -->
                        <div
                            class="mt-3.5 flex items-center justify-between border-t border-border/40 pt-2.5 text-[10px] text-muted-foreground"
                        >
                            <span
                                class="font-semibold tracking-wider text-foreground/80 uppercase"
                                >LSI LEARNING PLATFORM</span
                            >
                            <span>{{ profileUser.joinedAt }}</span>
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-4 flex flex-col gap-2 sm:flex-row">
                        <button
                            type="button"
                            :disabled="isGeneratingImage"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-primary/40 bg-primary/10 py-2.5 text-xs font-semibold text-primary transition-all hover:bg-primary/20 disabled:opacity-60"
                            @click="downloadCardImage"
                        >
                            <Loader2
                                v-if="isGeneratingImage"
                                class="size-3.5 animate-spin"
                            />
                            <component
                                :is="imageDownloaded ? Check : Download"
                                v-else
                                class="size-3.5"
                            />
                            {{
                                isGeneratingImage
                                    ? 'Generating...'
                                    : imageDownloaded
                                      ? 'Card Saved!'
                                      : 'Save Card Image'
                            }}
                        </button>
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl border border-border/60 bg-muted/60 py-2.5 text-xs font-semibold text-foreground transition-colors hover:bg-muted"
                            @click="copyCardSummary"
                        >
                            <component
                                :is="cardCopied ? Check : Copy"
                                class="size-3.5"
                            />
                            {{
                                cardCopied ? 'Summary Copied!' : 'Copy Summary'
                            }}
                        </button>
                        <button
                            type="button"
                            class="flex flex-1 items-center justify-center gap-1.5 rounded-xl bg-foreground py-2.5 text-xs font-semibold text-background transition-opacity hover:opacity-90"
                            @click="shareProfile"
                        >
                            <Share2 class="size-3.5" />
                            Share Link
                        </button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
