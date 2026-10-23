<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    Bookmark,
    Check,
    Clock,
    ExternalLink,
    EyeOff,
    FileText,
    Film,
    GraduationCap,
    Heart,
    Loader2,
    MessageCircle,
    Play,
    Plus,
    Shield,
    Sparkles,
    Trash2,
    UploadCloud,
    UserCheck,
    UserPlus,
    X,
} from 'lucide-vue-next';
import { computed, onBeforeUnmount, ref } from 'vue';
import CurtainWipeOverlay from '@/components/common/CurtainWipeOverlay.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

export interface FeaturedMediaItem {
    id: number;
    media_url: string;
    media_type: 'image' | 'video';
    thumbnail_url?: string | null;
    title?: string | null;
    sort_order: number;
    created_at: string;
}

export interface LoungePostItem {
    id: number | string;
    content: string;
    attachment_url?: string | null;
    attachment_type?: string | null;
    attachment_name?: string | null;
    attachment_size?: number | null;
    likes_count: number;
    comments_count: number;
    created_at: string;
    is_liked?: boolean;
    is_bookmarked?: boolean;
    is_anonymous?: boolean;
    section_name?: string;
    author_name?: string;
    author_handle?: string;
    author_avatar?: string | null;
}

export interface CommunityProfileData {
    is_masked: boolean;
    is_self: boolean;
    public_id?: string | null;
    name: string;
    handle: string;
    avatar?: string | null;
    bio?: string;
    level?: number;
    section_name?: string;
    presence_status?: 'online' | 'idle' | 'offline';
    presence_activity?: string;
    can_follow?: boolean;
    is_following?: boolean;
    followers_count: number;
    following_count: number;
    posts_count: number;
    likes_received: number;
    posts: LoungePostItem[];
    saved_posts?: LoungePostItem[];
    featured_media?: FeaturedMediaItem[];
}

const props = defineProps<{
    profile: CommunityProfileData;
}>();

const curtainRef = ref<InstanceType<typeof CurtainWipeOverlay> | null>(null);
const activeTab = ref<'posts' | 'saved' | 'about'>('posts');

// Optimistic follow state
const isFollowingState = ref(Boolean(props.profile.is_following));
const followersCountState = ref(props.profile.followers_count || 0);

// Optimistic post likes and bookmarks
const likedMap = ref<Record<string, boolean>>(
    props.profile.posts.reduce(
        (acc, post) => {
            acc[String(post.id)] = Boolean(post.is_liked);
            return acc;
        },
        {} as Record<string, boolean>,
    ),
);

const likesCountMap = ref<Record<string, number>>(
    props.profile.posts.reduce(
        (acc, post) => {
            acc[String(post.id)] = post.likes_count || 0;
            return acc;
        },
        {} as Record<string, number>,
    ),
);

const bookmarkedMap = ref<Record<string, boolean>>(
    props.profile.posts.reduce(
        (acc, post) => {
            acc[String(post.id)] = Boolean(post.is_bookmarked);
            return acc;
        },
        {} as Record<string, boolean>,
    ),
);

// Server truth tracking for debouncing
const serverLikedMap = ref<Record<string, boolean>>({ ...likedMap.value });
const serverBookmarkedMap = ref<Record<string, boolean>>({
    ...bookmarkedMap.value,
});
const likeDebounceTimers = ref<Record<string, ReturnType<typeof setTimeout>>>(
    {},
);
const bookmarkDebounceTimers = ref<
    Record<string, ReturnType<typeof setTimeout>>
>({});
const isHeartBouncing = ref<Record<string, boolean>>({});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Community Lounge', href: '/community' },
    {
        title: props.profile.is_masked
            ? 'Student'
            : `@${props.profile.handle || props.profile.name}`,
        href: '#',
    },
]);

const getInitials = (name: string) => {
    return name
        .split(' ')
        .map((part) => part[0])
        .filter(Boolean)
        .slice(0, 2)
        .join('')
        .toUpperCase();
};

const handleBackToLounge = () => {
    if (curtainRef.value?.wipeTo) {
        curtainRef.value.wipeTo('/community', 'to_community');
    } else {
        router.visit('/community');
    }
};

