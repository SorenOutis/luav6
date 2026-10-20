<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import {
    ArrowLeft,
    BarChart2,
    Bookmark,
    Code2,
    Download,
    EyeOff,
    FileText,
    Heart,
    ImageIcon,
    MessageCircle,
    MoreHorizontal,
    Plus,
    RefreshCw,
    Search,
    Send,
    Shield,
    ShieldCheck,
    Smile,
    Users,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import CurtainWipeOverlay from '@/components/common/CurtainWipeOverlay.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

export interface ClassmatePresence {
    id: number | string;
    name: string;
    avatar?: string;
    status: 'online' | 'idle' | 'offline';
    role?: string;
    activity?: string;
    isCurrentUser?: boolean;
}

interface Workspace {
    id: number;
    name: string;
    public_id?: string;
}

interface Props {
    workspace?: Workspace | null;
    sectionName?: string;
    onlineClassmates?: ClassmatePresence[];
}

const props = defineProps<Props>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Community Lounge', href: '/community' },
];

const curtainRef = ref<InstanceType<typeof CurtainWipeOverlay> | null>(null);

const handleReturn = () => {
    if (curtainRef.value) {
        curtainRef.value.wipeTo('/dashboard', 'to_dashboard');
    } else {
        router.visit('/dashboard', { preserveScroll: false });
    }
};

const activeTab = ref<'all' | 'section' | 'saved'>('all');

// ─── Section Online Presence & Mockup State ──────────────────────────────────
const defaultClassmates: ClassmatePresence[] = [
    {
        id: 1,
        name: 'You (Alex)',
        status: 'online',
        role: 'You',
        activity: 'Active in Lounge',
        isCurrentUser: true,
    },
    {
        id: 2,
        name: 'Sarah Chen',
        status: 'online',
        role: 'Class Officer',
        activity: 'Reviewing Midterms',
    },
    {
        id: 3,
        name: 'Marcus Vance',
        status: 'online',
        activity: 'Networking Assignment #3',
    },
    {
        id: 4,
        name: 'Chloe Santos',
        status: 'idle',
        role: 'Section Rep',
        activity: 'In Library Hub (idle 12m)',
    },
    {
        id: 5,
        name: 'Devon Ray',
        status: 'idle',
        activity: 'Studying Dijkstra algorithm',
    },
    {
        id: 6,
        name: 'Elena Rostova',
        status: 'offline',
        activity: 'Last seen 2h ago',
    },
];

const sectionDisplayName = computed(
    () => props.sectionName?.trim() || 'BSIT 3-A',
);

const classmatesList = computed<ClassmatePresence[]>(
    () => props.onlineClassmates ?? defaultClassmates,
);

const onlineCount = computed(
    () => classmatesList.value.filter((c) => c.status === 'online').length,
);

const totalEnrolledCount = computed(() =>
    Math.max(38, classmatesList.value.length),
);

const classmateSearch = ref('');

const filteredClassmates = computed(() => {
    const q = classmateSearch.value.trim().toLowerCase();
    if (!q) {
        return classmatesList.value;
    }
    return classmatesList.value.filter(
        (c) =>
            c.name.toLowerCase().includes(q) ||
            (c.role && c.role.toLowerCase().includes(q)) ||
            (c.activity && c.activity.toLowerCase().includes(q)),
    );
});

const getInitials = (name: string): string => {
    const trimmed = name.trim();
    if (trimmed.startsWith('You (')) {
        const inner = trimmed.match(/\((.*?)\)/)?.[1] || '';
        if (inner) {
            return inner.slice(0, 2).toUpperCase();
        }
    }
    const parts = trimmed.split(/\s+/).filter(Boolean);
    if (parts.length === 0) {
        return '?';
    }
    if (parts.length === 1) {
        return parts[0].slice(0, 2).toUpperCase();
    }
    return (parts[0][0] + parts[parts.length - 1][0]).toUpperCase();
};

const isRefreshingPresence = ref(false);

const handleRefreshPresence = () => {
    if (isRefreshingPresence.value) {
        return;
    }
    isRefreshingPresence.value = true;
    if (typeof router.reload === 'function') {
        router.reload({
            only: ['onlineClassmates'],
            onFinish: () => {
                isRefreshingPresence.value = false;
            },
        });
    }
    setTimeout(() => {
        isRefreshingPresence.value = false;
    }, 600);
};

// ─── Interactive Social Likes & Bookmarks ────────────────────────────────────
const likedPosts = ref<Record<string, boolean>>({
    'post-1': false,
    'post-2': false,
});
const postLikesCount = ref<Record<string, number>>({
    'post-1': 12,
    'post-2': 8,
});
const toggleLike = (postId: string) => {
    likedPosts.value[postId] = !likedPosts.value[postId];
    postLikesCount.value[postId] += likedPosts.value[postId] ? 1 : -1;
};

const bookmarkedPosts = ref<Record<string, boolean>>({});
const toggleBookmark = (postId: string) => {
    bookmarkedPosts.value[postId] = !bookmarkedPosts.value[postId];
};

// ─── Stories / Classmate Presence Bar (Instagram Style) ──────────────────────
const stories = [
    {
        id: 'you',
        name: 'Your story',
        username: 'alex.rivera',
        initials: 'AL',
        isUser: true,
        hasStory: false,
        status: 'online',
    },
    {
        id: 'sarah',
        name: 'Sarah',
        username: 'sarah.chen',
        initials: 'SC',
        hasStory: true,
        unread: true,
        status: 'online',
    },
    {
        id: 'marcus',
        name: 'Marcus',
        username: 'marcus.vance',
        initials: 'MV',
        hasStory: true,
        unread: true,
        status: 'online',
    },
    {
        id: 'chloe',
        name: 'Chloe',
        username: 'chloe.santos',
        initials: 'CS',
        hasStory: true,
        unread: false,
        status: 'idle',
    },
    {
        id: 'devon',
        name: 'Devon',
        username: 'devon.ray',
        initials: 'DR',
        hasStory: false,
        unread: false,
        status: 'idle',
    },
    {
        id: 'elena',
        name: 'Elena',
        username: 'elena.rostova',
        initials: 'ER',
        hasStory: false,
        unread: false,
        status: 'offline',
    },
];