const handleToggleFollow = () => {
    if (!props.profile.public_id || !props.profile.can_follow) {
        return;
    }

    const publicId = props.profile.public_id;
    const currentlyFollowing = isFollowingState.value;

    // Optimistic toggle
    isFollowingState.value = !currentlyFollowing;
    followersCountState.value = Math.max(
        0,
        followersCountState.value + (currentlyFollowing ? -1 : 1),
    );

    if (currentlyFollowing) {
        router.delete(`/u/${publicId}/follow`, {
            preserveScroll: true,
            preserveState: true,
        });
    } else {
        router.post(
            `/u/${publicId}/follow`,
            {},
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    }
};

const toggleLike = (postId: number | string) => {
    const key = String(postId);
    const prevLiked = Boolean(likedMap.value[key]);
    const prevCount = likesCountMap.value[key] ?? 0;
    const nextLiked = !prevLiked;

    // 1. Immediate optimistic UI update
    likedMap.value[key] = nextLiked;
    likesCountMap.value[key] = Math.max(0, prevCount + (nextLiked ? 1 : -1));

    // 2. Tactile bounce animation
    isHeartBouncing.value[key] = true;
    setTimeout(() => {
        isHeartBouncing.value[key] = false;
    }, 350);

    // 3. Clear existing debounce timer
    if (likeDebounceTimers.value[key]) {
        clearTimeout(likeDebounceTimers.value[key]);
    }

    // 4. Debounce network sync by 350ms
    likeDebounceTimers.value[key] = setTimeout(async () => {
        delete likeDebounceTimers.value[key];

        if (likedMap.value[key] === serverLikedMap.value[key]) {
            return;
        }

        try {
            const res = await axios.post(
                `/community/posts/${postId}/like`,
                {},
                { headers: { Accept: 'application/json' } },
            );
            if (res.data) {
                serverLikedMap.value[key] = Boolean(res.data.is_liked);
                if (typeof res.data.likes_count === 'number') {
                    likesCountMap.value[key] = res.data.likes_count;
                }
            }
        } catch {
            likedMap.value[key] = serverLikedMap.value[key] ?? prevLiked;
            likesCountMap.value[key] = prevCount;
        }
    }, 350);
};

const toggleBookmark = (postId: number | string) => {
    const key = String(postId);
    const prevBookmarked = Boolean(bookmarkedMap.value[key]);
    const nextBookmarked = !prevBookmarked;

    // 1. Immediate optimistic UI update
    bookmarkedMap.value[key] = nextBookmarked;

    if (bookmarkDebounceTimers.value[key]) {
        clearTimeout(bookmarkDebounceTimers.value[key]);
    }

    // 2. Debounce network sync by 350ms
    bookmarkDebounceTimers.value[key] = setTimeout(async () => {
        delete bookmarkDebounceTimers.value[key];

        if (bookmarkedMap.value[key] === serverBookmarkedMap.value[key]) {
            return;
        }

        try {
            const res = await axios.post(
                `/community/posts/${postId}/bookmark`,
                {},
                { headers: { Accept: 'application/json' } },
            );
            if (res.data) {
                serverBookmarkedMap.value[key] = Boolean(
                    res.data.is_bookmarked,
                );
            }
        } catch {
            bookmarkedMap.value[key] =
                serverBookmarkedMap.value[key] ?? prevBookmarked;
        }
    }, 350);
};

// ─── Meta / Facebook Style Featured Media Highlights ────────────────────────
export interface ArchivedStoryItem {
    id: number;
    media_url: string;
    media_type: 'image' | 'video';
    duration_seconds: number;
    caption?: string | null;
    views_count: number;
    reactions_count: number;
    is_expired: boolean;
    expires_at: string;
    created_at: string;
    formatted_date: string;
}

const isAddFeaturedModalOpen = ref(false);
const isFeaturedLightboxOpen = ref(false);
const activeFeaturedItem = ref<FeaturedMediaItem | null>(null);

const addFeaturedSource = ref<'upload' | 'archive'>('upload');
const archivedStories = ref<ArchivedStoryItem[]>([]);
const isLoadingArchive = ref(false);
const selectedArchivedStory = ref<ArchivedStoryItem | null>(null);

const featuredFileInputRef = ref<HTMLInputElement | null>(null);
const featuredMediaFile = ref<File | null>(null);
const featuredMediaPreview = ref<string | null>(null);
const featuredMediaType = ref<'image' | 'video'>('image');
const featuredTitle = ref<string>('');
const featuredUploadError = ref<string | null>(null);
const isSubmittingFeatured = ref<boolean>(false);
const isDeletingFeatured = ref<Record<string, boolean>>({});

const featuredList = computed<FeaturedMediaItem[]>(() => {
    return props.profile.featured_media ?? [];
});

const fetchStoryArchive = async () => {
    if (archivedStories.value.length > 0) return;
    isLoadingArchive.value = true;
    try {
        const response = await axios.get('/community/stories/archive');
        archivedStories.value = response.data.stories || [];
    } catch {
        // fail silently
    } finally {
        isLoadingArchive.value = false;
    }
};

const selectStoryFromArchive = (story: ArchivedStoryItem) => {
    selectedArchivedStory.value = story;
    featuredMediaType.value = story.media_type;
    featuredMediaPreview.value = story.media_url;
    if (story.caption && !featuredTitle.value) {
        featuredTitle.value = story.caption;
    }
};

const openAddFeaturedModal = (source?: 'upload' | 'archive' | Event) => {
    const validSource: 'upload' | 'archive' =
        source === 'archive' ? 'archive' : 'upload';
    addFeaturedSource.value = validSource;
    selectedArchivedStory.value = null;
    featuredMediaFile.value = null;
    featuredMediaPreview.value = null;
    featuredTitle.value = '';
    featuredUploadError.value = null;
    featuredMediaType.value = 'image';
    isAddFeaturedModalOpen.value = true;
    if (validSource === 'archive') {
        fetchStoryArchive();
    }
};

const closeAddFeaturedModal = () => {
    if (
        featuredMediaPreview.value &&
        featuredMediaPreview.value.startsWith('blob:')
    ) {
        URL.revokeObjectURL(featuredMediaPreview.value);
    }
    isAddFeaturedModalOpen.value = false;
    addFeaturedSource.value = 'upload';
    selectedArchivedStory.value = null;
    featuredMediaFile.value = null;
    featuredMediaPreview.value = null;
    featuredTitle.value = '';
    featuredUploadError.value = null;
    isSubmittingFeatured.value = false;
};

const handleFeaturedFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;

    featuredUploadError.value = null;
    const isVideo =
        file.type.startsWith('video/') || /\.(mp4|webm|mov)$/i.test(file.name);
    const isImg = file.type.startsWith('image/');

    if (!isVideo && !isImg) {
        featuredUploadError.value =
            'Please upload a photo (JPG, PNG, WebP) or video (MP4, WebM, MOV).';
        return;
    }

    if (isImg && file.size > 10 * 1024 * 1024) {
        featuredUploadError.value = 'Photo exceeds 10MB limit.';
        return;
    }

    if (isVideo && file.size > 30 * 1024 * 1024) {
        featuredUploadError.value = 'Video exceeds 30MB limit.';
        return;
    }

    featuredMediaType.value = isVideo ? 'video' : 'image';
    featuredMediaFile.value = file;
    featuredMediaPreview.value = URL.createObjectURL(file);
};

const handleUploadFeatured = () => {
    if (addFeaturedSource.value === 'archive') {
        if (!selectedArchivedStory.value) {
            featuredUploadError.value =
                'Please select a story from your archive.';
            return;
        }

        isSubmittingFeatured.value = true;
        featuredUploadError.value = null;

        router.post(
            '/community/profile/featured',
            {
                story_id: selectedArchivedStory.value.id,
                title:
                    featuredTitle.value.trim() ||
                    selectedArchivedStory.value.caption ||
                    undefined,
            },
            {
                preserveScroll: true,
                onSuccess: () => {
                    closeAddFeaturedModal();
                },
                onError: (errs) => {
                    featuredUploadError.value =
                        (Object.values(errs)[0] as string) ||
                        'Failed to add featured media.';
                    isSubmittingFeatured.value = false;
                },
                onFinish: () => {
                    isSubmittingFeatured.value = false;
                },
            },
        );
        return;
    }

    if (!featuredMediaFile.value) {
        featuredUploadError.value = 'Please select a photo or video to pin.';
        return;
    }

    isSubmittingFeatured.value = true;
    featuredUploadError.value = null;

    const formData = new FormData();
    formData.append('media', featuredMediaFile.value);
    if (featuredTitle.value.trim()) {
        formData.append('title', featuredTitle.value.trim());
    }

    router.post('/community/profile/featured', formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeAddFeaturedModal();
        },
        onError: (errs) => {
            featuredUploadError.value =
                (Object.values(errs)[0] as string) ||
                'Failed to add featured media.';
            isSubmittingFeatured.value = false;
        },
        onFinish: () => {
            isSubmittingFeatured.value = false;
        },
    });
};

const handleDeleteFeatured = (item: FeaturedMediaItem) => {
    if (!props.profile.is_self) return;
    const key = String(item.id);
    isDeletingFeatured.value[key] = true;

    router.delete(`/community/profile/featured/${item.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (activeFeaturedItem.value?.id === item.id) {
                closeFeaturedLightbox();
            }
        },
        onFinish: () => {
            delete isDeletingFeatured.value[key];
        },
    });
};

const openFeaturedLightbox = (item: FeaturedMediaItem) => {
    activeFeaturedItem.value = item;
    isFeaturedLightboxOpen.value = true;
};

const closeFeaturedLightbox = () => {
    isFeaturedLightboxOpen.value = false;
    activeFeaturedItem.value = null;
};

onBeforeUnmount(() => {
    for (const timer of Object.values(likeDebounceTimers.value)) {
        clearTimeout(timer);
    }
    for (const timer of Object.values(bookmarkDebounceTimers.value)) {
        clearTimeout(timer);
    }
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head
            :title="
                profile.is_masked
                    ? 'Student Profile · Lounge'
                    : `${profile.name} (@${profile.handle}) · Lounge Profile`
            "
        />

        <div class="relative min-h-[calc(100vh-4rem)] p-3 sm:p-5 lg:p-6">
            <div class="mx-auto max-w-4xl space-y-6">
                <!-- Navigation Top Bar -->
                <div class="flex items-center justify-between">
                    <button
                        type="button"
                        class="group inline-flex cursor-pointer items-center gap-2 rounded-xl border border-border/60 bg-card/70 px-3 py-1.5 text-xs font-semibold text-foreground backdrop-blur-md transition-all hover:border-brand/40 hover:bg-card hover:text-brand active:scale-95"
                        @click="handleBackToLounge"
                    >
                        <ArrowLeft
                            class="h-3.5 w-3.5 transition-transform group-hover:-translate-x-0.5"
                        />
                        <span>Back to Lounge Feed</span>
                    </button>

                    <!-- Academic Profile Switcher Pill -->
                    <Link
                        v-if="profile.public_id && !profile.is_masked"
                        :href="'/u/' + profile.public_id"
                        class="inline-flex items-center gap-1.5 rounded-full border border-brand/30 bg-brand/10 px-3.5 py-1.5 text-xs font-semibold text-brand transition-all hover:bg-brand/20 active:scale-95"
                    >
                        <GraduationCap class="h-3.5 w-3.5" />
                        <span>View Academic Profile</span>
                        <ExternalLink class="h-3 w-3 opacity-70" />
                    </Link>
                </div>

                <!-- Privacy Shielded State (Cross Section) -->
                <div
                    v-if="profile.is_masked"
                    class="overflow-hidden rounded-3xl border border-border/80 bg-card p-8 text-center shadow-sm"
                >
                    <div
                        class="mx-auto flex h-20 w-20 items-center justify-center rounded-full bg-muted/60 text-muted-foreground"
                    >
                        <EyeOff class="h-10 w-10" />
                    </div>
                    <div class="mt-4 space-y-1">
                        <h2 class="text-xl font-bold text-foreground">
                            {{ profile.name }}
                        </h2>
                        <p class="text-xs font-medium text-muted-foreground">
                            @{{ profile.handle }} ·
                            {{ profile.section_name }}
                        </p>
                    </div>
                    <div
                        class="mx-auto mt-6 max-w-md rounded-2xl border border-border/60 bg-muted/30 p-4 text-xs leading-relaxed text-muted-foreground"
                    >
                        <div
                            class="mb-2 flex items-center justify-center gap-1.5 font-semibold text-foreground"
                        >
                            <Shield class="h-4 w-4 text-brand" />
                            <span>Classroom Privacy Boundary</span>
                        </div>
                        This student is enrolled in a different section. To
                        preserve classroom privacy and prevent cross-section
                        disruption, personal lounge posts and social
                        interactions are reserved for classmates sharing the
                        same section.
                    </div>
                    <div class="mt-6">
                        <button
                            type="button"
                            class="inline-flex cursor-pointer items-center gap-2 rounded-full bg-brand px-5 py-2.5 text-xs font-semibold text-primary-foreground transition-all hover:opacity-90 active:scale-95"
                            @click="handleBackToLounge"
                        >
                            <ArrowLeft class="h-3.5 w-3.5" />
                            <span>Return to Community Lounge</span>
                        </button>
                    </div>
                </div>

                <!-- Unmasked Community Lounge Profile Card -->
                <div
                    v-else
                    class="overflow-hidden rounded-3xl border border-border/80 bg-card shadow-sm"
                >
                    <!-- Cover Banner (VisionOS Soft Gradient) -->
                    <div
                        class="relative h-40 bg-gradient-to-r from-brand/20 via-primary/10 to-brand/10 sm:h-48"
                    >
                        <div
                            class="absolute inset-0 bg-[radial-gradient(ellipse_at_top,_var(--tw-gradient-stops))] from-white/10 via-transparent to-transparent"
                        />
                        <div class="absolute top-4 right-4">
                            <span
                                class="inline-flex items-center gap-1.5 rounded-full border border-border/40 bg-background/70 px-3 py-1 text-[11px] font-semibold text-foreground backdrop-blur-md"
                            >
                                <Sparkles class="h-3 w-3 text-brand" />
                                <span>Community Lounge Persona</span>
                            </span>
                        </div>
                    </div>

                    <!-- Header Content -->
                    <div class="relative px-5 pb-6 sm:px-8">
                        <div
                            class="flex flex-col items-center gap-4 sm:flex-row sm:items-end sm:justify-between"
                        >
                            <!-- Avatar & Presence Beacon -->
                            <div
                                class="-mt-16 flex flex-col items-center gap-3 sm:-mt-20 sm:flex-row sm:items-end"
                            >
                                <div class="relative">
                                    <div
                                        class="h-28 w-28 overflow-hidden rounded-3xl border-4 border-card bg-muted shadow-md ring-1 ring-border/50 sm:h-32 sm:w-32"
                                    >
                                        <img
                                            v-if="profile.avatar"
                                            :src="profile.avatar"
                                            :alt="profile.name"
                                            class="h-full w-full object-cover"
                                        />
                                        <div
                                            v-else
                                            class="flex h-full w-full items-center justify-center bg-gradient-to-br from-brand/20 to-primary/20 text-2xl font-black text-brand"
                                        >
                                            {{ getInitials(profile.name) }}
                                        </div>
                                    </div>

                                    <!-- Presence Badge on Avatar -->
                                    <span
                                        class="absolute -right-1 -bottom-1 flex h-6 w-6 items-center justify-center rounded-full border-2 border-card shadow-sm"
                                        :class="
                                            profile.presence_status === 'online'
                                                ? 'bg-emerald-500'
                                                : profile.presence_status ===
                                                    'idle'
                                                  ? 'bg-amber-500'
                                                  : 'bg-zinc-400'
                                        "
                                        :title="profile.presence_activity"
                                    >
                                        <span
                                            v-if="
                                                profile.presence_status ===
                                                'online'
                                            "
                                            class="h-2 w-2 animate-ping rounded-full bg-white opacity-75"
                                        />
                                    </span>
                                </div>

                                <div class="text-center sm:text-left">
                                    <div
                                        class="flex flex-wrap items-center justify-center gap-2 sm:justify-start"
                                    >
                                        <h1
                                            class="text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                                        >
                                            {{ profile.name }}
                                        </h1>
                                        <span
                                            v-if="profile.level"
                                            class="rounded-full bg-brand/15 px-2.5 py-0.5 text-xs font-bold text-brand"
                                        >
                                            Lv. {{ profile.level }}
                                        </span>
                                    </div>
                                    <p
                                        class="text-xs font-medium text-muted-foreground"
                                    >
                                        @{{ profile.handle }} ·
                                        {{ profile.section_name }}
                                    </p>
                                </div>
                            </div>

                            <!-- Action Buttons -->
                            <div class="flex items-center gap-2">
                                <button
                                    v-if="profile.can_follow"
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-full px-5 py-2 text-xs font-semibold shadow-sm transition-all active:scale-95"
                                    :class="
                                        isFollowingState
                                            ? 'border border-border/80 bg-card text-foreground hover:bg-muted'
                                            : 'bg-brand text-primary-foreground hover:opacity-90'
                                    "
                                    @click="handleToggleFollow"
                                >
                                    <UserCheck
                                        v-if="isFollowingState"
                                        class="h-3.5 w-3.5"
                                    />
                                    <UserPlus v-else class="h-3.5 w-3.5" />
                                    <span>{{
                                        isFollowingState
                                            ? 'Following'
                                            : 'Follow'
                                    }}</span>
                                </button>

                                <span
                                    v-if="profile.is_self"
                                    class="inline-flex items-center rounded-full border border-border/80 bg-muted/40 px-3.5 py-1.5 text-xs font-medium text-muted-foreground"
                                >
                                    Your Lounge Profile
                                </span>
                            </div>
                        </div>

                        <!-- Bio & Presence Beacon Row -->
                        <div class="mt-4 space-y-2">
                            <p
                                class="max-w-2xl text-xs leading-relaxed text-foreground/80 sm:text-sm"
                            >
                                {{ profile.bio }}
                            </p>

                            <!-- Live Presence Beacon Pill -->
                            <div class="flex items-center gap-2 pt-1 text-xs">
                                <span
                                    class="inline-flex items-center gap-1.5 rounded-full border border-border/50 bg-muted/40 px-2.5 py-1 font-medium text-foreground"
                                >
                                    <span
                                        class="h-2 w-2 rounded-full"
                                        :class="
                                            profile.presence_status === 'online'
                                                ? 'bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.5)]'
                                                : profile.presence_status ===
                                                    'idle'
                                                  ? 'bg-amber-500'
                                                  : 'bg-zinc-400'
                                        "
                                    />
                                    <span>{{
                                        profile.presence_activity || 'Offline'
                                    }}</span>
                                </span>

                                <span class="text-muted-foreground">·</span>
                                <span class="text-muted-foreground"
                                    >Section: {{ profile.section_name }}</span
                                >
                            </div>
                        </div>

                        <!-- Instagram Style Stats Counter Grid -->
                        <div
                            class="mt-6 grid grid-cols-4 divide-x divide-border/60 rounded-2xl border border-border/60 bg-muted/20 py-3 text-center"
                        >
                            <div class="px-2">
                                <span
                                    class="block text-base font-bold text-foreground sm:text-lg"
                                >
                                    {{ profile.posts_count }}
                                </span>
                                <span
                                    class="text-[11px] font-medium text-muted-foreground"
                                    >Lounge Posts</span
                                >
                            </div>
                            <div class="px-2">
                                <span
                                    class="block text-base font-bold text-foreground sm:text-lg"
                                >
                                    {{ profile.likes_received }}
                                </span>
                                <span
                                    class="inline-flex items-center gap-1 text-[11px] font-medium text-muted-foreground"
                                >
                                    <Heart class="h-3 w-3 text-rose-500" />
                                    <span>Likes</span>
                                </span>
                            </div>
                            <div class="px-2">
                                <span
                                    class="block text-base font-bold text-foreground sm:text-lg"
                                >
                                    {{ followersCountState }}
                                </span>
                                <span
                                    class="text-[11px] font-medium text-muted-foreground"
                                    >Followers</span
                                >
                            </div>
                            <div class="px-2">
                                <span
                                    class="block text-base font-bold text-foreground sm:text-lg"
                                >
                                    {{ profile.following_count }}
                                </span>
                                <span
                                    class="text-[11px] font-medium text-muted-foreground"
                                    >Following</span
                                >
                            </div>
                        </div>

                        <!-- Notice: Distinct from Academic Profile -->
                        <div
                            class="mt-5 flex flex-col items-start justify-between gap-3 rounded-2xl border border-border/50 bg-gradient-to-r from-brand/5 via-card to-transparent p-4 sm:flex-row sm:items-center"
                        >
                            <div class="space-y-0.5">
                                <div
                                    class="flex items-center gap-1.5 text-xs font-semibold text-foreground"
                                >
                                    <GraduationCap class="h-4 w-4 text-brand" />
                                    <span
                                        >Student Learning Platform Profile</span
                                    >
                                </div>
                                <p
                                    class="text-[11px] leading-relaxed text-muted-foreground"
                                >
                                    Academic grades, enrolled courses, quest
                                    badges, and seasonal XP belong to the
                                    learning platform profile.
                                </p>
                            </div>
                            <Link
                                v-if="profile.public_id"
                                :href="'/u/' + profile.public_id"
                                class="inline-flex shrink-0 items-center gap-1.5 rounded-full border border-border/80 bg-card px-3.5 py-1.5 text-xs font-semibold text-foreground shadow-sm transition-all hover:bg-muted active:scale-95"
                            >
                                <span>Switch to Academic Profile</span>
                                <ExternalLink class="h-3 w-3 opacity-70" />
                            </Link>
                        </div>
                    </div>

                    <!-- ═══ Facebook / Meta Style Featured Highlights ═══ -->
                    <div
                        class="border-t border-border/60 bg-muted/10 px-5 py-4 sm:px-8"
                        data-test="featured-highlights-section"
                    >
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <Sparkles class="h-4 w-4 text-brand" />
                                <h3
                                    class="text-xs font-bold tracking-wider text-foreground uppercase"
                                >
                                    Featured
                                </h3>
                                <span
                                    class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-semibold text-muted-foreground"
                                >
                                    {{ featuredList.length }}/6
                                </span>
                            </div>

                            <button
                                v-if="
                                    profile.is_self && featuredList.length < 6
                                "
                                type="button"
                                data-test="add-featured-trigger"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-border/80 bg-card px-3 py-1 text-xs font-semibold text-foreground shadow-2xs transition hover:border-brand/50 hover:bg-muted hover:text-brand active:scale-95"
                                @click="openAddFeaturedModal"
                            >
                                <Plus class="h-3 w-3 stroke-[2.5]" />
                                <span>Add Featured</span>
                            </button>
                        </div>

                        <!-- Empty state when not self -->
                        <div
                            v-if="featuredList.length === 0 && !profile.is_self"
                            class="rounded-2xl border border-dashed border-border/60 p-4 text-center text-xs text-muted-foreground"
                        >
                            No featured highlights shared yet.
                        </div>

                        <!-- Empty state prompt for self -->
                        <div
                            v-else-if="
                                featuredList.length === 0 && profile.is_self
                            "
                            class="group flex cursor-pointer items-center justify-between rounded-2xl border border-dashed border-border/70 bg-card/60 p-4 transition hover:border-brand/50 hover:bg-brand/5"
                            data-test="add-featured-empty-prompt"
                            @click="openAddFeaturedModal"
                        >
                            <div class="space-y-0.5">
                                <p
                                    class="text-xs font-semibold text-foreground"
                                >
                                    Feature your favorite photos and videos
                                </p>
                                <p class="text-[11px] text-muted-foreground">
                                    Share up to 6 permanent highlights on your
                                    lounge profile.
                                </p>
                            </div>
                            <span
                                class="inline-flex items-center gap-1 text-xs font-bold text-brand"
                            >
                                <Plus class="h-3.5 w-3.5" />
                                <span>Add now</span>
                            </span>
                        </div>

                        <!-- 6-Slot Squircle Grid -->
                        <div
                            v-else
                            class="grid grid-cols-3 gap-2.5 sm:grid-cols-6 sm:gap-3"
                        >
                            <div
                                v-for="item in featuredList"
                                :key="item.id"
                                class="group relative aspect-[3/4] cursor-pointer overflow-hidden rounded-2xl border border-border/70 bg-muted/40 shadow-xs transition hover:border-brand/60"
                                data-test="featured-item"
                                @click="openFeaturedLightbox(item)"
                            >
                                <img
                                    v-if="item.media_type === 'image'"
                                    :src="item.media_url"
                                    :alt="item.title || 'Featured media'"
                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                />
                                <div
                                    v-else
                                    class="relative h-full w-full bg-black"
                                >
                                    <video
                                        :src="item.media_url"
                                        class="h-full w-full object-cover opacity-90"
                                        preload="metadata"
                                        muted
                                        playsinline
                                    />
                                    <div
                                        class="absolute inset-0 flex items-center justify-center"
                                    >
                                        <div
                                            class="flex h-8 w-8 items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-xs transition-transform group-hover:scale-110"
                                        >
                                            <Play
                                                class="ml-0.5 h-4 w-4 fill-white"
                                            />
                                        </div>
                                    </div>
                                    <span
                                        class="absolute top-1.5 left-1.5 flex items-center gap-1 rounded-md bg-black/70 px-1.5 py-0.5 text-[9px] font-bold text-white uppercase backdrop-blur-xs"
                                    >
                                        <Film class="h-2.5 w-2.5" />
                                        <span>Video</span>
                                    </span>
                                </div>

                                <!-- Title Banner -->
                                <div
                                    v-if="item.title"
                                    class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/80 via-black/40 to-transparent p-1.5 pt-4 text-center text-[10px] font-semibold text-white"
                                >
                                    {{ item.title }}
                                </div>

                                <!-- Owner Delete Quick Action -->
                                <button
                                    v-if="profile.is_self"
                                    type="button"
                                    data-test="delete-featured-button"
                                    class="absolute top-1.5 right-1.5 flex h-6 w-6 cursor-pointer items-center justify-center rounded-full bg-black/70 text-rose-300 opacity-0 transition group-hover:opacity-100 hover:bg-rose-500/20 hover:text-rose-200"
                                    title="Delete from featured"
                                    @click.stop="handleDeleteFeatured(item)"
                                >
                                    <Trash2 class="h-3 w-3" />
                                </button>
                            </div>

                            <!-- Plus Slot if < 6 -->
                            <div
                                v-if="
                                    profile.is_self && featuredList.length < 6
                                "
                                class="flex aspect-[3/4] cursor-pointer flex-col items-center justify-center gap-1 rounded-2xl border-2 border-dashed border-border/70 text-muted-foreground transition hover:border-brand/60 hover:bg-brand/5 hover:text-brand"
                                data-test="add-featured-slot"
                                @click="openAddFeaturedModal"
                            >
                                <Plus class="h-5 w-5 stroke-[2.5]" />
                                <span class="text-[10px] font-bold">Add</span>
                            </div>
                        </div>
                    </div>

                    <!-- Navigation Tabs -->
                    <div
                        class="border-t border-border/60 bg-muted/10 px-5 sm:px-8"
                    >
                        <div class="flex items-center gap-6">
                            <button
                                type="button"
                                class="cursor-pointer border-b-2 py-3.5 text-xs font-semibold transition-colors"
                                :class="
                                    activeTab === 'posts'
                                        ? 'border-brand text-brand'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                "
                                @click="activeTab = 'posts'"
                            >
                                Lounge Posts ({{ profile.posts.length }})
                            </button>

                            <button
                                v-if="profile.is_self && profile.saved_posts"
                                type="button"
                                class="cursor-pointer border-b-2 py-3.5 text-xs font-semibold transition-colors"
                                :class="
                                    activeTab === 'saved'
                                        ? 'border-amber-500 text-amber-600 dark:text-amber-400'
                                        : 'border-transparent text-muted-foreground hover:text-amber-600 dark:hover:text-amber-400'
                                "
                                @click="activeTab = 'saved'"
                            >
                                Saved ({{ profile.saved_posts.length }})
                            </button>

                            <button
                                type="button"
                                class="cursor-pointer border-b-2 py-3.5 text-xs font-semibold transition-colors"
                                :class="
                                    activeTab === 'about'
                                        ? 'border-brand text-brand'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                "
                                @click="activeTab = 'about'"
                            >
                                About
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Tab: Lounge Posts Grid/List -->
                <div v-if="!profile.is_masked && activeTab === 'posts'">
                    <div
                        v-if="profile.posts.length === 0"
                        class="rounded-3xl border border-dashed border-border/80 bg-card/50 p-12 text-center"
                    >
                        <MessageCircle
                            class="mx-auto h-10 w-10 text-muted-foreground/60"
                        />
                        <h3 class="mt-3 text-sm font-semibold text-foreground">
                            No Lounge Posts Yet
                        </h3>
                        <p
                            class="mx-auto mt-1 max-w-sm text-xs text-muted-foreground"
                        >
                            {{
                                profile.is_self
                                    ? "You haven't shared any posts in the community lounge yet. Head over to the lounge feed to join the conversation!"
                                    : `${profile.name} hasn't shared any non-anonymous posts in the lounge yet.`
                            }}
                        </p>
                        <div v-if="profile.is_self" class="mt-4">
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-brand px-4 py-2 text-xs font-semibold text-primary-foreground transition-all hover:opacity-90 active:scale-95"
                                @click="handleBackToLounge"
                            >
                                <span>Go to Lounge Feed</span>
                            </button>
                        </div>
                    </div>

                    <div v-else class="space-y-4">
                        <article
                            v-for="post in profile.posts"
                            :key="post.id"
                            class="rounded-2xl border border-border/70 bg-card p-4 shadow-sm transition-all hover:border-border"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs font-semibold text-foreground"
                                        >{{ profile.name }}</span
                                    >
                                    <span
                                        v-if="post.is_anonymous"
                                        class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                    >
                                        Posted Anonymously
                                    </span>
                                    <span
                                        class="text-[11px] text-muted-foreground"
                                        >· {{ post.created_at }}</span
                                    >
                                </div>
                                <span
                                    class="rounded-md border border-border/40 bg-muted/30 px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                >
                                    {{ post.section_name }}
                                </span>
                            </div>

                            <p
                                class="mt-3 text-xs leading-relaxed whitespace-pre-line text-foreground sm:text-sm"
                            >
                                {{ post.content }}
                            </p>

                            <!-- Attachment Preview -->
                            <div
                                v-if="
                                    post.attachment_url &&
                                    post.attachment_type === 'image'
                                "
                                class="mt-3 overflow-hidden rounded-xl border border-border/50 bg-muted/20"
                            >
                                <img
                                    :src="post.attachment_url"
                                    alt="Attachment"
                                    class="max-h-80 w-full object-cover"
                                    loading="lazy"
                                />
                            </div>

                            <div
                                v-else-if="
                                    post.attachment_url &&
                                    post.attachment_type === 'document'
                                "
                                class="mt-3 flex items-center gap-2.5 rounded-xl border border-border/50 bg-muted/30 p-2.5 text-xs"
                            >
                                <FileText class="h-4 w-4 text-brand" />
                                <span
                                    class="truncate font-medium text-foreground"
                                    >{{
                                        post.attachment_name || 'Document'
                                    }}</span
                                >
                                <a
                                    :href="post.attachment_url"
                                    target="_blank"
                                    download
                                    class="ml-auto text-brand hover:underline"
                                >
                                    Download
                                </a>
                            </div>

                            <!-- Post Interactions Row -->
                            <div
                                class="mt-4 flex items-center justify-between border-t border-border/40 pt-3 text-xs text-muted-foreground"
                            >
                                <div class="flex items-center gap-4">
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-1.5 transition-colors"
                                        :class="
                                            likedMap[String(post.id)]
                                                ? 'font-semibold text-rose-500'
                                                : 'hover:text-foreground'
                                        "
                                        @click="toggleLike(post.id)"
                                    >
                                        <Heart
                                            class="h-4 w-4 transition-transform group-hover:scale-115 active:scale-90"
                                            :class="[
                                                likedMap[String(post.id)]
                                                    ? 'fill-rose-500 text-rose-500'
                                                    : '',
                                                isHeartBouncing[String(post.id)]
                                                    ? 'scale-125 duration-150'
                                                    : '',
                                            ]"
                                        />
                                        <span>{{
                                            likesCountMap[String(post.id)] ??
                                            post.likes_count
                                        }}</span>
                                    </button>

                                    <div
                                        class="inline-flex items-center gap-1.5"
                                    >
                                        <MessageCircle class="h-4 w-4" />
                                        <span>{{ post.comments_count }}</span>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="cursor-pointer transition-colors"
                                    :class="
                                        bookmarkedMap[String(post.id)]
                                            ? 'text-brand'
                                            : 'hover:text-foreground'
                                    "
                                    @click="toggleBookmark(post.id)"
                                >
                                    <Bookmark
                                        class="h-4 w-4 transition-transform group-hover:scale-110 active:scale-90"
                                        :class="
                                            bookmarkedMap[String(post.id)]
                                                ? 'fill-brand text-brand'
                                                : ''
                                        "
                                    />
                                </button>
                            </div>
                        </article>
                    </div>
                </div>

                <!-- Tab: Saved Posts (Self Only) -->
                <div
                    v-else-if="
                        !profile.is_masked &&
                        activeTab === 'saved' &&
                        profile.is_self
                    "
                >
                    <div
                        v-if="
                            !profile.saved_posts ||
                            profile.saved_posts.length === 0
                        "
                        class="rounded-3xl border border-dashed border-border/80 bg-card/50 p-12 text-center"
                    >
                        <Bookmark
                            class="mx-auto h-10 w-10 text-muted-foreground/60"
                        />
                        <h3 class="mt-3 text-sm font-semibold text-foreground">
                            No Bookmarked Posts
                        </h3>
                        <p
                            class="mx-auto mt-1 max-w-sm text-xs text-muted-foreground"
                        >
                            You haven't saved any lounge posts yet. Click the
                            bookmark icon on posts in the feed to save them
                            here.
                        </p>
                    </div>

                    <div v-else class="space-y-4">
                        <article
                            v-for="post in profile.saved_posts"
                            :key="'saved-' + post.id"
                            class="rounded-2xl border border-border/70 bg-card p-4 shadow-sm"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span
                                        class="text-xs font-semibold text-foreground"
                                        >{{ post.author_name }}</span
                                    >
                                    <span
                                        class="text-[11px] text-muted-foreground"
                                        >· {{ post.created_at }}</span
                                    >
                                </div>
                                <span
                                    class="rounded-md border border-border/40 bg-muted/30 px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                                >
                                    {{ post.section_name }}
                                </span>
                            </div>

                            <p
                                class="mt-3 text-xs leading-relaxed whitespace-pre-line text-foreground sm:text-sm"
                            >
                                {{ post.content }}
                            </p>

                            <div
                                class="mt-4 flex items-center justify-between border-t border-border/40 pt-3 text-xs text-muted-foreground"
                            >
                                <div class="flex items-center gap-4">
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <Heart
                                            class="h-3.5 w-3.5 text-rose-500"
                                        />
                                        <span>{{ post.likes_count }}</span>
                                    </span>
                                    <span
                                        class="inline-flex items-center gap-1"
                                    >
                                        <MessageCircle class="h-3.5 w-3.5" />
                                        <span>{{ post.comments_count }}</span>
                                    </span>
                                </div>
                                <span class="text-[11px] text-brand"
                                    >Saved in Lounge</span
                                >
                            </div>
                        </article>
                    </div>
                </div>

                <!-- Tab: About -->
                <div v-else-if="!profile.is_masked && activeTab === 'about'">
                    <div
                        class="space-y-5 rounded-3xl border border-border/70 bg-card p-6 shadow-sm"
                    >
                        <div>
                            <h3 class="text-sm font-semibold text-foreground">
                                Lounge Presence
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Current activity:
                                {{ profile.presence_activity }}. Presence
                                indicators update seamlessly via background
                                memory-cached polling.
                            </p>
                        </div>

                        <div class="border-t border-border/50 pt-4">
                            <h3 class="text-sm font-semibold text-foreground">
                                Classroom Section
                            </h3>
                            <p class="mt-1 text-xs text-muted-foreground">
                                Enrolled in {{ profile.section_name }}. Student
                                feeds prioritize discussions and questions from
                                peers within the same section.
                            </p>
                        </div>

                        <div class="border-t border-border/50 pt-4">
                            <h3 class="text-sm font-semibold text-foreground">
                                Community Interactions
                            </h3>
                            <div
                                class="mt-2 grid grid-cols-2 gap-3 text-xs sm:grid-cols-3"
                            >
                                <div
                                    class="rounded-xl border border-border/50 bg-muted/20 p-3"
                                >
                                    <span class="text-muted-foreground"
                                        >Posts</span
                                    >
                                    <p
                                        class="text-base font-bold text-foreground"
                                    >
                                        {{ profile.posts_count }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-xl border border-border/50 bg-muted/20 p-3"
                                >
                                    <span class="text-muted-foreground"
                                        >Likes Received</span
                                    >
                                    <p
                                        class="text-base font-bold text-foreground"
                                    >
                                        {{ profile.likes_received }}
                                    </p>
                                </div>
                                <div
                                    class="rounded-xl border border-border/50 bg-muted/20 p-3"
                                >
                                    <span class="text-muted-foreground"
                                        >Followers</span
                                    >
                                    <p
                                        class="text-base font-bold text-foreground"
                                    >
                                        {{ followersCountState }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Curtain Wipe Overlay -->
        <CurtainWipeOverlay
            ref="curtainRef"
            title="Community Lounge"
            subtitle="Returning to lounge feed..."
            expected-mount-direction="to_community"
        />

        <!-- ═══ Add Featured Modal ═══ -->
        <div
            v-if="isAddFeaturedModalOpen"
            class="fixed inset-0 z-[150] flex items-center justify-center p-4"
            data-test="add-featured-modal"
        >
            <div
                class="fixed inset-0 bg-black/70 backdrop-blur-xs transition-opacity"
                @click="closeAddFeaturedModal"
            />
            <div
                class="relative z-[160] w-full max-w-md overflow-hidden rounded-3xl border border-border/70 bg-card p-6 shadow-2xl"
            >
                <div
                    class="mb-4 flex items-center justify-between border-b border-border/50 pb-3"
                >
                    <div class="flex items-center gap-2">
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-xl bg-brand/15 text-brand"
                        >
                            <Sparkles class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-foreground">
                                Add to Featured
                            </h2>
                            <p class="text-[11px] text-muted-foreground">
                                Permanent showcase on your lounge profile
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground"
                        @click="closeAddFeaturedModal"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Source Tabs: Upload New vs Story Archive -->
                <div
                    class="mb-3 flex items-center gap-4 border-b border-border/50 text-xs font-semibold"
                >
                    <button
                        type="button"
                        class="cursor-pointer border-b-2 py-2 transition-colors"
                        :class="
                            addFeaturedSource === 'upload'
                                ? 'border-brand text-brand'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        data-test="featured-source-upload"
                        @click="addFeaturedSource = 'upload'"
                    >
                        Upload New
                    </button>
                    <button
                        type="button"
                        class="cursor-pointer border-b-2 py-2 transition-colors"
                        :class="
                            addFeaturedSource === 'archive'
                                ? 'border-brand text-brand'
                                : 'border-transparent text-muted-foreground hover:text-foreground'
                        "
                        data-test="featured-source-archive"
                        @click="
                            addFeaturedSource = 'archive';
                            fetchStoryArchive();
                        "
                    >
                        Story Archive (24h+)
                    </button>
                </div>

                <div class="space-y-4">
                    <input
                        ref="featuredFileInputRef"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
                        class="hidden"
                        @change="handleFeaturedFileChange"
                    />

                    <!-- Tab 1: Upload Source -->
                    <template v-if="addFeaturedSource === 'upload'">
                        <!-- Select Zone -->
                        <div
                            v-if="!featuredMediaPreview"
                            class="group flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed border-border/80 bg-muted/20 p-8 text-center transition-all hover:border-brand/60 hover:bg-brand/5"
                            @click="featuredFileInputRef?.click()"
                        >
                            <div
                                class="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground transition-colors group-hover:bg-brand/20 group-hover:text-brand"
                            >
                                <UploadCloud class="h-6 w-6" />
                            </div>
                            <div>
                                <p
                                    class="text-xs font-semibold text-foreground"
                                >
                                    Choose a photo or video
                                </p>
                                <p
                                    class="mt-0.5 text-[11px] text-muted-foreground"
                                >
                                    Images up to 10MB · MP4/WebM up to 30MB
                                </p>
                            </div>
                        </div>

                        <!-- Preview -->
                        <div
                            v-else
                            class="relative mx-auto flex aspect-[3/4] max-h-64 w-full items-center justify-center overflow-hidden rounded-2xl border border-border/70 bg-black"
                        >
                            <img
                                v-if="featuredMediaType === 'image'"
                                :src="featuredMediaPreview"
                                alt="Featured preview"
                                class="h-full w-full object-contain"
                            />
                            <video
                                v-else
                                :src="featuredMediaPreview"
                                class="h-full w-full object-contain"
                                controls
                                playsinline
                            />

                            <button
                                type="button"
                                class="absolute top-2 right-2 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-black/70 text-white hover:bg-black"
                                @click="
                                    featuredMediaPreview = null;
                                    featuredMediaFile = null;
                                    featuredUploadError = null;
                                "
                            >
                                <X class="h-3.5 w-3.5" />
                            </button>
                        </div>
                    </template>

                    <!-- Tab 2: Story Archive Source -->
                    <template v-else>
                        <div
                            v-if="isLoadingArchive"
                            class="flex items-center justify-center p-8 text-xs text-muted-foreground"
                        >
                            <Loader2 class="mr-2 h-5 w-5 animate-spin" />
                            <span>Loading your stories...</span>
                        </div>

                        <div
                            v-else-if="archivedStories.length === 0"
                            class="rounded-2xl border border-dashed border-border/80 bg-muted/20 p-8 text-center"
                        >
                            <Clock
                                class="mx-auto h-8 w-8 text-muted-foreground/60"
                            />
                            <p
                                class="mt-2 text-xs font-semibold text-foreground"
                            >
                                No stories in your archive yet
                            </p>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                Stories you post are automatically preserved
                                here even after their 24h expiration.
                            </p>
                        </div>

                        <div v-else class="max-h-64 overflow-y-auto pr-1">
                            <p class="mb-2 text-[11px] text-muted-foreground">
                                Select an active or expired story to pin to your
                                featured highlights:
                            </p>
                            <div class="grid grid-cols-3 gap-2">
                                <div
                                    v-for="story in archivedStories"
                                    :key="story.id"
                                    class="group relative aspect-[3/4] cursor-pointer overflow-hidden rounded-xl border bg-black transition-all"
                                    :class="
                                        selectedArchivedStory?.id === story.id
                                            ? 'border-brand ring-2 ring-brand'
                                            : 'border-border/60 hover:border-border'
                                    "
                                    :data-test="`archive-story-${story.id}`"
                                    @click="selectStoryFromArchive(story)"
                                >
                                    <img
                                        v-if="story.media_type === 'image'"
                                        :src="story.media_url"
                                        alt="Archived story"
                                        class="h-full w-full object-cover"
                                    />
                                    <div v-else class="relative h-full w-full">
                                        <video
                                            :src="story.media_url"
                                            class="h-full w-full object-cover"
                                            muted
                                        />
                                        <div
                                            class="absolute inset-0 flex items-center justify-center bg-black/30"
                                        >
                                            <Play
                                                class="h-4 w-4 fill-white text-white"
                                            />
                                        </div>
                                    </div>

                                    <span
                                        class="absolute top-1 left-1 rounded bg-black/70 px-1 py-0.5 text-[9px] font-bold text-white backdrop-blur-xs"
                                    >
                                        {{
                                            story.is_expired
                                                ? 'Expired'
                                                : 'Active'
                                        }}
                                    </span>

                                    <div
                                        v-if="
                                            selectedArchivedStory?.id ===
                                            story.id
                                        "
                                        class="absolute top-1 right-1 flex h-5 w-5 items-center justify-center rounded-full bg-brand text-primary-foreground shadow-sm"
                                    >
                                        <Check class="h-3 w-3 stroke-[3]" />
                                    </div>

                                    <div
                                        v-if="story.caption"
                                        class="absolute inset-x-0 bottom-0 truncate bg-gradient-to-t from-black/80 to-transparent p-1 text-[9px] text-white"
                                    >
                                        {{ story.caption }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>

                    <!-- Title -->
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-semibold text-foreground"
                        >
                            Title / Label (optional)
                        </label>
                        <input
                            v-model="featuredTitle"
                            type="text"
                            maxlength="100"
                            placeholder="e.g. Campus Project, Lab Setup"
                            class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none"
                        />
                    </div>

                    <!-- Error -->
                    <div
                        v-if="featuredUploadError"
                        class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-2.5 text-xs font-medium text-rose-600 dark:text-rose-400"
                    >
                        {{ featuredUploadError }}
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="cursor-pointer rounded-xl border border-border/70 px-4 py-2 text-xs font-semibold text-foreground hover:bg-muted"
                            @click="closeAddFeaturedModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            :disabled="
                                (addFeaturedSource === 'upload'
                                    ? !featuredMediaFile
                                    : !selectedArchivedStory) ||
                                isSubmittingFeatured
                            "
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-brand px-5 py-2 text-xs font-semibold text-primary-foreground transition-all hover:opacity-90 disabled:opacity-50"
                            data-test="confirm-add-featured-button"
                            @click="handleUploadFeatured"
                        >
                            <Loader2
                                v-if="isSubmittingFeatured"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <span>{{
                                isSubmittingFeatured
                                    ? 'Pinning...'
                                    : 'Save Featured'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Featured Lightbox Modal ═══ -->
        <div
            v-if="isFeaturedLightboxOpen && activeFeaturedItem"
            class="fixed inset-0 z-[170] flex items-center justify-center bg-black/90 p-4"
            data-test="featured-lightbox"
        >
            <div class="fixed inset-0" @click="closeFeaturedLightbox" />
            <div
                class="relative z-[180] flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-border/70 bg-zinc-950 text-white shadow-2xl"
            >
                <div
                    class="flex items-center justify-between border-b border-white/10 px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold">{{
                            activeFeaturedItem.title || 'Featured Highlight'
                        }}</span>
                        <span class="text-[10px] text-white/60"
                            >· {{ activeFeaturedItem.created_at }}</span
                        >
                    </div>

                    <div class="flex items-center gap-2">
                        <button
                            v-if="profile.is_self"
                            type="button"
                            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-white/10 text-rose-300 transition hover:bg-rose-500/20 hover:text-rose-200"
                            title="Delete"
                            @click="handleDeleteFeatured(activeFeaturedItem)"
                        >
                            <Trash2 class="h-3.5 w-3.5" />
                        </button>
                        <button
                            type="button"
                            class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-white/10 text-white transition hover:bg-white/20"
                            @click="closeFeaturedLightbox"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <div
                    class="relative flex min-h-[300px] flex-1 items-center justify-center bg-black p-2"
                >
                    <img
                        v-if="activeFeaturedItem.media_type === 'image'"
                        :src="activeFeaturedItem.media_url"
                        :alt="activeFeaturedItem.title || 'Featured'"
                        class="max-h-[70vh] w-auto max-w-full object-contain"
                    />
                    <video
                        v-else
                        :src="activeFeaturedItem.media_url"
                        class="max-h-[70vh] w-auto max-w-full object-contain"
                        controls
                        autoplay
                        playsinline
                    />
                </div>
            </div>
        </div>
    </AppLayout>
</template>