// ─── Mobile Drawer State ─────────────────────────────────────────────────────
const isMobileDrawerOpen = ref(false);

watch(isMobileDrawerOpen, (isOpen) => {
    if (typeof document !== 'undefined') {
        if (isOpen) {
            document.body.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = '';
        }
    }
});

onBeforeUnmount(() => {
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});

// ─── Trending Topics ─────────────────────────────────────────────────────────
const trendingTopics = [
    { tag: 'midterm-exam', name: 'MidtermExam', count: 28 },
    { tag: 'networking-lab', name: 'NetworkingLab', count: 14 },
    { tag: 'review-notes', name: 'ReviewNotes', count: 9 },
    { tag: 'dijkstra', name: 'DijkstraAlgorithm', count: 6 },
    { tag: 'study-group', name: 'StudyGroup', count: 4 },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Community Lounge" />

        <div class="relative min-h-[calc(100vh-4rem)] p-3 sm:p-5 lg:p-6">
            <div class="mx-auto max-w-6xl">
                <!-- ═══ Instagram / Threads Clean Navigation Bar ═══ -->
                <header
                    class="mb-5 flex flex-wrap items-center justify-between gap-3 border-b border-border/40 pb-4"
                >
                    <div class="flex items-center gap-3">
                        <button
                            type="button"
                            data-test="community-back-button"
                            class="group inline-flex cursor-pointer items-center gap-2 rounded-full border border-border/70 bg-card px-3.5 py-1.5 text-xs font-semibold text-foreground transition-all duration-150 hover:border-foreground/30 hover:bg-muted/60 focus-visible:ring-2 focus-visible:ring-brand focus-visible:outline-none active:scale-95"
                            @click="handleReturn"
                        >
                            <ArrowLeft
                                class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-0.5"
                            />
                            <span>Back to Dashboard</span>
                        </button>

                        <div class="h-4 w-px bg-border/60" />

                        <div class="flex items-center gap-2">
                            <h1
                                class="text-base font-bold tracking-tight text-foreground sm:text-lg"
                            >
                                Community Lounge
                            </h1>
                            <span
                                v-if="props.workspace?.name"
                                class="hidden rounded-md bg-muted px-2 py-0.5 text-[11px] font-medium text-muted-foreground sm:inline-flex"
                            >
                                {{ props.workspace.name }}
                            </span>
                        </div>
                    </div>

                    <!-- Right Controls: Privacy Indicator & Mobile Presence Button -->
                    <div class="flex items-center gap-2">
                        <!-- Instagram-style minimal privacy badge -->
                        <div
                            class="hidden items-center gap-2 rounded-full border border-border/60 bg-card/60 px-3 py-1 text-[11px] text-muted-foreground md:inline-flex"
                        >
                            <span class="flex items-center gap-1.5 font-medium">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                />
                                <span>Your Section</span>: Real Name
                            </span>
                            <span class="text-border">•</span>
                            <span class="flex items-center gap-1.5 font-medium">
                                <span
                                    class="h-1.5 w-1.5 rounded-full bg-muted-foreground/60"
                                />
                                <span>Other Sections</span>: Anonymous
                            </span>
                        </div>

                        <!-- Mobile Presence Trigger Button -->
                        <button
                            type="button"
                            data-test="mobile-presence-drawer-trigger"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-3 py-1 text-xs font-semibold text-emerald-600 transition-all hover:bg-emerald-500/20 active:scale-95 lg:hidden dark:text-emerald-400"
                            aria-label="View online classmates"
                            @click="isMobileDrawerOpen = true"
                        >
                            <span
                                class="relative flex h-2 w-2 shrink-0 items-center justify-center"
                                aria-hidden="true"
                            >
                                <span
                                    class="presence-beacon-ring absolute inline-flex h-full w-full rounded-full bg-emerald-500/40"
                                />
                                <span
                                    class="presence-beacon-dot relative inline-flex h-1.5 w-1.5 rounded-full bg-emerald-500"
                                />
                            </span>
                            <span>{{ onlineCount }} Online</span>
                            <Users class="h-3 w-3" />
                        </button>
                    </div>
                </header>

                <!-- ═══ 2-Column Social Feed Layout ═══ -->
                <div class="grid grid-cols-1 items-start gap-8 lg:grid-cols-12">
                    <!-- Main Feed Column (col-span-7 or 8 on desktop) -->
                    <main class="space-y-5 lg:col-span-7 xl:col-span-8">
                        <!-- ─── Instagram Top Stories Tray ──────────────────────────── -->
                        <section
                            aria-label="Classmate Stories"
                            class="flex scrollbar-none items-center gap-4 overflow-x-auto rounded-2xl border border-border/50 bg-card/60 p-3.5 pb-3 shadow-2xs backdrop-blur-xs"
                        >
                            <!-- Story Item -->
                            <div
                                v-for="story in stories"
                                :key="story.id"
                                class="group flex shrink-0 cursor-pointer flex-col items-center gap-1.5 select-none"
                            >
                                <div class="relative">
                                    <!-- Story Ring: Instagram gradient for unread stories -->
                                    <div
                                        class="flex h-14 w-14 items-center justify-center rounded-full transition-transform duration-200 group-hover:scale-105 active:scale-95"
                                        :class="[
                                            story.isUser
                                                ? 'border-2 border-dashed border-border p-0.5'
                                                : story.unread
                                                  ? 'bg-gradient-to-tr from-amber-500 via-rose-500 to-purple-600 p-[2.5px]'
                                                  : 'border-2 border-border/70 p-0.5',
                                        ]"
                                    >
                                        <!-- Inner Avatar Circle -->
                                        <div
                                            class="flex h-full w-full items-center justify-center rounded-full border-2 border-background bg-muted text-xs font-bold text-foreground"
                                            :class="
                                                story.isUser
                                                    ? 'bg-brand/15 text-brand'
                                                    : 'bg-card text-foreground'
                                            "
                                        >
                                            {{ story.initials }}
                                        </div>
                                    </div>

                                    <!-- Plus badge on user story -->
                                    <span
                                        v-if="story.isUser"
                                        class="absolute -right-0.5 -bottom-0.5 flex h-4 w-4 items-center justify-center rounded-full bg-brand text-[10px] font-bold text-primary-foreground shadow-xs"
                                    >
                                        <Plus class="h-2.5 w-2.5 stroke-[3]" />
                                    </span>

                                    <!-- Online Green Beacon on classmates -->
                                    <span
                                        v-else-if="story.status === 'online'"
                                        class="absolute right-0.5 bottom-0.5 flex h-3 w-3 items-center justify-center rounded-full border-2 border-background bg-emerald-500 shadow-2xs"
                                        title="Active now"
                                    />
                                </div>

                                <span
                                    class="max-w-[64px] truncate text-[11px] font-medium tracking-tight text-muted-foreground group-hover:text-foreground"
                                >
                                    {{ story.name }}
                                </span>
                            </div>
                        </section>

                        <!-- ─── Threads Style Feed Filter Tabs ─────────────────────── -->
                        <div
                            class="flex items-center justify-around border-b border-border/40 text-xs font-semibold sm:justify-start sm:gap-8"
                        >
                            <button
                                type="button"
                                class="relative cursor-pointer py-2.5 transition-colors"
                                :class="
                                    activeTab === 'all'
                                        ? 'text-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="activeTab = 'all'"
                            >
                                <span>Workspace Feed</span>
                                <span
                                    v-if="activeTab === 'all'"
                                    class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-foreground"
                                />
                            </button>
                            <button
                                type="button"
                                class="relative cursor-pointer py-2.5 transition-colors"
                                :class="
                                    activeTab === 'section'
                                        ? 'text-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="activeTab = 'section'"
                            >
                                <span>My Section</span>
                                <span
                                    v-if="activeTab === 'section'"
                                    class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-foreground"
                                />
                            </button>
                            <button
                                type="button"
                                class="relative cursor-pointer py-2.5 transition-colors"
                                :class="
                                    activeTab === 'saved'
                                        ? 'text-foreground'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="activeTab = 'saved'"
                            >
                                <span>Saved</span>
                                <span
                                    v-if="activeTab === 'saved'"
                                    class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-foreground"
                                />
                            </button>
                        </div>

                        <!-- ─── Feed Container Placeholder ────────────────────────── -->
                        <div
                            class="space-y-5"
                            data-test="community-feed-container"
                        >
                            <!-- Instagram / Threads Composer Row -->
                            <div
                                class="rounded-2xl border border-border/60 bg-card p-4 shadow-2xs transition-colors hover:border-border"
                            >
                                <div class="flex items-start gap-3">
                                    <!-- User Avatar -->
                                    <div
                                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border/80 bg-brand/15 text-xs font-bold text-brand select-none"
                                    >
                                        AL
                                    </div>

                                    <!-- Input Row -->
                                    <div class="min-w-0 flex-1">
                                        <div
                                            class="w-full cursor-text rounded-full border border-border/60 bg-muted/40 px-4 py-2 text-xs text-muted-foreground transition-all hover:border-border hover:bg-muted/60"
                                        >
                                            Share learning tips, ask questions,
                                            or start a discussion...
                                        </div>

                                        <!-- Composer Action Tray -->
                                        <div
                                            class="mt-3 flex items-center justify-between border-t border-border/30 pt-2.5 text-xs"
                                        >
                                            <div
                                                class="flex items-center gap-1 text-muted-foreground"
                                            >
                                                <button
                                                    type="button"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full hover:bg-muted hover:text-foreground"
                                                    title="Attach photo/screenshot"
                                                >
                                                    <ImageIcon
                                                        class="h-3.5 w-3.5"
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full hover:bg-muted hover:text-foreground"
                                                    title="Code snippet"
                                                >
                                                    <Code2
                                                        class="h-3.5 w-3.5"
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full hover:bg-muted hover:text-foreground"
                                                    title="Poll / Survey"
                                                >
                                                    <BarChart2
                                                        class="h-3.5 w-3.5"
                                                    />
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex h-7 w-7 items-center justify-center rounded-full hover:bg-muted hover:text-foreground"
                                                    title="Emoji"
                                                >
                                                    <Smile
                                                        class="h-3.5 w-3.5"
                                                    />
                                                </button>
                                            </div>

                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <span
                                                    class="hidden text-[11px] text-muted-foreground sm:inline-flex"
                                                >
                                                    Identity masked outside
                                                    section
                                                </span>
                                                <button
                                                    type="button"
                                                    disabled
                                                    class="inline-flex cursor-not-allowed items-center rounded-full bg-foreground/10 px-3.5 py-1 text-xs font-semibold text-muted-foreground opacity-60"
                                                >
                                                    Post
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ─── Post Card 1: Sarah Chen (Instagram Media Card) ──── -->
                            <article
                                class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-2xs transition-colors hover:border-border"
                            >
                                <!-- Post Header -->
                                <div
                                    class="flex items-center justify-between p-4 pb-3"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <!-- Avatar with Story Ring -->
                                        <div class="relative shrink-0">
                                            <div
                                                class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-tr from-amber-500 via-rose-500 to-purple-600 p-[2px]"
                                            >
                                                <div
                                                    class="flex h-full w-full items-center justify-center rounded-full border-2 border-card bg-emerald-500/10 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                                >
                                                    SC
                                                </div>
                                            </div>
                                            <span
                                                class="absolute right-0 bottom-0 h-2.5 w-2.5 rounded-full border-2 border-card bg-emerald-500"
                                                title="Online"
                                            />
                                        </div>

                                        <div class="min-w-0">
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="cursor-pointer text-xs font-bold text-foreground hover:underline"
                                                >
                                                    sarah.chen
                                                </span>
                                                <span
                                                    class="text-[10px] text-muted-foreground"
                                                    >•</span
                                                >
                                                <span
                                                    class="text-xs font-medium text-muted-foreground"
                                                >
                                                    Sarah Chen
                                                </span>
                                                <span
                                                    class="py-0.2 rounded-full bg-emerald-500/10 px-1.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                                >
                                                    Same Section
                                                </span>
                                            </div>
                                            <p
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                BSIT 3-A · 25m ago
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-full p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                        aria-label="Post options"
                                    >
                                        <MoreHorizontal class="h-4 w-4" />
                                    </button>
                                </div>

                                <!-- Post Text Content -->
                                <div class="px-4 pb-3">
                                    <p
                                        class="text-xs leading-relaxed text-foreground/90 sm:text-[13px]"
                                    >
                                        Just uploaded the review notes for
                                        Computer Networking 3. Feel free to
                                        check the Study Hub section before
                                        tomorrow's quiz!
                                    </p>
                                </div>

                                <!-- Instagram Document / Attachment Preview -->
                                <div class="px-4 pb-3">
                                    <div
                                        class="group flex cursor-pointer items-center justify-between gap-3 rounded-xl border border-border/70 bg-muted/30 p-3 transition-colors hover:border-border hover:bg-muted/50"
                                    >
                                        <div
                                            class="flex min-w-0 items-center gap-3"
                                        >
                                            <div
                                                class="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-rose-500/10 text-rose-500"
                                            >
                                                <FileText class="h-5 w-5" />
                                            </div>
                                            <div class="min-w-0">
                                                <p
                                                    class="truncate text-xs font-semibold text-foreground group-hover:text-brand"
                                                >
                                                    CN3_Midterm_Review_Notes_Final.pdf
                                                </p>
                                                <p
                                                    class="text-[11px] text-muted-foreground"
                                                >
                                                    12 pages · 4.2 MB · Computer
                                                    Networking III
                                                </p>
                                            </div>
                                        </div>
                                        <button
                                            type="button"
                                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border/60 bg-card text-muted-foreground transition-all hover:bg-foreground hover:text-background"
                                            title="Download preview"
                                        >
                                            <Download class="h-3.5 w-3.5" />
                                        </button>
                                    </div>
                                </div>

                                <!-- Instagram Action Bar -->
                                <div
                                    class="flex items-center justify-between border-t border-border/40 px-4 py-2.5"
                                >
                                    <div class="flex items-center gap-4">
                                        <!-- Heart / Like -->
                                        <button
                                            type="button"
                                            class="group inline-flex cursor-pointer items-center gap-1.5 text-xs transition-colors"
                                            :class="
                                                likedPosts['post-1']
                                                    ? 'text-rose-500'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            "
                                            @click="toggleLike('post-1')"
                                        >
                                            <Heart
                                                class="h-4 w-4 transition-transform group-hover:scale-115 active:scale-90"
                                                :class="{
                                                    'fill-rose-500':
                                                        likedPosts['post-1'],
                                                }"
                                            />
                                            <span class="font-medium">{{
                                                postLikesCount['post-1']
                                            }}</span>
                                        </button>

                                        <!-- Comment -->
                                        <button
                                            type="button"
                                            class="group inline-flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                                        >
                                            <MessageCircle
                                                class="h-4 w-4 transition-transform group-hover:scale-115 active:scale-90"
                                            />
                                            <span class="font-medium">4</span>
                                        </button>

                                        <!-- Send / Share -->
                                        <button
                                            type="button"
                                            class="group inline-flex cursor-pointer items-center text-xs text-muted-foreground transition-colors hover:text-foreground"
                                            aria-label="Share post"
                                        >
                                            <Send
                                                class="h-4 w-4 -rotate-12 transition-transform group-hover:scale-115 active:scale-90"
                                            />
                                        </button>
                                    </div>

                                    <!-- Bookmark -->
                                    <button
                                        type="button"
                                        class="cursor-pointer text-muted-foreground transition-colors hover:text-foreground"
                                        :class="{
                                            'text-foreground':
                                                bookmarkedPosts['post-1'],
                                        }"
                                        aria-label="Save post"
                                        @click="toggleBookmark('post-1')"
                                    >
                                        <Bookmark
                                            class="h-4 w-4"
                                            :class="{
                                                'fill-foreground':
                                                    bookmarkedPosts['post-1'],
                                            }"
                                        />
                                    </button>
                                </div>

                                <!-- Instagram Social Proof / Comments Feed -->
                                <div
                                    class="space-y-1.5 border-t border-border/30 bg-muted/15 px-4 py-2.5 text-xs"
                                >
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Liked by
                                        <span
                                            class="font-semibold text-foreground"
                                            >marcus.vance</span
                                        >
                                        and
                                        <span
                                            class="font-semibold text-foreground"
                                            >11 others</span
                                        >
                                    </p>
                                    <button
                                        type="button"
                                        class="cursor-pointer text-[11px] text-muted-foreground hover:underline"
                                    >
                                        View all 4 comments
                                    </button>
                                    <div class="text-[11px] text-foreground/90">
                                        <span class="font-semibold"
                                            >marcus.vance</span
                                        >
                                        <span
                                            class="ml-1.5 text-muted-foreground"
                                            >Does this cover subnetting table in
                                            chapter 4?</span
                                        >
                                    </div>

                                    <!-- Quick comment input -->
                                    <div
                                        class="mt-2 flex items-center justify-between border-t border-border/20 pt-1"
                                    >
                                        <input
                                            type="text"
                                            placeholder="Add a comment…"
                                            class="w-full bg-transparent text-[11px] placeholder:text-muted-foreground/60 focus:outline-none"
                                        />
                                        <button
                                            type="button"
                                            class="cursor-pointer text-[11px] font-semibold text-brand hover:opacity-80"
                                        >
                                            Post
                                        </button>
                                    </div>
                                </div>
                            </article>

                            <!-- ─── Post Card 2: Student #4928 (Masked / Cross-Section) -->
                            <article
                                class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-2xs transition-colors hover:border-border"
                            >
                                <!-- Post Header -->
                                <div
                                    class="flex items-center justify-between p-4 pb-3"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <div
                                            class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full border border-border/80 bg-muted text-xs font-semibold text-muted-foreground"
                                        >
                                            <EyeOff class="h-4 w-4" />
                                        </div>
                                        <div>
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="text-xs font-bold text-foreground"
                                                >
                                                    student.4928
                                                </span>
                                                <span
                                                    class="text-[10px] text-muted-foreground"
                                                    >•</span
                                                >
                                                <span
                                                    class="text-xs text-muted-foreground"
                                                >
                                                    Student #4928
                                                </span>
                                                <span
                                                    class="py-0.2 rounded-full bg-muted px-1.5 text-[10px] font-medium text-muted-foreground"
                                                >
                                                    Other Section
                                                </span>
                                            </div>
                                            <p
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                BSIT 3rd Year · 1h ago
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-full p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                        aria-label="Post options"
                                    >
                                        <MoreHorizontal class="h-4 w-4" />
                                    </button>
                                </div>

                                <!-- Post Content -->
                                <div class="px-4 pb-3">
                                    <p
                                        class="text-xs leading-relaxed text-foreground/90 sm:text-[13px]"
                                    >
                                        How did you solve question #3 in the
                                        networking assignment? Did you use
                                        Dijkstra or Bellman-Ford algorithm?
                                    </p>
                                </div>

                                <!-- Instagram Action Bar -->
                                <div
                                    class="flex items-center justify-between border-t border-border/40 px-4 py-2.5"
                                >
                                    <div class="flex items-center gap-4">
                                        <button
                                            type="button"
                                            class="group inline-flex cursor-pointer items-center gap-1.5 text-xs transition-colors"
                                            :class="
                                                likedPosts['post-2']
                                                    ? 'text-rose-500'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            "
                                            @click="toggleLike('post-2')"
                                        >
                                            <Heart
                                                class="h-4 w-4 transition-transform group-hover:scale-115 active:scale-90"
                                                :class="{
                                                    'fill-rose-500':
                                                        likedPosts['post-2'],
                                                }"
                                            />
                                            <span class="font-medium">{{
                                                postLikesCount['post-2']
                                            }}</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="group inline-flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                                        >
                                            <MessageCircle
                                                class="h-4 w-4 transition-transform group-hover:scale-115 active:scale-90"
                                            />
                                            <span class="font-medium">9</span>
                                        </button>

                                        <button
                                            type="button"
                                            class="group inline-flex cursor-pointer items-center text-xs text-muted-foreground transition-colors hover:text-foreground"
                                            aria-label="Share post"
                                        >
                                            <Send
                                                class="h-4 w-4 -rotate-12 transition-transform group-hover:scale-115 active:scale-90"
                                            />
                                        </button>
                                    </div>

                                    <button
                                        type="button"
                                        class="cursor-pointer text-muted-foreground transition-colors hover:text-foreground"
                                        :class="{
                                            'text-foreground':
                                                bookmarkedPosts['post-2'],
                                        }"
                                        aria-label="Save post"
                                        @click="toggleBookmark('post-2')"
                                    >
                                        <Bookmark
                                            class="h-4 w-4"
                                            :class="{
                                                'fill-foreground':
                                                    bookmarkedPosts['post-2'],
                                            }"
                                        />
                                    </button>
                                </div>

                                <!-- Instagram Social Proof / Comments -->
                                <div
                                    class="space-y-1.5 border-t border-border/30 bg-muted/15 px-4 py-2.5 text-xs"
                                >
                                    <button
                                        type="button"
                                        class="cursor-pointer text-[11px] text-muted-foreground hover:underline"
                                    >
                                        View all 9 comments
                                    </button>
                                    <div class="text-[11px] text-foreground/90">
                                        <span class="font-semibold"
                                            >alex.rivera</span
                                        >
                                        <span
                                            class="ml-1.5 text-muted-foreground"
                                            >Dijkstra is faster O(E log V)
                                            unless negative weights are
                                            specified.</span
                                        >
                                    </div>
                                </div>
                            </article>
                        </div>
                    </main>

                    <!-- ═══ Right Sidebar (Instagram Web 'Suggested for You' Style) ═══ -->
                    <aside
                        class="hidden space-y-6 lg:sticky lg:top-6 lg:col-span-5 lg:block xl:col-span-4"
                    >
                        <!-- Instagram Profile Snippet at top of rail -->
                        <div class="flex items-center justify-between px-1">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-11 w-11 items-center justify-center rounded-full border border-border/80 bg-brand/15 text-xs font-bold text-brand select-none"
                                >
                                    AL
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-bold text-foreground"
                                    >
                                        alex.rivera
                                    </p>
                                    <p
                                        class="truncate text-[11px] text-muted-foreground"
                                    >
                                        Alex Rivera · {{ sectionDisplayName }}
                                    </p>
                                </div>
                            </div>
                            <span
                                class="cursor-pointer text-[11px] font-semibold text-brand hover:underline"
                            >
                                You
                            </span>
                        </div>

                        <!-- Card 1: Section Online Presence / Classmates Rail -->
                        <div
                            data-test="section-presence-card"
                            class="rounded-2xl border border-border/60 bg-card p-4 shadow-2xs"
                        >
                            <!-- Instagram 'Suggested for you' style header -->
                            <div
                                class="flex items-center justify-between border-b border-border/40 pb-3"
                            >
                                <div>
                                    <h2
                                        class="text-xs font-bold tracking-tight text-foreground uppercase"
                                    >
                                        {{ sectionDisplayName }}
                                    </h2>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Classroom Presence
                                    </p>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                        />
                                        {{ onlineCount }} Online ·
                                        {{ totalEnrolledCount }} Enrolled
                                    </span>
                                    <button
                                        type="button"
                                        class="inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded-md text-muted-foreground hover:bg-muted hover:text-foreground"
                                        :class="{
                                            'opacity-60': isRefreshingPresence,
                                        }"
                                        title="Refresh presence"
                                        @click="handleRefreshPresence"
                                    >
                                        <RefreshCw
                                            class="h-3 w-3"
                                            :class="{
                                                'animate-spin':
                                                    isRefreshingPresence,
                                            }"
                                        />
                                    </button>
                                </div>
                            </div>

                            <!-- Sleek Search Input -->
                            <div class="relative mt-3">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                                />
                                <input
                                    v-model="classmateSearch"
                                    type="text"
                                    placeholder="Search classmates..."
                                    class="h-8 w-full rounded-xl border border-border/60 bg-muted/40 pr-8 pl-8.5 text-xs text-foreground placeholder:text-muted-foreground/70 focus:border-foreground/30 focus:bg-background focus:outline-none"
                                />
                                <button
                                    v-if="classmateSearch"
                                    type="button"
                                    class="absolute top-1/2 right-2.5 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                                    aria-label="Clear search"
                                    @click="classmateSearch = ''"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>

                            <!-- Classmate Member List -->
                            <div class="mt-3 divide-y divide-border/30">
                                <div
                                    v-for="member in filteredClassmates"
                                    :key="member.id"
                                    class="group flex items-center justify-between gap-3 py-2.5 first:pt-1 last:pb-1"
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2.5"
                                    >
                                        <!-- Avatar -->
                                        <div class="relative shrink-0">
                                            <div
                                                class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold select-none"
                                                :class="[
                                                    member.isCurrentUser
                                                        ? 'bg-brand/15 text-brand ring-1 ring-brand/40'
                                                        : member.status ===
                                                            'online'
                                                          ? 'bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/30 dark:text-emerald-400'
                                                          : member.status ===
                                                              'idle'
                                                            ? 'bg-amber-500/10 text-amber-600 ring-1 ring-amber-500/30 dark:text-amber-400'
                                                            : 'bg-muted text-muted-foreground ring-1 ring-border',
                                                ]"
                                            >
                                                {{ getInitials(member.name) }}
                                            </div>
                                            <span
                                                class="absolute -right-0.5 -bottom-0.5 h-2 w-2 rounded-full ring-2 ring-card"
                                                :class="{
                                                    'bg-emerald-500':
                                                        member.status ===
                                                        'online',
                                                    'bg-amber-500':
                                                        member.status ===
                                                        'idle',
                                                    'bg-muted-foreground/30':
                                                        member.status ===
                                                        'offline',
                                                }"
                                            />
                                        </div>

                                        <div class="min-w-0">
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <p
                                                    class="cursor-pointer truncate text-xs font-semibold text-foreground group-hover:underline"
                                                >
                                                    {{ member.name }}
                                                </p>
                                                <span
                                                    v-if="member.role"
                                                    class="py-0.2 rounded bg-muted px-1 text-[9px] font-medium text-muted-foreground"
                                                >
                                                    {{ member.role }}
                                                </span>
                                            </div>
                                            <p
                                                v-if="member.activity"
                                                class="truncate text-[10px] text-muted-foreground"
                                            >
                                                {{ member.activity }}
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Status beacon or follow action -->
                                    <span
                                        v-if="member.status === 'online'"
                                        class="shrink-0 text-[10px] font-medium text-emerald-600 dark:text-emerald-400"
                                    >
                                        Active
                                    </span>
                                    <span
                                        v-else-if="member.status === 'idle'"
                                        class="shrink-0 text-[10px] text-amber-600 dark:text-amber-400"
                                    >
                                        Idle
                                    </span>
                                </div>

                                <!-- Empty search state -->
                                <div
                                    v-if="filteredClassmates.length === 0"
                                    data-test="classmates-empty-state"
                                    class="py-6 text-center text-xs text-muted-foreground"
                                >
                                    <p>No classmates match your search.</p>
                                    <button
                                        type="button"
                                        class="mt-1 cursor-pointer text-[11px] font-medium text-brand hover:underline"
                                        @click="classmateSearch = ''"
                                    >
                                        Clear search
                                    </button>
                                </div>
                            </div>

                            <!-- Privacy Note -->
                            <div
                                data-test="section-privacy-note"
                                class="mt-3.5 flex items-start gap-2 rounded-xl border border-border/40 bg-muted/20 p-2.5 text-[11px] leading-relaxed text-muted-foreground"
                            >
                                <ShieldCheck
                                    class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />
                                <span>
                                    Real names are visible only to classmates in
                                    your section. Other sections cannot see this
                                    presence list.
                                </span>
                            </div>
                        </div>

                        <!-- Card 2: Trending in Section (Clean Hashtag Feed) -->
                        <div
                            data-test="section-topics-card"
                            class="rounded-2xl border border-border/60 bg-card p-4 shadow-2xs"
                        >
                            <div class="flex items-center justify-between pb-2">
                                <h3
                                    class="text-xs font-bold tracking-tight text-foreground uppercase"
                                >
                                    Trending in Section
                                </h3>
                                <span class="text-[10px] text-muted-foreground"
                                    >This week</span
                                >
                            </div>

                            <div class="mt-2 divide-y divide-border/30">
                                <div
                                    v-for="topic in trendingTopics"
                                    :key="topic.tag"
                                    class="flex cursor-pointer items-center justify-between py-2 text-xs transition-colors hover:text-brand"
                                >
                                    <div>
                                        <p
                                            class="font-semibold text-foreground"
                                        >
                                            #{{ topic.name }}
                                        </p>
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            {{ topic.count }} discussions
                                        </p>
                                    </div>
                                    <span
                                        class="text-[10px] text-muted-foreground"
                                        >Trending</span
                                    >
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Lounge Guidelines -->
                        <div
                            data-test="section-guidelines-card"
                            class="rounded-2xl border border-border/60 bg-card p-4 text-xs shadow-2xs"
                        >
                            <div
                                class="flex items-center gap-1.5 font-semibold text-foreground"
                            >
                                <Shield
                                    class="h-3.5 w-3.5 text-muted-foreground"
                                />
                                <span>Lounge Guidelines</span>
                            </div>
                            <ul
                                class="mt-2 space-y-1.5 text-[11px] leading-normal text-muted-foreground"
                            >
                                <li class="flex items-start gap-1.5">
                                    <span
                                        class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-muted-foreground"
                                    />
                                    <span>
                                        Identity boundary: Real name visible
                                        only to
                                        {{ sectionDisplayName }} classmates.
                                    </span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span
                                        class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-muted-foreground"
                                    />
                                    <span>
                                        Follow university honor code for
                                        assignments and exams.
                                    </span>
                                </li>
                                <li class="flex items-start gap-1.5">
                                    <span
                                        class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-muted-foreground"
                                    />
                                    <span>
                                        Helpful peer reviews and constructive
                                        collaboration are encouraged.
                                    </span>
                                </li>
                            </ul>
                        </div>

                        <!-- Instagram-style Micro Footer -->
                        <footer
                            class="space-y-1 px-1 text-[11px] leading-relaxed text-muted-foreground/60 select-none"
                        >
                            <p>
                                Guidelines • Honor Code • Privacy • Terms •
                                Workspace Help
                            </p>
                            <p>© 2026 LSI ENGINE • COMMUNITY LOUNGE</p>
                        </footer>
                    </aside>
                </div>
            </div>
        </div>

        <!-- ═══ Mobile Section Online Presence Drawer ═══ -->
        <div v-if="isMobileDrawerOpen" class="fixed inset-0 z-[120] lg:hidden">
            <transition name="drawer-fade" appear>
                <div
                    class="fixed inset-0 bg-black/60 backdrop-blur-xs"
                    data-test="mobile-presence-drawer-backdrop"
                    @click="isMobileDrawerOpen = false"
                />
            </transition>

            <transition name="drawer-slide" appear>
                <div
                    data-test="mobile-presence-drawer"
                    class="fixed inset-x-0 bottom-0 z-[130] flex max-h-[85vh] flex-col rounded-t-[28px] border-t border-border/70 bg-card p-5 shadow-2xl backdrop-blur-2xl"
                    style="
                        padding-bottom: calc(
                            env(safe-area-inset-bottom) + 1.25rem
                        );
                    "
                >
                    <div
                        class="mx-auto -mt-1 mb-3.5 h-1.5 w-10 shrink-0 rounded-full bg-muted-foreground/25"
                    />

                    <!-- Drawer Header -->
                    <div
                        class="flex shrink-0 items-center justify-between gap-2 border-b border-border/40 pb-3"
                    >
                        <div>
                            <h2
                                class="text-sm font-bold tracking-tight text-foreground"
                            >
                                {{ sectionDisplayName }}
                            </h2>
                            <p class="text-[10px] text-muted-foreground">
                                Classroom Presence
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1.5">
                            <span
                                class="inline-flex items-center rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                            >
                                {{ onlineCount }} Online ·
                                {{ totalEnrolledCount }} Enrolled
                            </span>
                            <button
                                type="button"
                                class="inline-flex h-6 w-6 cursor-pointer items-center justify-center rounded-lg border border-border/60 text-muted-foreground"
                                :class="{
                                    'opacity-60': isRefreshingPresence,
                                }"
                                title="Refresh presence"
                                @click="handleRefreshPresence"
                            >
                                <RefreshCw
                                    class="h-3 w-3"
                                    :class="{
                                        'animate-spin': isRefreshingPresence,
                                    }"
                                />
                            </button>
                            <button
                                type="button"
                                data-test="mobile-presence-drawer-close"
                                class="ml-1 inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-full text-muted-foreground hover:bg-muted"
                                aria-label="Close drawer"
                                @click="isMobileDrawerOpen = false"
                            >
                                <X class="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <!-- Search Input inside Drawer -->
                    <div class="relative mt-3.5 shrink-0">
                        <Search
                            class="pointer-events-none absolute top-1/2 left-3 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                        />
                        <input
                            v-model="classmateSearch"
                            type="text"
                            placeholder="Search classmates..."
                            class="h-8 w-full rounded-xl border border-border/60 bg-muted/40 pr-8 pl-8.5 text-xs text-foreground placeholder:text-muted-foreground/70 focus:outline-none"
                        />
                        <button
                            v-if="classmateSearch"
                            type="button"
                            class="absolute top-1/2 right-2.5 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                            aria-label="Clear search"
                            @click="classmateSearch = ''"
                        >
                            <X class="h-3 w-3" />
                        </button>
                    </div>

                    <!-- Scrollable Member List -->
                    <div
                        class="mt-3.5 min-h-0 flex-1 divide-y divide-border/30 overflow-y-auto overscroll-contain pr-0.5"
                    >
                        <div
                            v-for="member in filteredClassmates"
                            :key="'mobile-' + member.id"
                            class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0"
                        >
                            <div class="relative shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center rounded-full text-xs font-semibold select-none"
                                    :class="[
                                        member.isCurrentUser
                                            ? 'bg-brand/15 text-brand ring-1 ring-brand/40'
                                            : member.status === 'online'
                                              ? 'bg-emerald-500/10 text-emerald-600 ring-1 ring-emerald-500/30 dark:text-emerald-400'
                                              : member.status === 'idle'
                                                ? 'bg-amber-500/10 text-amber-600 ring-1 ring-amber-500/30 dark:text-amber-400'
                                                : 'bg-muted text-muted-foreground ring-1 ring-border',
                                    ]"
                                >
                                    {{ getInitials(member.name) }}
                                </div>
                                <span
                                    class="absolute -right-0.5 -bottom-0.5 h-2 w-2 rounded-full ring-2 ring-card"
                                    :class="{
                                        'bg-emerald-500':
                                            member.status === 'online',
                                        'bg-amber-500':
                                            member.status === 'idle',
                                        'bg-muted-foreground/30':
                                            member.status === 'offline',
                                    }"
                                />
                            </div>

                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="truncate text-xs font-semibold text-foreground"
                                    >
                                        {{ member.name }}
                                    </span>
                                    <span
                                        v-if="member.role"
                                        class="py-0.2 rounded bg-muted px-1.5 text-[10px] font-medium text-muted-foreground"
                                    >
                                        {{ member.role }}
                                    </span>
                                </div>
                                <p
                                    v-if="member.activity"
                                    class="truncate text-[11px] text-muted-foreground"
                                >
                                    {{ member.activity }}
                                </p>
                            </div>
                        </div>

                        <!-- Empty State -->
                        <div
                            v-if="filteredClassmates.length === 0"
                            class="py-6 text-center text-xs text-muted-foreground"
                        >
                            <p>No classmates match your search.</p>
                            <button
                                type="button"
                                class="mt-1 cursor-pointer text-[11px] font-medium text-brand hover:underline"
                                @click="classmateSearch = ''"
                            >
                                Clear search
                            </button>
                        </div>
                    </div>

                    <!-- Privacy Reassurance Footnote -->
                    <div
                        class="mt-3.5 flex shrink-0 items-start gap-2 rounded-xl border border-border/40 bg-muted/20 p-2.5 text-[11px] leading-relaxed text-muted-foreground"
                    >
                        <ShieldCheck
                            class="mt-0.5 h-3.5 w-3.5 shrink-0 text-emerald-600 dark:text-emerald-400"
                        />
                        <span>
                            Real names are visible only to classmates in your
                            section. Other sections cannot see this presence
                            list.
                        </span>
                    </div>
                </div>
            </transition>
        </div>

        <!-- Curtain Wipe Overlay -->
        <CurtainWipeOverlay
            ref="curtainRef"
            title="Dashboard"
            subtitle="Returning to dashboard..."
            expected-mount-direction="to_community"
        />
    </AppLayout>
</template>

<style scoped>
.presence-beacon-dot {
    animation: presenceBreathe 2.4s ease-in-out infinite;
}

.presence-beacon-ring {
    animation: presenceRing 2.4s cubic-bezier(0, 0, 0.2, 1) infinite;
}

@keyframes presenceBreathe {
    0%,
    100% {
        transform: scale(0.9);
        opacity: 0.9;
    }
    50% {
        transform: scale(1.15);
        opacity: 1;
    }
}

@keyframes presenceRing {
    0% {
        transform: scale(0.85);
        opacity: 0.8;
    }
    70%,
    100% {
        transform: scale(2.2);
        opacity: 0;
    }
}

.scrollbar-none {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.scrollbar-none::-webkit-scrollbar {
    display: none;
}

.drawer-fade-enter-active,
.drawer-fade-leave-active {
    transition: opacity 0.2s ease;
}
.drawer-fade-enter-from,
.drawer-fade-leave-to {
    opacity: 0;
}

.drawer-slide-enter-active,
.drawer-slide-leave-active {
    transition: transform 0.28s cubic-bezier(0.16, 1, 0.3, 1);
}
.drawer-slide-enter-from,
.drawer-slide-leave-to {
    transform: translateY(100%);
}
</style>
