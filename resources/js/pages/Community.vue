<script setup lang="ts">
import { Head, router, useForm, usePage, usePoll } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    Bookmark,
    Check,
    Clock,
    Code2,
    Download,
    ExternalLink,
    EyeOff,
    FileText,
    Flame,
    GraduationCap,
    Heart,
    ImageIcon,
    Laugh,
    Loader2,
    MessageCircle,
    MoreHorizontal,
    PartyPopper,
    Pause,
    Pencil,
    Music,
    Play,
    Plus,
    LogOut,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    CornerDownRight,
    Eye,
    Film,
    RefreshCw,
    Search,
    Send,
    Shield,
    ShieldCheck,
    Sparkles,
    ThumbsUp,
    Trash2,
    UploadCloud,
    UserCheck,
    UserPlus,
    Users,
    X,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import CurtainWipeOverlay from '@/components/common/CurtainWipeOverlay.vue';
import CommunityInstallPrompt from '@/components/community/CommunityInstallPrompt.vue';
import LogoutConfirmationModal from '@/components/LogoutConfirmationModal.vue';
import { useCommunityPwa } from '@/composables/useCommunityPwa';
import AppLayout from '@/layouts/AppLayout.vue';
import { compressImage } from '@/lib/image-compression';
import { like as likeMedia } from '@/routes/community/media';
import { store as storePostComment } from '@/routes/community/posts/comments';
import type { BreadcrumbItem } from '@/types';

export interface ClassmatePresence {
    id: number | string;
    public_id?: string;
    name: string;
    avatar?: string | null;
    section_name?: string | null;
    status: 'online' | 'idle' | 'offline';
    role?: string;
    activity?: string;
    isCurrentUser?: boolean;
}

export interface PostComment {
    id: number | string;
    parent_id?: number | string | null;
    content: string;
    created_at: string;
    author_id?: string | number;
    author_public_id?: string | null;
    author_name: string;
    author_handle: string;
    author_avatar?: string | null;
    is_masked?: boolean;
    section_name?: string;
    likes_count?: number;
    is_liked?: boolean;
    replies?: PostComment[];
}

export interface PostAuthor {
    id: string;
    public_id?: string | null;
    name: string;
    handle: string;
    avatar?: string | null;
    is_same_section: boolean;
    is_masked: boolean;
    is_following: boolean;
    section_name?: string;
}

export interface PostMediaItem {
    id: number | string;
    url: string;
    type: 'image' | 'video' | string;
    name?: string | null;
    size?: number | null;
    likes_count?: number;
    is_liked?: boolean;
    comments?: PostComment[];
    comments_count?: number;
}

export interface PostMusicTrack {
    id: number;
    title: string;
    artist: string;
    duration?: number;
    audio_url: string;
    audioUrl?: string;
    cover_image_url?: string | null;
    coverImageUrl?: string | null;
}

export interface CommunityFeedPost {
    id: number | string;
    content: string;
    created_at: string;
    attachment_url?: string | null;
    attachment_type?: string | null;
    attachment_name?: string | null;
    attachment_size?: number | null;
    media?: PostMediaItem[];
    music?: PostMusicTrack | null;
    likes_count: number;
    comments_count: number;
    is_liked: boolean;
    is_bookmarked: boolean;
    author: PostAuthor;
    comments: PostComment[];
}

export interface TrendingTopic {
    tag: string;
    name: string;
    count: number;
}

interface Workspace {
    id: number;
    name: string;
    public_id?: string;
}

export interface StoryItem {
    id: number;
    media_url: string;
    media_type: 'image' | 'video';
    duration_seconds: number;
    caption?: string | null;
    views_count: number;
    reactions_count?: number;
    user_reaction?: string | null;
    is_reacted?: boolean;
    created_at: string;
    is_self?: boolean;
}

export interface UserStoryGroup {
    user_id: number;
    public_id?: string | null;
    name: string;
    handle: string;
    avatar?: string | null;
    is_self: boolean;
    is_masked: boolean;
    stories: StoryItem[];
}

export interface StoryTrayItem {
    id: string;
    name: string;
    username: string;
    initials: string;
    avatar?: string | null;
    isUser: boolean;
    hasStory: boolean;
    storyGroup?: UserStoryGroup | null;
    status: 'online' | 'idle' | 'offline';
    public_id?: string;
}

export interface AvailableSection {
    id: number;
    name: string;
}

interface Props {
    workspace?: Workspace | null;
    sectionName?: string;
    currentSectionId?: number | null;
    availableSections?: AvailableSection[];
    onlineClassmates?: ClassmatePresence[];
    onlineCount?: number;
    totalEnrolledCount?: number;
    posts?: CommunityFeedPost[];
    hiddenOwnPosts?: CommunityFeedPost[];
    musicTracks?: PostMusicTrack[];
    trendingTopics?: TrendingTopic[];
    currentTab?: 'all' | 'section' | 'saved';
    stories?: UserStoryGroup[];
    isFirstTimeJoin?: boolean;
    isSectionOnly?: boolean;
    feedScope?: 'workspace' | 'section';
    allowsAnonymous?: boolean;
    storiesEnabled?: boolean;
    musicEnabled?: boolean;
    mediaUploadsEnabled?: boolean;
    fileAttachmentsEnabled?: boolean;
    pwaEnabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    storiesEnabled: true,
    allowsAnonymous: true,
    musicEnabled: true,
    mediaUploadsEnabled: true,
    fileAttachmentsEnabled: true,
    pwaEnabled: true,
    isSectionOnly: false,
    feedScope: 'workspace',
});

const page = usePage();
const currentUser = computed(() => (page.props as any)?.auth?.user);
const currentUserName = computed(() => currentUser.value?.name || 'You');
const currentUserHandle = computed(() => {
    if (!currentUser.value?.name) {
        return 'you';
    }
    return strToHandle(currentUser.value.name);
});
const currentUserInitials = computed(() => getInitials(currentUserName.value));

function strToHandle(name: string): string {
    return strToClean(name).toLowerCase();
}

function strToClean(s: string): string {
    return s.replace(/[^a-zA-Z0-9]/g, '.');
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Community Lounge', href: '/community' },
];

const { isStandalone } = useCommunityPwa();
const showLogoutModal = ref(false);

const curtainRef = ref<InstanceType<typeof CurtainWipeOverlay> | null>(null);

const handleReturn = () => {
    if (curtainRef.value) {
        curtainRef.value.wipeTo('/dashboard', 'to_dashboard');
    } else {
        router.visit('/dashboard', { preserveScroll: false });
    }
};

// ─── First-Time Welcome Modal State ───────────────────────────────────────────
const shouldShowWelcome = (): boolean => {
    if (!props.isFirstTimeJoin) return false;
    try {
        if (typeof window !== 'undefined' && window.localStorage) {
            const userId =
                currentUser.value?.id ||
                currentUser.value?.public_id ||
                'default';
            return !window.localStorage.getItem(
                `community_lounge_welcome_seen_${userId}`,
            );
        }
    } catch {
        // fallback
    }
    return Boolean(props.isFirstTimeJoin);
};

const isWelcomeModalOpen = ref(shouldShowWelcome());

const dismissWelcomeModal = () => {
    isWelcomeModalOpen.value = false;
    try {
        if (typeof window !== 'undefined' && window.localStorage) {
            const userId =
                currentUser.value?.id ||
                currentUser.value?.public_id ||
                'default';
            window.localStorage.setItem(
                `community_lounge_welcome_seen_${userId}`,
                'true',
            );
        }
    } catch {
        // ignore
    }
};

// ─── Active Tab Filtering & Client-Side Tab Cache ────────────────────────────
const initialTab =
    props.isSectionOnly && props.currentTab === 'section'
        ? 'all'
        : (props.currentTab ?? 'all');
const activeTab = ref<'all' | 'section' | 'saved'>(initialTab);
const tabPostsCache = ref<Record<string, CommunityFeedPost[]>>({
    [initialTab]: [...(props.posts ?? [])],
});
const feedScrollRef = ref<HTMLElement | null>(null);
const tabScrollPositions = ref<Record<string, number>>({
    all: 0,
    section: 0,
    saved: 0,
});

const handleTabChange = (tab: 'all' | 'section' | 'saved') => {
    if (activeTab.value === tab) {
        return;
    }

    if (feedScrollRef.value) {
        tabScrollPositions.value[activeTab.value] =
            feedScrollRef.value.scrollTop;
    }

    // Cache current tab state
    tabPostsCache.value[activeTab.value] = [...localPosts.value];
    activeTab.value = tab;

    // 1. Instant optimistic feed swap
    if (tabPostsCache.value[tab]) {
        localPosts.value = [...tabPostsCache.value[tab]];
    } else {
        const allPosts = tabPostsCache.value['all'] ?? localPosts.value;
        if (tab === 'section') {
            localPosts.value = allPosts.filter((p) =>
                Boolean(p.author?.is_same_section),
            );
        } else if (tab === 'saved') {
            localPosts.value = allPosts.filter((p) =>
                Boolean(bookmarkedMap.value[String(p.id)] ?? p.is_bookmarked),
            );
        }
    }

    nextTick(() => {
        if (feedScrollRef.value) {
            feedScrollRef.value.scrollTop = tabScrollPositions.value[tab] ?? 0;
        }
    });

    // 2. Background Inertia router visit to reconcile with server
    router.visit(`/community?tab=${tab}`, {
        preserveState: true,
        preserveScroll: true,
        only: ['posts', 'currentTab'],
        onSuccess: (page) => {
            const serverPosts = (page.props as any)?.posts;
            if (Array.isArray(serverPosts)) {
                tabPostsCache.value[tab] = [...serverPosts];
                if (activeTab.value === tab) {
                    localPosts.value = [...serverPosts];
                }
            }
        },
    });
};

// ─── Section Online Presence & Mockup State ──────────────────────────────────
const defaultClassmates: ClassmatePresence[] = [
    {
        id: 1,
        public_id: 'default-alex',
        name: 'You (Alex)',
        status: 'online',
        role: 'You',
        activity: 'Active in Lounge',
        isCurrentUser: true,
    },
    {
        id: 2,
        public_id: 'default-sarah',
        name: 'Sarah Chen',
        status: 'online',
        role: 'Class Officer',
        activity: 'Reviewing Midterms',
    },
    {
        id: 3,
        public_id: 'default-marcus',
        name: 'Marcus Vance',
        status: 'online',
        activity: 'Networking Assignment #3',
    },
    {
        id: 4,
        public_id: 'default-chloe',
        name: 'Chloe Santos',
        status: 'idle',
        role: 'Section Rep',
        activity: 'In Library Hub (idle 12m)',
    },
    {
        id: 5,
        public_id: 'default-devon',
        name: 'Devon Ray',
        status: 'idle',
        activity: 'Studying Dijkstra algorithm',
    },
    {
        id: 6,
        public_id: 'default-elena',
        name: 'Elena Rostova',
        status: 'offline',
        activity: 'Last seen 2h ago',
    },
];

const sectionDisplayName = computed(
    () => props.sectionName?.trim() || 'BSIT 3-A',
);

const selectedSectionId = ref<number | null>(
    props.currentSectionId ?? props.availableSections?.[0]?.id ?? null,
);

const isSectionDropdownOpen = ref(false);
const isMobileSectionDropdownOpen = ref(false);
const sectionDropdownRef = ref<HTMLElement | null>(null);

watch(
    () => props.currentSectionId,
    (newId) => {
        if (newId !== undefined && newId !== null) {
            selectedSectionId.value = newId;
            composerSectionId.value = newId;
            composerForm.section_id = newId;
        }
    },
);

const copiedPostId = ref<number | string | null>(null);

const handleSharePost = async (post: CommunityFeedPost) => {
    try {
        const url = `${window.location.origin}/community#post-${post.id}`;
        if (navigator.clipboard?.writeText) {
            await navigator.clipboard.writeText(url);
        }
        copiedPostId.value = post.id;
        setTimeout(() => {
            if (copiedPostId.value === post.id) {
                copiedPostId.value = null;
            }
        }, 2000);
    } catch {
        // Fallback
    }
};

const selectSection = (sectionId: number) => {
    isSectionDropdownOpen.value = false;
    isMobileSectionDropdownOpen.value = false;
    isInlineSectionDropdownOpen.value = false;
    isComposerSectionDropdownOpen.value = false;
    composerSectionId.value = sectionId;
    composerForm.section_id = sectionId;
    if (
        selectedSectionId.value === sectionId &&
        (props.currentSectionId === undefined ||
            props.currentSectionId === sectionId)
    ) {
        return;
    }

    selectedSectionId.value = sectionId;
    tabPostsCache.value = {};
    isRefreshingPresence.value = true;

    if (typeof router.post === 'function') {
        router.post(
            '/community/section',
            { section_id: sectionId, tab: activeTab.value },
            {
                preserveState: true,
                preserveScroll: true,
                only: [
                    'onlineClassmates',
                    'onlineCount',
                    'totalEnrolledCount',
                    'sectionName',
                    'currentSectionId',
                    'stories',
                    'posts',
                    'hiddenOwnPosts',
                    'trendingTopics',
                ],
                onSuccess: (page) => {
                    const serverPosts = (page.props as any)?.posts;
                    if (Array.isArray(serverPosts)) {
                        localPosts.value = [...serverPosts];
                        tabPostsCache.value[activeTab.value] = [...serverPosts];
                    }
                    const serverHidden = (page.props as any)?.hiddenOwnPosts;
                    if (Array.isArray(serverHidden)) {
                        localHiddenOwnPosts.value = [...serverHidden];
                    }
                },
                onFinish: () => {
                    isRefreshingPresence.value = false;
                },
            },
        );
    }
};

onMounted(() => {
    if (
        typeof window !== 'undefined' &&
        window.location.search.includes('section_id=')
    ) {
        window.history.replaceState({}, '', window.location.pathname);
    }
});

const classmatesList = computed<ClassmatePresence[]>(() =>
    props.onlineClassmates !== undefined
        ? props.onlineClassmates
        : defaultClassmates,
);

const onlineCount = computed(
    () =>
        props.onlineCount ??
        classmatesList.value.filter((c) => c.status === 'online').length,
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
            data: selectedSectionId.value
                ? { section_id: selectedSectionId.value }
                : undefined,
            only: ['onlineClassmates', 'onlineCount', 'totalEnrolledCount'],
            onFinish: () => {
                isRefreshingPresence.value = false;
            },
        });
    }
    setTimeout(() => {
        isRefreshingPresence.value = false;
    }, 600);
};

// Polling: refresh presence every 15s without full page reload.
// Server caches data in RAM (15s TTL) and throttles DB writes to once per 3m.
try {
    if (typeof usePoll === 'function') {
        usePoll(15000, {
            only: ['onlineClassmates', 'onlineCount'],
        });
    }
} catch {
    // Ignore in non-Inertia or test environments
}

// ─── Dynamic Feed Posts ─────────────────────────────────────────────────────
const localPosts = ref<CommunityFeedPost[]>([...(props.posts ?? [])]);
const hiddenPostIds = ref<Set<string>>(new Set());
const localHiddenOwnPosts = ref<CommunityFeedPost[]>([
    ...(props.hiddenOwnPosts ?? []),
]);
const isHiddenPostsModalOpen = ref(false);

watch(
    () => props.posts,
    (newPosts) => {
        const list = [...(newPosts ?? [])];
        localPosts.value = list;
        tabPostsCache.value[activeTab.value] = list;
    },
    { immediate: true },
);

watch(
    () => props.hiddenOwnPosts,
    (newHidden) => {
        localHiddenOwnPosts.value = [...(newHidden ?? [])];
    },
    { immediate: true },
);

const displayPosts = computed<CommunityFeedPost[]>(() => {
    return localPosts.value.filter(
        (post) => !hiddenPostIds.value.has(String(post.id)),
    );
});

const hidePost = async (post: CommunityFeedPost) => {
    activePostMenuId.value = null;
    const key = String(post.id);
    const next = new Set(hiddenPostIds.value);
    next.add(key);
    hiddenPostIds.value = next;

    for (const tabKey of Object.keys(tabPostsCache.value)) {
        tabPostsCache.value[tabKey] = tabPostsCache.value[tabKey].filter(
            (p) => String(p.id) !== key,
        );
    }

    if (
        canManagePost(post) &&
        !localHiddenOwnPosts.value.some((p) => String(p.id) === key)
    ) {
        localHiddenOwnPosts.value = [post, ...localHiddenOwnPosts.value];
    }

    try {
        await axios.post(
            `/community/posts/${post.id}/hide`,
            {},
            { headers: { Accept: 'application/json' } },
        );
    } catch {
        // Ignore network errors in test/offline mode; optimistic state remains
    }
};

const unhidePost = async (post: CommunityFeedPost) => {
    const key = String(post.id);
    const next = new Set(hiddenPostIds.value);
    next.delete(key);
    hiddenPostIds.value = next;

    localHiddenOwnPosts.value = localHiddenOwnPosts.value.filter(
        (p) => String(p.id) !== key,
    );

    if (!localPosts.value.some((p) => String(p.id) === key)) {
        localPosts.value = [post, ...localPosts.value];
    }

    if (localHiddenOwnPosts.value.length === 0) {
        isHiddenPostsModalOpen.value = false;
    }

    try {
        await axios.post(
            `/community/posts/${post.id}/hide`,
            {},
            { headers: { Accept: 'application/json' } },
        );
    } catch {
        // Ignore network errors in test/offline mode
    }
};

// ─── Post & Comment Ownership / Moderation Helpers ──────────────────────────
const canManagePost = (post: CommunityFeedPost): boolean => {
    if (!currentUser.value) return false;
    if (Boolean(currentUser.value.is_admin)) return true;
    return (
        post.author?.id !== undefined &&
        post.author?.id !== null &&
        String(post.author.id) === String(currentUser.value.id)
    );
};

const canEditComment = (comment: PostComment): boolean => {
    if (!currentUser.value) return false;
    if (Boolean(currentUser.value.is_admin)) return true;
    return (
        comment.author_id !== undefined &&
        comment.author_id !== null &&
        comment.author_id !== '' &&
        String(comment.author_id) === String(currentUser.value.id)
    );
};

const canDeleteComment = (
    post: CommunityFeedPost,
    comment: PostComment,
): boolean => {
    if (!currentUser.value) return false;
    if (Boolean(currentUser.value.is_admin)) return true;

    const isCommentAuthor =
        comment.author_id !== undefined &&
        comment.author_id !== null &&
        comment.author_id !== '' &&
        String(comment.author_id) === String(currentUser.value.id);

    const isPostOwner =
        post.author?.id !== undefined &&
        post.author?.id !== null &&
        post.author?.id !== '' &&
        String(post.author.id) === String(currentUser.value.id);

    return isCommentAuthor || isPostOwner;
};

// ─── Post Actions & Management (Edit / Delete) ──────────────────────────────
const activePostMenuId = ref<string | null>(null);
const editingPostId = ref<string | null>(null);
const editingPostContent = ref<string>('');
const editingPostIsAnonymous = ref<boolean>(false);
const isSavingPost = ref<boolean>(false);
const postToDelete = ref<CommunityFeedPost | null>(null);
const isDeletingPost = ref<boolean>(false);

const togglePostMenu = (postId: number | string) => {
    const key = String(postId);
    activePostMenuId.value = activePostMenuId.value === key ? null : key;
};

const startEditPost = (post: CommunityFeedPost) => {
    activePostMenuId.value = null;
    editingPostId.value = String(post.id);
    editingPostContent.value = post.content;
    editingPostIsAnonymous.value = Boolean(post.author?.is_masked);
};

const cancelEditPost = () => {
    editingPostId.value = null;
    editingPostContent.value = '';
    editingPostIsAnonymous.value = false;
};

const saveEditPost = async (post: CommunityFeedPost) => {
    const content = editingPostContent.value.trim();
    if (!content || isSavingPost.value) return;

    isSavingPost.value = true;
    try {
        const res = await axios.put(
            `/community/posts/${post.id}`,
            {
                content,
                is_anonymous: editingPostIsAnonymous.value,
            },
            {
                headers: { Accept: 'application/json' },
            },
        );
        if (res.data?.post) {
            post.content = res.data.post.content;
            if (typeof res.data.post.is_anonymous === 'boolean') {
                post.author.is_masked = res.data.post.is_anonymous;
            }
        } else {
            post.content = content;
        }
        cancelEditPost();
    } catch {
        router.put(
            `/community/posts/${post.id}`,
            {
                content,
                is_anonymous: editingPostIsAnonymous.value,
            },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    post.content = content;
                    cancelEditPost();
                },
            },
        );
    } finally {
        isSavingPost.value = false;
    }
};

const confirmDeletePost = (post: CommunityFeedPost) => {
    activePostMenuId.value = null;
    postToDelete.value = post;
};

const cancelDeletePost = () => {
    postToDelete.value = null;
};

const deletePost = async (post?: CommunityFeedPost | null) => {
    const target = post || postToDelete.value;
    if (!target || isDeletingPost.value) return;

    const removeTargetFromStateAndCache = () => {
        localPosts.value = localPosts.value.filter(
            (p) => String(p.id) !== String(target.id),
        );
        for (const tabKey of Object.keys(tabPostsCache.value)) {
            tabPostsCache.value[tabKey] = tabPostsCache.value[tabKey].filter(
                (p) => String(p.id) !== String(target.id),
            );
        }
    };

    isDeletingPost.value = true;
    try {
        await axios.delete(`/community/posts/${target.id}`, {
            headers: { Accept: 'application/json' },
        });
        removeTargetFromStateAndCache();
        postToDelete.value = null;
    } catch {
        router.delete(`/community/posts/${target.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                removeTargetFromStateAndCache();
                postToDelete.value = null;
            },
        });
    } finally {
        isDeletingPost.value = false;
    }
};

// ─── Comment & Reply Management (Edit / Delete) ─────────────────────────────
const editingCommentId = ref<string | null>(null);
const editingCommentContent = ref<string>('');
const isSavingComment = ref<boolean>(false);
const isDeletingComment = ref<Record<string, boolean>>({});

const startEditComment = (comment: PostComment) => {
    editingCommentId.value = String(comment.id);
    editingCommentContent.value = comment.content;
};

const cancelEditComment = () => {
    editingCommentId.value = null;
    editingCommentContent.value = '';
};

const saveEditComment = async (comment: PostComment) => {
    const content = editingCommentContent.value.trim();
    if (!content || isSavingComment.value) return;

    isSavingComment.value = true;
    try {
        const res = await axios.put(
            `/community/comments/${comment.id}`,
            { content },
            { headers: { Accept: 'application/json' } },
        );
        if (res.data?.comment?.content) {
            comment.content = res.data.comment.content;
        } else {
            comment.content = content;
        }
        cancelEditComment();
    } catch {
        router.put(
            `/community/comments/${comment.id}`,
            { content },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    comment.content = content;
                    cancelEditComment();
                },
            },
        );
    } finally {
        isSavingComment.value = false;
    }
};

const deleteComment = async (postId: number | string, comment: PostComment) => {
    const postKey = String(postId);
    const commentKey = String(comment.id);
    if (isDeletingComment.value[commentKey]) return;

    isDeletingComment.value[commentKey] = true;
    const repliesCount = comment.replies?.length ?? 0;
    const totalRemoved = 1 + repliesCount;

    try {
        await axios.delete(`/community/comments/${comment.id}`, {
            headers: { Accept: 'application/json' },
        });
        if (localCommentsMap.value[postKey]) {
            localCommentsMap.value[postKey] = localCommentsMap.value[
                postKey
            ].filter((c) => String(c.id) !== commentKey);
        }
        commentsCountMap.value[postKey] = Math.max(
            0,
            (commentsCountMap.value[postKey] ?? 0) - totalRemoved,
        );
    } catch {
        router.delete(`/community/comments/${comment.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (localCommentsMap.value[postKey]) {
                    localCommentsMap.value[postKey] = localCommentsMap.value[
                        postKey
                    ].filter((c) => String(c.id) !== commentKey);
                }
                commentsCountMap.value[postKey] = Math.max(
                    0,
                    (commentsCountMap.value[postKey] ?? 0) - totalRemoved,
                );
            },
        });
    } finally {
        delete isDeletingComment.value[commentKey];
    }
};

const deleteReply = async (
    postId: number | string,
    parentComment: PostComment,
    reply: PostComment,
) => {
    const postKey = String(postId);
    const replyKey = String(reply.id);
    if (isDeletingComment.value[replyKey]) return;

    isDeletingComment.value[replyKey] = true;
    try {
        await axios.delete(`/community/comments/${reply.id}`, {
            headers: { Accept: 'application/json' },
        });
        if (parentComment.replies) {
            parentComment.replies = parentComment.replies.filter(
                (r) => String(r.id) !== replyKey,
            );
        }
        commentsCountMap.value[postKey] = Math.max(
            0,
            (commentsCountMap.value[postKey] ?? 0) - 1,
        );
    } catch {
        router.delete(`/community/comments/${reply.id}`, {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (parentComment.replies) {
                    parentComment.replies = parentComment.replies.filter(
                        (r) => String(r.id) !== replyKey,
                    );
                }
                commentsCountMap.value[postKey] = Math.max(
                    0,
                    (commentsCountMap.value[postKey] ?? 0) - 1,
                );
            },
        });
    } finally {
        delete isDeletingComment.value[replyKey];
    }
};

interface CommentToDeleteTarget {
    postId: number | string;
    comment: PostComment;
    parentComment?: PostComment | null;
    isReply: boolean;
}

const commentToDelete = ref<CommentToDeleteTarget | null>(null);

const confirmDeleteComment = (
    postId: number | string,
    comment: PostComment,
) => {
    commentToDelete.value = {
        postId,
        comment,
        isReply: false,
    };
};

const confirmDeleteReply = (
    postId: number | string,
    parentComment: PostComment,
    reply: PostComment,
) => {
    commentToDelete.value = {
        postId,
        comment: reply,
        parentComment,
        isReply: true,
    };
};

const cancelDeleteComment = () => {
    commentToDelete.value = null;
};

const handleConfirmDeleteComment = async () => {
    if (!commentToDelete.value) return;
    const target = commentToDelete.value;
    if (target.isReply && target.parentComment) {
        await deleteReply(target.postId, target.parentComment, target.comment);
    } else {
        await deleteComment(target.postId, target.comment);
    }
    commentToDelete.value = null;
};

// ─── Interactive Social Likes & Bookmarks (Optimistic + Debounced) ───────────
const likedMap = ref<Record<string, boolean>>({});
const likesCountMap = ref<Record<string, number>>({});
const bookmarkedMap = ref<Record<string, boolean>>({});

// Server truth tracking for debouncing
const serverLikedMap = ref<Record<string, boolean>>({});
const serverBookmarkedMap = ref<Record<string, boolean>>({});
const likeDebounceTimers = ref<Record<string, ReturnType<typeof setTimeout>>>(
    {},
);
const bookmarkDebounceTimers = ref<
    Record<string, ReturnType<typeof setTimeout>>
>({});
const isHeartBouncing = ref<Record<string, boolean>>({});
const localCommentsMap = ref<Record<string, PostComment[]>>({});
const commentsCountMap = ref<Record<string, number>>({});

// ─── Comment & Reply Likes State ─────────────────────────────────────────────
const commentLikedMap = ref<Record<string, boolean>>({});
const commentLikesCountMap = ref<Record<string, number>>({});
const serverCommentLikedMap = ref<Record<string, boolean>>({});
const commentLikeDebounceTimers = ref<
    Record<string, ReturnType<typeof setTimeout>>
>({});
const isCommentHeartBouncing = ref<Record<string, boolean>>({});

const initCommentReactions = (comments?: PostComment[]) => {
    if (!comments) {
        return;
    }
    for (const c of comments) {
        const cKey = String(c.id);
        if (commentLikedMap.value[cKey] === undefined) {
            commentLikedMap.value[cKey] = Boolean(c.is_liked);
            commentLikesCountMap.value[cKey] = c.likes_count ?? 0;
            serverCommentLikedMap.value[cKey] = Boolean(c.is_liked);
        }
        if (c.replies?.length) {
            initCommentReactions(c.replies);
        }
    }
};

const isCommentLiked = (commentId: number | string) =>
    commentLikedMap.value[String(commentId)] ?? false;

const getCommentLikesCount = (commentId: number | string) =>
    commentLikesCountMap.value[String(commentId)] ?? 0;

const toggleCommentLike = (commentId: number | string) => {
    const key = String(commentId);
    const prevLiked = commentLikedMap.value[key] ?? false;
    const prevCount = commentLikesCountMap.value[key] ?? 0;
    const nextLiked = !prevLiked;

    // 1. Immediate optimistic client update (0ms visual latency)
    commentLikedMap.value[key] = nextLiked;
    commentLikesCountMap.value[key] = Math.max(
        0,
        prevCount + (nextLiked ? 1 : -1),
    );

    // 2. Tactile bounce animation
    if (nextLiked) {
        isCommentHeartBouncing.value[key] = true;
        setTimeout(() => {
            isCommentHeartBouncing.value[key] = false;
        }, 350);
    }

    // 3. Clear existing debounce timer
    if (commentLikeDebounceTimers.value[key]) {
        clearTimeout(commentLikeDebounceTimers.value[key]);
    }

    // 4. Debounce network sync by 350ms to prevent spamming server
    commentLikeDebounceTimers.value[key] = setTimeout(async () => {
        delete commentLikeDebounceTimers.value[key];

        if (commentLikedMap.value[key] === serverCommentLikedMap.value[key]) {
            return;
        }

        try {
            const res = await axios.post(
                `/community/comments/${commentId}/like`,
                {},
                { headers: { Accept: 'application/json' } },
            );
            if (res.data && typeof res.data.is_liked === 'boolean') {
                serverCommentLikedMap.value[key] = res.data.is_liked;
                commentLikedMap.value[key] = res.data.is_liked;
                if (typeof res.data.likes_count === 'number') {
                    commentLikesCountMap.value[key] = res.data.likes_count;
                }
            }
        } catch {
            // Rollback on network failure
            commentLikedMap.value[key] =
                serverCommentLikedMap.value[key] ?? prevLiked;
            commentLikesCountMap.value[key] = prevCount;
        }
    }, 350);
};

// ─── Post Media Item Likes State ─────────────────────────────────────────────
const mediaLikedMap = ref<Record<string, boolean>>({});
const mediaLikesCountMap = ref<Record<string, number>>({});
const serverMediaLikedMap = ref<Record<string, boolean>>({});
const mediaLikeDebounceTimers = ref<
    Record<string, ReturnType<typeof setTimeout>>
>({});

const initMediaReactions = (media?: PostMediaItem[]) => {
    if (!media) return;
    for (const m of media) {
        const mKey = String(m.id);
        if (mediaLikedMap.value[mKey] === undefined) {
            mediaLikedMap.value[mKey] = Boolean(m.is_liked);
            mediaLikesCountMap.value[mKey] = m.likes_count ?? 0;
            serverMediaLikedMap.value[mKey] = Boolean(m.is_liked);
        }
        const commentKey = 'media_' + String(m.id);
        if (localCommentsMap.value[commentKey] === undefined && m.comments) {
            localCommentsMap.value[commentKey] = JSON.parse(
                JSON.stringify(m.comments),
            );
            commentsCountMap.value[commentKey] =
                m.comments_count ?? m.comments.length;
            initCommentReactions(m.comments);
        }
    }
};

const isMediaLiked = (mediaItem: PostMediaItem | null | undefined): boolean => {
    if (!mediaItem) return false;
    const key = String(mediaItem.id);
    if (mediaLikedMap.value[key] !== undefined) {
        return mediaLikedMap.value[key];
    }
    return Boolean(mediaItem.is_liked);
};

const getMediaLikesCount = (
    mediaItem: PostMediaItem | null | undefined,
): number => {
    if (!mediaItem) return 0;
    const key = String(mediaItem.id);
    if (mediaLikesCountMap.value[key] !== undefined) {
        return mediaLikesCountMap.value[key];
    }
    return mediaItem.likes_count ?? 0;
};

const toggleMediaLike = (
    post: CommunityFeedPost,
    mediaItem: PostMediaItem | null | undefined,
) => {
    if (!mediaItem || !mediaItem.id) {
        toggleLike(post.id);
        return;
    }

    const mKey = String(mediaItem.id);
    const prevLiked = isMediaLiked(mediaItem);
    const prevCount = getMediaLikesCount(mediaItem);
    const nextLiked = !prevLiked;

    // 1. Immediate optimistic client update for media item
    mediaLikedMap.value[mKey] = nextLiked;
    mediaLikesCountMap.value[mKey] = Math.max(
        0,
        prevCount + (nextLiked ? 1 : -1),
    );

    // 2. Clear existing debounce timer
    if (mediaLikeDebounceTimers.value[mKey]) {
        clearTimeout(mediaLikeDebounceTimers.value[mKey]);
    }

    // 3. Debounce network sync by 350ms
    mediaLikeDebounceTimers.value[mKey] = setTimeout(async () => {
        delete mediaLikeDebounceTimers.value[mKey];

        if (mediaLikedMap.value[mKey] === serverMediaLikedMap.value[mKey]) {
            return;
        }

        try {
            const res = await axios.post(
                likeMedia.url(Number(mediaItem.id)),
                {},
                { headers: { Accept: 'application/json' } },
            );
            if (res.data) {
                serverMediaLikedMap.value[mKey] = Boolean(res.data.is_liked);
                mediaLikedMap.value[mKey] = Boolean(res.data.is_liked);
                mediaLikesCountMap.value[mKey] = Number(
                    res.data.likes_count ?? 0,
                );
            }
        } catch {
            mediaLikedMap.value[mKey] =
                serverMediaLikedMap.value[mKey] ?? prevLiked;
            mediaLikesCountMap.value[mKey] = prevCount;
        }
    }, 350);
};

watch(
    displayPosts,
    (posts) => {
        for (const post of posts) {
            const key = String(post.id);
            if (likedMap.value[key] === undefined) {
                likedMap.value[key] = Boolean(post.is_liked);
                likesCountMap.value[key] = post.likes_count ?? 0;
                serverLikedMap.value[key] = Boolean(post.is_liked);
            }
            if (bookmarkedMap.value[key] === undefined) {
                bookmarkedMap.value[key] = Boolean(post.is_bookmarked);
                serverBookmarkedMap.value[key] = Boolean(post.is_bookmarked);
            }
            if (localCommentsMap.value[key] === undefined) {
                localCommentsMap.value[key] = post.comments
                    ? JSON.parse(JSON.stringify(post.comments))
                    : [];
                commentsCountMap.value[key] = post.comments_count ?? 0;
            }
            initCommentReactions(post.comments);
            initMediaReactions(post.media);
        }
    },
    { immediate: true },
);

const isPostLiked = (postId: number | string) =>
    likedMap.value[String(postId)] ?? false;
const getPostLikesCount = (postId: number | string) =>
    likesCountMap.value[String(postId)] ?? 0;
const isPostBookmarked = (postId: number | string) =>
    bookmarkedMap.value[String(postId)] ?? false;

const toggleLike = (postId: number | string) => {
    const key = String(postId);
    const prevLiked = likedMap.value[key] ?? false;
    const prevCount = likesCountMap.value[key] ?? 0;
    const nextLiked = !prevLiked;

    // 1. Immediate optimistic client update (0ms visual latency)
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

    // 4. Debounce network sync by 350ms to prevent spamming server
    likeDebounceTimers.value[key] = setTimeout(async () => {
        delete likeDebounceTimers.value[key];

        // If user rapidly clicked like then unlike, net state hasn't changed from server: skip request
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
            // Rollback on network failure
            likedMap.value[key] = serverLikedMap.value[key] ?? prevLiked;
            likesCountMap.value[key] = prevCount;
        }
    }, 350);
};

const toggleBookmark = (postId: number | string) => {
    const key = String(postId);
    const prevBookmarked = bookmarkedMap.value[key] ?? false;
    const nextBookmarked = !prevBookmarked;

    // 1. Immediate optimistic update
    bookmarkedMap.value[key] = nextBookmarked;

    // Keep saved cache in sync
    if (tabPostsCache.value['saved']) {
        if (nextBookmarked) {
            const postToAdd = (
                tabPostsCache.value['all'] || localPosts.value
            ).find((p) => String(p.id) === key);
            if (
                postToAdd &&
                !tabPostsCache.value['saved'].some((p) => String(p.id) === key)
            ) {
                tabPostsCache.value['saved'] = [
                    postToAdd,
                    ...tabPostsCache.value['saved'],
                ];
            }
        } else {
            tabPostsCache.value['saved'] = tabPostsCache.value['saved'].filter(
                (p) => String(p.id) !== key,
            );
            if (activeTab.value === 'saved') {
                localPosts.value = localPosts.value.filter(
                    (p) => String(p.id) !== key,
                );
            }
        }
    }

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
            // Rollback on network failure
            bookmarkedMap.value[key] =
                serverBookmarkedMap.value[key] ?? prevBookmarked;
        }
    }, 350);
};

onBeforeUnmount(() => {
    for (const timer of Object.values(likeDebounceTimers.value)) {
        clearTimeout(timer);
    }
    for (const timer of Object.values(bookmarkDebounceTimers.value)) {
        clearTimeout(timer);
    }
    for (const timer of Object.values(commentLikeDebounceTimers.value)) {
        clearTimeout(timer);
    }
    for (const timer of Object.values(mediaLikeDebounceTimers.value)) {
        clearTimeout(timer);
    }
});

// ─── Quick Comments & Threaded Replies State ────────────────────────────────
const expandedComments = ref<Record<string, boolean>>({});
const commentInputs = ref<Record<string, string>>({});
const isSubmittingComment = ref<Record<string, boolean>>({});
const replyingTo = ref<
    Record<string, { id: number | string; author_handle: string } | null>
>({});
const expandedReplies = ref<Record<string, boolean>>({});
const commentInputRefs = ref<Record<string, HTMLInputElement | null>>({});

const toggleComments = (postId: number | string) => {
    const key = String(postId);
    expandedComments.value[key] = !expandedComments.value[key];
};

const getPostComments = (post: CommunityFeedPost): PostComment[] => {
    const key = String(post.id);
    return localCommentsMap.value[key] ?? post.comments ?? [];
};

const getPostCommentsCount = (post: CommunityFeedPost): number => {
    const key = String(post.id);
    return commentsCountMap.value[key] ?? post.comments_count ?? 0;
};

const setReplyingTo = (
    postId: number | string,
    comment: { id: number | string; author_handle: string },
) => {
    const key = String(postId);
    replyingTo.value[key] = {
        id: comment.id,
        author_handle: comment.author_handle,
    };
    setTimeout(() => {
        commentInputRefs.value[key]?.focus();
    }, 50);
};

const cancelReply = (postId: number | string) => {
    const key = String(postId);
    replyingTo.value[key] = null;
};

const toggleReplies = (commentId: number | string) => {
    const key = String(commentId);
    expandedReplies.value[key] = !expandedReplies.value[key];
};

const handleAddComment = async (postId: number | string) => {
    const key = String(postId);
    const content = (commentInputs.value[key] || '').trim();
    if (!content || isSubmittingComment.value[key]) {
        return;
    }

    const replyTarget = replyingTo.value[key];
    const parentId = replyTarget?.id ?? null;

    isSubmittingComment.value[key] = true;
    commentInputs.value[key] = '';
    replyingTo.value[key] = null;

    try {
        const response = await axios.post(
            `/community/posts/${postId}/comments`,
            {
                content,
                parent_id: parentId,
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        const newComment = response.data?.comment;
        if (newComment) {
            const newKey = String(newComment.id);
            commentLikedMap.value[newKey] = false;
            commentLikesCountMap.value[newKey] = 0;
            serverCommentLikedMap.value[newKey] = false;
            if (!localCommentsMap.value[key]) {
                const post = displayPosts.value.find(
                    (p) => String(p.id) === key,
                );
                localCommentsMap.value[key] = post?.comments
                    ? JSON.parse(JSON.stringify(post.comments))
                    : [];
            }
            if (response.data.comments_count !== undefined) {
                commentsCountMap.value[key] = response.data.comments_count;
            } else {
                commentsCountMap.value[key] =
                    (commentsCountMap.value[key] ?? 0) + 1;
            }

            if (parentId) {
                const parent = localCommentsMap.value[key].find(
                    (c) => String(c.id) === String(parentId),
                );
                if (parent) {
                    if (!parent.replies) {
                        parent.replies = [];
                    }
                    parent.replies.push(newComment);
                    expandedReplies.value[String(parentId)] = true;
                } else {
                    localCommentsMap.value[key].push(newComment);
                }
            } else {
                localCommentsMap.value[key].push(newComment);
            }
        }
    } catch {
        router.post(
            `/community/posts/${postId}/comments`,
            { content, parent_id: parentId },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    } finally {
        isSubmittingComment.value[key] = false;
    }
};

const getMediaComments = (
    mediaItem: PostMediaItem | null | undefined,
): PostComment[] => {
    if (!mediaItem || !mediaItem.id) return [];
    const key = 'media_' + String(mediaItem.id);
    return localCommentsMap.value[key] ?? mediaItem.comments ?? [];
};

const getMediaCommentsCount = (
    mediaItem: PostMediaItem | null | undefined,
): number => {
    if (!mediaItem || !mediaItem.id) return 0;
    const key = 'media_' + String(mediaItem.id);
    return (
        commentsCountMap.value[key] ??
        mediaItem.comments_count ??
        mediaItem.comments?.length ??
        0
    );
};

const handleAddLightboxComment = async (
    post: CommunityFeedPost,
    mediaItem: PostMediaItem | null | undefined,
) => {
    if (!mediaItem || !mediaItem.id) {
        await handleAddComment(post.id);
        return;
    }

    const key = 'media_' + String(mediaItem.id);
    const content = (commentInputs.value[key] || '').trim();
    if (!content || isSubmittingComment.value[key]) {
        return;
    }

    const replyTarget = replyingTo.value[key];
    const parentId = replyTarget?.id ?? null;

    isSubmittingComment.value[key] = true;
    commentInputs.value[key] = '';
    replyingTo.value[key] = null;

    try {
        const response = await axios.post(
            storePostComment.url(Number(post.id)),
            {
                content,
                parent_id: parentId,
                post_media_id: mediaItem.id,
            },
            {
                headers: {
                    Accept: 'application/json',
                },
            },
        );

        const newComment = response.data?.comment;
        if (newComment) {
            const newKey = String(newComment.id);
            commentLikedMap.value[newKey] = false;
            commentLikesCountMap.value[newKey] = 0;
            serverCommentLikedMap.value[newKey] = false;

            if (!localCommentsMap.value[key]) {
                localCommentsMap.value[key] = mediaItem.comments
                    ? JSON.parse(JSON.stringify(mediaItem.comments))
                    : [];
            }

            if (parentId) {
                const parent = localCommentsMap.value[key].find(
                    (c) => String(c.id) === String(parentId),
                );
                if (parent) {
                    if (!parent.replies) {
                        parent.replies = [];
                    }
                    parent.replies.push(newComment);
                    expandedReplies.value[String(parentId)] = true;
                } else {
                    localCommentsMap.value[key].push(newComment);
                }
            } else {
                localCommentsMap.value[key].push(newComment);
            }

            commentsCountMap.value[key] =
                response.data.comments_count ??
                getMediaCommentsCount(mediaItem) + 1;
        }
    } catch {
        router.post(
            storePostComment.url(Number(post.id)),
            {
                content,
                parent_id: parentId,
                post_media_id: mediaItem.id,
            },
            {
                preserveScroll: true,
                preserveState: true,
            },
        );
    } finally {
        isSubmittingComment.value[key] = false;
    }
};

// ─── Post Composer Form & Media Modal State ─────────────────────────────────
export interface ComposerMediaFile {
    file: File;
    preview: string;
    type: 'image' | 'video';
    name: string;
    size: number;
}

const isComposerOpen = ref(false);
const composerFile = ref<File | null>(null);
const composerFilePreview = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

// Social Pop-up Media Composer Modal state
const isMediaModalOpen = ref(false);
const mediaFiles = ref<ComposerMediaFile[]>([]);
const activeMediaIndex = ref(0);
const selectedMusicTrackId = ref<number | null>(null);
const isMusicPickerOpen = ref(false);
const previewAudio = ref<HTMLAudioElement | null>(null);
const playingTrackId = ref<number | null>(null);
const mediaFileInputRef = ref<HTMLInputElement | null>(null);

// Section picker state for students with multiple sections
const composerSectionId = ref<number | null>(
    props.currentSectionId ?? props.availableSections?.[0]?.id ?? null,
);
const isComposerSectionDropdownOpen = ref(false);
const isInlineSectionDropdownOpen = ref(false);

const activeMediaItem = computed<ComposerMediaFile | null>(() => {
    return mediaFiles.value[activeMediaIndex.value] ?? null;
});

const selectedMusicTrack = computed<PostMusicTrack | null>(() => {
    if (!selectedMusicTrackId.value) return null;
    return (
        props.musicTracks?.find((t) => t.id === selectedMusicTrackId.value) ??
        null
    );
});

const composerForm = useForm<{
    content: string;
    is_anonymous: boolean;
    section_id: number | null;
    profile_music_track_id: number | null;
    attachment: File | null;
    attachments: File[];
}>({
    content: '',
    is_anonymous: false,
    section_id:
        props.currentSectionId ?? props.availableSections?.[0]?.id ?? null,
    profile_music_track_id: null,
    attachment: null,
    attachments: [],
});

const postingSectionName = computed(() => {
    if (composerSectionId.value) {
        const found = props.availableSections?.find(
            (s) => s.id === composerSectionId.value,
        );
        if (found) return found.name;
    }
    return sectionDisplayName.value;
});

const revokeMediaPreviews = () => {
    mediaFiles.value.forEach((item) => {
        if (item.preview && item.preview.startsWith('blob:')) {
            try {
                URL.revokeObjectURL(item.preview);
            } catch {
                // ignore
            }
        }
    });
};

const stopMusicPreview = () => {
    if (previewAudio.value) {
        previewAudio.value.pause();
        previewAudio.value = null;
    }
    playingTrackId.value = null;
};

const toggleTrackPreview = (track: PostMusicTrack) => {
    if (playingTrackId.value === track.id) {
        stopMusicPreview();
        return;
    }
    stopMusicPreview();
    const audio = new Audio(track.audio_url);
    previewAudio.value = audio;
    playingTrackId.value = track.id;
    audio.play().catch(() => {
        stopMusicPreview();
    });
    audio.onended = () => {
        playingTrackId.value = null;
        previewAudio.value = null;
    };
};

const selectMusicTrack = (track: PostMusicTrack) => {
    if (selectedMusicTrackId.value === track.id) {
        selectedMusicTrackId.value = null;
    } else {
        selectedMusicTrackId.value = track.id;
    }
    stopMusicPreview();
    isMusicPickerOpen.value = false;
};

const removeSelectedMusic = () => {
    selectedMusicTrackId.value = null;
    stopMusicPreview();
};

const mediaUploadError = ref<string | null>(null);

const openMediaModalWithFiles = (files: FileList | File[]) => {
    const list = Array.from(files);
    if (!list.length) return;

    mediaUploadError.value = null;
    composerForm.clearErrors?.();

    for (const file of list) {
        const isVid =
            file.type.startsWith('video/') ||
            /\.(mp4|webm|mov|m4v)$/i.test(file.name);
        const previewUrl = URL.createObjectURL(file);
        const entry = {
            file,
            preview: previewUrl,
            type: (isVid ? 'video' : 'image') as 'image' | 'video',
            name: file.name,
            size: file.size,
        };
        mediaFiles.value.push(entry);

        if (!isVid) {
            void compressImage(file, {
                maxSize: 1920,
                quality: 0.85,
            })
                .then((compressed) => {
                    const target = mediaFiles.value.find(
                        (item) => item.preview === previewUrl,
                    );
                    if (target && compressed) {
                        target.file = compressed;
                        target.size = compressed.size;
                    }
                })
                .catch(() => {
                    // Keep original file if compression fails
                });
        }
    }

    if (activeMediaIndex.value >= mediaFiles.value.length) {
        activeMediaIndex.value = 0;
    }

    isMediaModalOpen.value = true;
};

const handleMediaFileInputChange = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files.length) {
        openMediaModalWithFiles(target.files);
        target.value = '';
    }
};

const removeMediaItem = (index: number) => {
    const item = mediaFiles.value[index];
    if (item && item.preview.startsWith('blob:')) {
        try {
            URL.revokeObjectURL(item.preview);
        } catch {
            // ignore
        }
    }
    mediaFiles.value.splice(index, 1);
    if (activeMediaIndex.value >= mediaFiles.value.length) {
        activeMediaIndex.value = Math.max(0, mediaFiles.value.length - 1);
    }
    if (mediaFiles.value.length === 0) {
        closeMediaModal();
    }
};

const moveMediaCarousel = (dir: number) => {
    if (!mediaFiles.value.length) return;
    activeMediaIndex.value =
        (activeMediaIndex.value + dir + mediaFiles.value.length) %
        mediaFiles.value.length;
};

const closeMediaModal = () => {
    stopMusicPreview();
    isMusicPickerOpen.value = false;
    isMediaModalOpen.value = false;
    mediaUploadError.value = null;
    revokeMediaPreviews();
    mediaFiles.value = [];
    activeMediaIndex.value = 0;
    selectedMusicTrackId.value = null;
    if (mediaFileInputRef.value) {
        mediaFileInputRef.value.value = '';
    }
};

const submitMediaPost = () => {
    if (!composerForm.content.trim() && mediaFiles.value.length === 0) {
        return;
    }

    mediaUploadError.value = null;
    composerForm.section_id =
        composerSectionId.value ?? selectedSectionId.value;
    composerForm.profile_music_track_id = selectedMusicTrackId.value;
    composerForm.attachments = mediaFiles.value.map((m) => m.file);
    composerForm.attachment = null;

    composerForm.post('/community/posts', {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: (page) => {
            const nextPosts = ((page?.props as any)?.posts ??
                props.posts ??
                []) as CommunityFeedPost[];
            localPosts.value = [...nextPosts];
            tabPostsCache.value = {
                [activeTab.value]: [...nextPosts],
            };
            composerForm.reset();
            closeMediaModal();
            clearComposerFile();
            isComposerOpen.value = false;
        },
        onError: (errors) => {
            const firstErr =
                errors.attachments ||
                (errors as Record<string, string>)['attachments.0'] ||
                errors.attachment ||
                errors.content ||
                Object.values(errors)[0];
            mediaUploadError.value =
                typeof firstErr === 'string' && firstErr.trim() !== ''
                    ? firstErr
                    : 'Could not publish your post. The file may exceed the upload limit or failed to upload.';
        },
    });
};

// ─── Feed Post Soundtrack Player ─────────────────────────────────────────────
const activePlayingPostId = ref<number | string | null>(null);
const feedAudioElement = ref<HTMLAudioElement | null>(null);

const stopFeedPostMusic = () => {
    if (feedAudioElement.value) {
        feedAudioElement.value.pause();
        feedAudioElement.value = null;
    }
    activePlayingPostId.value = null;
};

const toggleFeedPostMusic = (post: CommunityFeedPost) => {
    if (!post.music) return;
    if (activePlayingPostId.value === post.id) {
        stopFeedPostMusic();
        return;
    }
    stopFeedPostMusic();
    activePlayingPostId.value = post.id;
    const audio = new Audio(post.music.audio_url);
    feedAudioElement.value = audio;
    audio.play().catch(() => {
        stopFeedPostMusic();
    });
    audio.onended = () => {
        activePlayingPostId.value = null;
        feedAudioElement.value = null;
    };
};

// ─── Social Media Lightbox / Full-screen Post Media Modal ───────────────────
const isLightboxOpen = ref(false);
const lightboxPost = ref<CommunityFeedPost | null>(null);
const lightboxMediaIndex = ref(0);

const activeLightboxMediaItem = computed(() => {
    if (!lightboxPost.value) return null;
    if (lightboxPost.value.media && lightboxPost.value.media.length > 0) {
        return (
            lightboxPost.value.media[lightboxMediaIndex.value] ||
            lightboxPost.value.media[0]
        );
    }
    if (lightboxPost.value.attachment_url) {
        return {
            id: 0,
            url: lightboxPost.value.attachment_url,
            type: lightboxPost.value.attachment_type || 'image',
            name: lightboxPost.value.attachment_name,
            size: lightboxPost.value.attachment_size,
        };
    }
    return null;
});

const lightboxCommentKey = computed(() => {
    if (activeLightboxMediaItem.value?.id) {
        return 'media_' + String(activeLightboxMediaItem.value.id);
    }
    return lightboxPost.value ? String(lightboxPost.value.id) : '';
});

const currentLightboxLiked = computed(() => {
    if (activeLightboxMediaItem.value?.id) {
        return isMediaLiked(activeLightboxMediaItem.value);
    }
    return lightboxPost.value ? isPostLiked(lightboxPost.value.id) : false;
});

const currentLightboxLikesCount = computed(() => {
    if (activeLightboxMediaItem.value?.id) {
        return getMediaLikesCount(activeLightboxMediaItem.value);
    }
    return lightboxPost.value ? getPostLikesCount(lightboxPost.value.id) : 0;
});

const currentLightboxComments = computed(() => {
    if (activeLightboxMediaItem.value?.id) {
        return getMediaComments(activeLightboxMediaItem.value);
    }
    return lightboxPost.value ? getPostComments(lightboxPost.value) : [];
});

const currentLightboxCommentsCount = computed(() => {
    if (activeLightboxMediaItem.value?.id) {
        return getMediaCommentsCount(activeLightboxMediaItem.value);
    }
    return lightboxPost.value ? getPostCommentsCount(lightboxPost.value) : 0;
});

const openPostLightbox = (post: CommunityFeedPost, mediaIndex = 0) => {
    lightboxPost.value = post;
    lightboxMediaIndex.value = mediaIndex;
    isLightboxOpen.value = true;
};

const closePostLightbox = () => {
    isLightboxOpen.value = false;
    lightboxPost.value = null;
    lightboxMediaIndex.value = 0;
};

const lightboxPrevMedia = () => {
    if (!lightboxPost.value?.media || lightboxPost.value.media.length <= 1)
        return;
    lightboxMediaIndex.value =
        (lightboxMediaIndex.value - 1 + lightboxPost.value.media.length) %
        lightboxPost.value.media.length;
};

const lightboxNextMedia = () => {
    if (!lightboxPost.value?.media || lightboxPost.value.media.length <= 1)
        return;
    lightboxMediaIndex.value =
        (lightboxMediaIndex.value + 1) % lightboxPost.value.media.length;
};

const handleFileSelect = (event: Event) => {
    const target = event.target as HTMLInputElement;
    if (target.files && target.files[0]) {
        const file = target.files[0];
        const isMedia =
            file.type.startsWith('image/') ||
            file.type.startsWith('video/') ||
            /\.(mp4|mov|webm|m4v|jpg|jpeg|png|webp|gif)$/i.test(file.name);

        if (isMedia) {
            // Automatically launch the Instagram / TikTok-style pop-up media composer!
            openMediaModalWithFiles([file]);
            target.value = '';
            return;
        }

        // Otherwise keep regular document/code attachment inline
        composerFile.value = file;
        composerForm.attachment = file;
        composerFilePreview.value = null;
    }
};

const clearComposerFile = () => {
    composerFile.value = null;
    composerFilePreview.value = null;
    composerForm.attachment = null;
    composerForm.attachments = [];
    if (fileInputRef.value) {
        fileInputRef.value.value = '';
    }
};

const submitPost = () => {
    if (!composerForm.content.trim() && !composerForm.attachment) {
        return;
    }

    composerForm.section_id =
        composerSectionId.value ?? selectedSectionId.value;
    composerForm.profile_music_track_id = null;
    composerForm.attachments = [];

    composerForm.post('/community/posts', {
        forceFormData: Boolean(composerForm.attachment),
        preserveScroll: true,
        onSuccess: (page) => {
            const nextPosts = ((page?.props as any)?.posts ??
                props.posts ??
                []) as CommunityFeedPost[];
            localPosts.value = [...nextPosts];
            tabPostsCache.value = {
                [activeTab.value]: [...nextPosts],
            };
            composerForm.reset();
            clearComposerFile();
            isComposerOpen.value = false;
        },
    });
};

// ─── Stories / Classmate Presence Bar (Instagram Style) ──────────────────────
const userStoryGroup = computed<UserStoryGroup | null>(() => {
    return props.stories?.find((g) => g.is_self) ?? null;
});

const classmateStoryGroups = computed<UserStoryGroup[]>(() => {
    return props.stories?.filter((g) => !g.is_self) ?? [];
});

const storyTrayItems = computed<StoryTrayItem[]>(() => {
    const items: StoryTrayItem[] = [];

    // 1. Current user ("Your story")
    const myGroup = userStoryGroup.value;
    const hasMyStory = Boolean(myGroup && myGroup.stories.length > 0);
    items.push({
        id: 'you',
        name: 'Your story',
        username: currentUserHandle.value,
        initials: currentUserInitials.value,
        avatar: currentUser.value?.avatar ? currentUser.value.avatar : null,
        isUser: true,
        hasStory: hasMyStory,
        storyGroup: myGroup,
        status: 'online',
        public_id: currentUser.value?.public_id,
    });

    const seenUserIds = new Set<string>();
    if (currentUser.value?.id) {
        seenUserIds.add(String(currentUser.value.id));
    }
    if (userStoryGroup.value) {
        seenUserIds.add(String(userStoryGroup.value.user_id));
    }

    // 2. All students in section (whether they have stories or not)
    const sectionClassmates = classmatesList.value.filter(
        (c) =>
            !c.isCurrentUser &&
            String(c.id) !== String(currentUser.value?.id) &&
            !(
                userStoryGroup.value &&
                String(c.id) === String(userStoryGroup.value.user_id)
            ),
    );

    const classmateTrayItems: StoryTrayItem[] = [];
    for (const classmate of sectionClassmates) {
        const cId = String(classmate.id);
        seenUserIds.add(cId);

        const group =
            classmateStoryGroups.value.find(
                (g) =>
                    (String(g.user_id) === cId ||
                        (Boolean(g.public_id) &&
                            Boolean(classmate.public_id) &&
                            g.public_id === classmate.public_id) ||
                        (Boolean(g.name) &&
                            Boolean(classmate.name) &&
                            g.name.trim().toLowerCase() ===
                                classmate.name.trim().toLowerCase())) &&
                    g.stories &&
                    g.stories.length > 0,
            ) || null;

        if (group) {
            seenUserIds.add(String(group.user_id));
        }

        classmateTrayItems.push({
            id: 'classmate-' + cId,
            name: (group?.name || classmate.name).split(' ')[0],
            username: group?.handle || strToHandle(classmate.name),
            initials: getInitials(group?.name || classmate.name),
            avatar: group?.avatar || classmate.avatar,
            isUser: false,
            hasStory: Boolean(group && group.stories.length > 0),
            storyGroup: group,
            status: classmate.status || 'offline',
            public_id: group?.public_id || classmate.public_id || undefined,
        });
    }

    // Sort: students with active stories first, then remainder by presence status (online > idle > offline)
    classmateTrayItems.sort((a, b) => {
        if (a.hasStory !== b.hasStory) {
            return a.hasStory ? -1 : 1;
        }
        const p: Record<string, number> = { online: 0, idle: 1, offline: 2 };
        return (p[a.status] ?? 3) - (p[b.status] ?? 3);
    });

    items.push(...classmateTrayItems);

    // 3. Any additional story groups from outside section with active stories
    for (const group of classmateStoryGroups.value) {
        const gId = String(group.user_id);
        if (
            seenUserIds.has(gId) ||
            !group.stories ||
            group.stories.length === 0
        ) {
            continue;
        }
        seenUserIds.add(gId);
        items.push({
            id: 'story-group-' + group.user_id,
            name: group.name.split(' ')[0],
            username: group.handle,
            initials: getInitials(group.name),
            avatar: group.avatar,
            isUser: false,
            hasStory: true,
            storyGroup: group,
            status: 'online',
            public_id: group.public_id || undefined,
        });
    }

    return items;
});

const handleStoryClick = (story: StoryTrayItem) => {
    if (story.isUser) {
        if (
            story.hasStory &&
            story.storyGroup &&
            story.storyGroup.stories.length > 0
        ) {
            openStoryViewer(story.storyGroup);
        } else {
            openStoryModal();
        }
        return;
    }

    if (
        story.hasStory &&
        story.storyGroup &&
        story.storyGroup.stories.length > 0
    ) {
        openStoryViewer(story.storyGroup);
        return;
    }

    openProfileModal({
        public_id: story.public_id,
        name: story.name,
        handle: story.username,
        avatar: story.avatar,
        section_name: sectionDisplayName.value,
    });
};

// ─── MyDay / Community Story Upload Modal State ──────────────────────────────
const isStoryModalOpen = ref(false);
const isStoryDragOver = ref(false);
const storyFileInputRef = ref<HTMLInputElement | null>(null);
const storyMediaFile = ref<File | null>(null);
const storyMediaPreview = ref<string | null>(null);
const storyMediaType = ref<'image' | 'video'>('image');
const storyDuration = ref<number>(5);
const storyCaption = ref<string>('');
const storyUploadError = ref<string | null>(null);
const isSubmittingStory = ref<boolean>(false);

const clearStoryMedia = () => {
    if (
        storyMediaPreview.value &&
        storyMediaPreview.value.startsWith('blob:') &&
        typeof URL !== 'undefined' &&
        typeof URL.revokeObjectURL === 'function'
    ) {
        URL.revokeObjectURL(storyMediaPreview.value);
    }
    storyMediaFile.value = null;
    storyMediaPreview.value = null;
    storyUploadError.value = null;
    if (storyFileInputRef.value) {
        storyFileInputRef.value.value = '';
    }
};

const openStoryModal = () => {
    clearStoryMedia();
    storyCaption.value = '';
    storyUploadError.value = null;
    storyMediaType.value = 'image';
    storyDuration.value = 5;
    isStoryDragOver.value = false;
    isStoryModalOpen.value = true;
};

const closeStoryModal = () => {
    clearStoryMedia();
    isStoryModalOpen.value = false;
    storyCaption.value = '';
    storyUploadError.value = null;
    isStoryDragOver.value = false;
    isSubmittingStory.value = false;
};

const processStoryFile = (file: File) => {
    storyUploadError.value = null;
    const isVideo =
        file.type.startsWith('video/') || /\.(mp4|webm|mov)$/i.test(file.name);
    const isImg = file.type.startsWith('image/');

    if (!isVideo && !isImg) {
        storyUploadError.value =
            'Please select a photo (JPG, PNG, WebP, GIF) or video (MP4, WebM, MOV).';
        return;
    }

    if (isImg && file.size > 10 * 1024 * 1024) {
        storyUploadError.value = 'Photo exceeds 10MB limit.';
        return;
    }

    if (isVideo && file.size > 30 * 1024 * 1024) {
        storyUploadError.value = 'Video exceeds 30MB limit.';
        return;
    }

    clearStoryMedia();

    if (isVideo) {
        const objectUrl = URL.createObjectURL(file);
        const tempVideo = document.createElement('video');
        tempVideo.preload = 'metadata';
        tempVideo.src = objectUrl;
        tempVideo.onloadedmetadata = () => {
            URL.revokeObjectURL(objectUrl);
            const duration = tempVideo.duration;
            if (duration > 30.5) {
                clearStoryMedia();
                storyUploadError.value = `Video is ${Math.round(duration)} seconds long. MyDay videos cannot exceed 30 seconds.`;
            } else {
                storyMediaType.value = 'video';
                storyDuration.value = Math.max(
                    1,
                    Math.min(30, Math.round(duration)),
                );
                storyMediaFile.value = file;
                storyMediaPreview.value = URL.createObjectURL(file);
            }
        };
        tempVideo.onerror = () => {
            URL.revokeObjectURL(objectUrl);
            storyMediaType.value = 'video';
            storyDuration.value = 15;
            storyMediaFile.value = file;
            storyMediaPreview.value = URL.createObjectURL(file);
        };
    } else {
        storyMediaType.value = 'image';
        storyDuration.value = 5;
        storyMediaFile.value = file;
        storyMediaPreview.value = URL.createObjectURL(file);
    }
};

const handleStoryFileChange = (e: Event) => {
    const input = e.target as HTMLInputElement;
    const file = input.files?.[0];
    if (!file) return;
    processStoryFile(file);
};

const handleStoryDragOver = (e: DragEvent) => {
    e.preventDefault();
    isStoryDragOver.value = true;
};

const handleStoryDragLeave = (e: DragEvent) => {
    e.preventDefault();
    isStoryDragOver.value = false;
};

const handleStoryDrop = (e: DragEvent) => {
    e.preventDefault();
    isStoryDragOver.value = false;
    const file = e.dataTransfer?.files?.[0];
    if (file) {
        processStoryFile(file);
    }
};

const handleUploadStory = () => {
    if (!storyMediaFile.value) {
        storyUploadError.value = 'Please select a photo or video to share.';
        return;
    }

    isSubmittingStory.value = true;
    storyUploadError.value = null;

    const formData = new FormData();
    formData.append('media', storyMediaFile.value);
    formData.append('duration_seconds', String(storyDuration.value));
    if (storyCaption.value.trim()) {
        formData.append('caption', storyCaption.value.trim());
    }

    router.post('/community/stories', formData, {
        forceFormData: true,
        preserveScroll: true,
        onSuccess: () => {
            closeStoryModal();
        },
        onError: (errs) => {
            storyUploadError.value =
                (Object.values(errs)[0] as string) || 'Failed to upload story.';
            isSubmittingStory.value = false;
        },
        onFinish: () => {
            isSubmittingStory.value = false;
        },
    });
};

// ─── Full-Screen Story Viewer (Instagram / Facebook MyDay Style) ─────────────
const isStoryViewerOpen = ref(false);
const activeStoryGroup = ref<UserStoryGroup | null>(null);
const currentStoryIndex = ref(0);
const storyProgress = ref(0);
const isStoryPaused = ref(false);
const isDeletingStory = ref(false);
const storyVideoRef = ref<HTMLVideoElement | null>(null);
let storyTimer: ReturnType<typeof setInterval> | null = null;

interface FlyingReaction {
    id: number;
    emoji: string;
    left: number;
    size: number;
    rotation: number;
}

const storyReactionOptions = [
    { id: '❤️', label: 'Heart', icon: Heart },
    { id: '🔥', label: 'Fire', icon: Flame },
    { id: '👏', label: 'Clap', icon: ThumbsUp },
    { id: '😂', label: 'Laugh', icon: Laugh },
    { id: '😮', label: 'Wow', icon: Sparkles },
    { id: '🎉', label: 'Party', icon: PartyPopper },
] as const;

const getReactionIcon = (id: string) => {
    const found = storyReactionOptions.find((r) => r.id === id);
    return found ? found.icon : Heart;
};

const flyingReactions = ref<FlyingReaction[]>([]);
let nextReactionParticleId = 0;

const triggerFlyingReactions = (emoji: string) => {
    const burstCount = 6;
    for (let i = 0; i < burstCount; i++) {
        const particleId = ++nextReactionParticleId;
        const particle: FlyingReaction = {
            id: particleId,
            emoji,
            left: 20 + Math.random() * 60,
            size: 22 + Math.floor(Math.random() * 14),
            rotation: -25 + Math.random() * 50,
        };
        flyingReactions.value.push(particle);
        setTimeout(() => {
            flyingReactions.value = flyingReactions.value.filter(
                (p) => p.id !== particleId,
            );
        }, 1200);
    }
};

const handleStoryReaction = async (emoji: string) => {
    const story = currentStory.value;
    if (!story) return;

    triggerFlyingReactions(emoji);

    const prevReaction = story.user_reaction;
    const prevIsReacted = story.is_reacted;
    const prevCount = story.reactions_count ?? 0;

    const isSame = prevReaction === emoji;
    story.user_reaction = isSame ? null : emoji;
    story.is_reacted = !isSame;
    story.reactions_count = Math.max(
        0,
        prevCount + (isSame ? -1 : prevReaction ? 0 : 1),
    );

    try {
        const response = await axios.post(
            `/community/stories/${story.id}/reactions`,
            { reaction: emoji },
        );
        if (response.data) {
            story.reactions_count = response.data.reactions_count;
            story.user_reaction = response.data.user_reaction;
            story.is_reacted = response.data.is_reacted;
        }
    } catch {
        story.user_reaction = prevReaction;
        story.is_reacted = prevIsReacted;
        story.reactions_count = prevCount;
    }
};

const isFeaturingStory = ref(false);
const featuredToast = ref<string | null>(null);

const handleFeatureStoryFromViewer = async () => {
    const story = currentStory.value;
    if (!story || isFeaturingStory.value) return;

    isFeaturingStory.value = true;
    try {
        await axios.post('/community/profile/featured', {
            story_id: story.id,
            title: story.caption || undefined,
        });
        featuredToast.value = 'Pinned to your Profile Featured highlights!';
        setTimeout(() => {
            featuredToast.value = null;
        }, 3000);
    } catch {
        featuredToast.value = 'Could not pin story to Featured.';
        setTimeout(() => {
            featuredToast.value = null;
        }, 3000);
    } finally {
        isFeaturingStory.value = false;
    }
};

const currentStory = computed<StoryItem | null>(() => {
    if (!activeStoryGroup.value || !activeStoryGroup.value.stories.length) {
        return null;
    }
    return activeStoryGroup.value.stories[currentStoryIndex.value] || null;
});

const allActiveStoryGroups = computed<UserStoryGroup[]>(() => {
    const list: UserStoryGroup[] = [];
    if (userStoryGroup.value && userStoryGroup.value.stories.length > 0) {
        list.push(userStoryGroup.value);
    }
    for (const g of classmateStoryGroups.value) {
        if (g.stories.length > 0) {
            list.push(g);
        }
    }
    return list;
});

const openStoryModalFromViewer = () => {
    stopStoryPlayback();
    isStoryOptionsOpen.value = false;
    isStoryViewerOpen.value = false;
    openStoryModal();
};

const openStoryViewer = (group: UserStoryGroup, startIndex = 0) => {
    if (!group || !group.stories.length) return;
    activeStoryGroup.value = group;
    currentStoryIndex.value = Math.max(
        0,
        Math.min(startIndex, group.stories.length - 1),
    );
    isStoryOptionsOpen.value = false;
    isStoryViewerOpen.value = true;
    startStoryPlayback();
};

const closeStoryViewer = () => {
    stopStoryPlayback();
    isStoryViewerOpen.value = false;
    isStoryOptionsOpen.value = false;
    activeStoryGroup.value = null;
    currentStoryIndex.value = 0;
    storyProgress.value = 0;
    flyingReactions.value = [];
    featuredToast.value = null;
};

const startStoryPlayback = () => {
    stopStoryPlayback();
    storyProgress.value = 0;
    isStoryPaused.value = false;

    const story = currentStory.value;
    if (!story) return;

    // Record unique view via background API call
    axios.post(`/community/stories/${story.id}/view`).catch(() => {});

    const totalSeconds =
        story.duration_seconds || (story.media_type === 'video' ? 15 : 5);
    const tickInterval = 50; // ms
    const stepIncrement = (tickInterval / (totalSeconds * 1000)) * 100;

    storyTimer = setInterval(() => {
        if (isStoryPaused.value) return;

        storyProgress.value += stepIncrement;
        if (storyProgress.value >= 100) {
            storyProgress.value = 100;
            goToNextStory();
        }
    }, tickInterval);
};

const stopStoryPlayback = () => {
    if (storyTimer) {
        clearInterval(storyTimer);
        storyTimer = null;
    }
};

const goToNextStory = () => {
    if (!activeStoryGroup.value) return;

    flyingReactions.value = [];
    isStoryOptionsOpen.value = false;
    if (currentStoryIndex.value < activeStoryGroup.value.stories.length - 1) {
        currentStoryIndex.value++;
        startStoryPlayback();
    } else {
        const groups = allActiveStoryGroups.value;
        const currentGroupIdx = groups.findIndex(
            (g) => g.user_id === activeStoryGroup.value?.user_id,
        );
        if (currentGroupIdx !== -1 && currentGroupIdx < groups.length - 1) {
            activeStoryGroup.value = groups[currentGroupIdx + 1];
            currentStoryIndex.value = 0;
            startStoryPlayback();
        } else {
            closeStoryViewer();
        }
    }
};

const goToPreviousStory = () => {
    if (!activeStoryGroup.value) return;

    flyingReactions.value = [];
    isStoryOptionsOpen.value = false;
    if (currentStoryIndex.value > 0) {
        currentStoryIndex.value--;
        startStoryPlayback();
    } else {
        const groups = allActiveStoryGroups.value;
        const currentGroupIdx = groups.findIndex(
            (g) => g.user_id === activeStoryGroup.value?.user_id,
        );
        if (currentGroupIdx > 0) {
            const prevGroup = groups[currentGroupIdx - 1];
            activeStoryGroup.value = prevGroup;
            currentStoryIndex.value = prevGroup.stories.length - 1;
            startStoryPlayback();
        } else {
            startStoryPlayback();
        }
    }
};

const pauseStory = () => {
    isStoryPaused.value = true;
    if (storyVideoRef.value && !storyVideoRef.value.paused) {
        storyVideoRef.value.pause();
    }
};

const resumeStory = () => {
    isStoryPaused.value = false;
    if (storyVideoRef.value && storyVideoRef.value.paused) {
        storyVideoRef.value.play().catch(() => {});
    }
};

const togglePlayPause = () => {
    if (isStoryPaused.value) {
        resumeStory();
    } else {
        pauseStory();
    }
};

const isStoryOptionsOpen = ref(false);

const toggleStoryOptions = () => {
    isStoryOptionsOpen.value = !isStoryOptionsOpen.value;
    if (isStoryOptionsOpen.value) {
        pauseStory();
    } else {
        resumeStory();
    }
};

const handleDeleteStory = async () => {
    const story = currentStory.value;
    if (!story || isDeletingStory.value) return;

    isDeletingStory.value = true;
    try {
        await axios.delete(`/community/stories/${story.id}`);
        if (activeStoryGroup.value) {
            activeStoryGroup.value.stories =
                activeStoryGroup.value.stories.filter((s) => s.id !== story.id);
            if (activeStoryGroup.value.stories.length === 0) {
                closeStoryViewer();
            } else {
                if (
                    currentStoryIndex.value >=
                    activeStoryGroup.value.stories.length
                ) {
                    currentStoryIndex.value =
                        activeStoryGroup.value.stories.length - 1;
                }
                startStoryPlayback();
            }
        }
        router.reload({ only: ['stories'] });
    } catch {
        // keep view open
    } finally {
        isDeletingStory.value = false;
    }
};

const handleStoryKeydown = (e: KeyboardEvent) => {
    if (isLightboxOpen.value) {
        if (e.key === 'Escape') {
            closePostLightbox();
            return;
        }
        if (e.key === 'ArrowLeft') {
            lightboxPrevMedia();
            return;
        }
        if (e.key === 'ArrowRight') {
            lightboxNextMedia();
            return;
        }
    }
    if (isMediaModalOpen.value && e.key === 'Escape') {
        closeMediaModal();
        return;
    }
    if (selectedTrendingTopic.value && e.key === 'Escape') {
        closeTrendingTopicModal();
        return;
    }
    if (isWelcomeModalOpen.value && e.key === 'Escape') {
        dismissWelcomeModal();
        return;
    }
    if (!isStoryViewerOpen.value) return;
    if (e.key === 'Escape') {
        if (isStoryOptionsOpen.value) {
            isStoryOptionsOpen.value = false;
            resumeStory();
            return;
        }
        closeStoryViewer();
    } else if (e.key === 'ArrowLeft') {
        goToPreviousStory();
    } else if (e.key === 'ArrowRight') {
        goToNextStory();
    } else if (e.key === ' ') {
        e.preventDefault();
        if (isStoryPaused.value) {
            resumeStory();
        } else {
            pauseStory();
        }
    }
};

// ─── Instagram Profile Preview Modal ─────────────────────────────────────────
export interface ProfileModalData {
    is_masked: boolean;
    is_self?: boolean;
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
    followers_count?: number;
    following_count?: number;
    posts_count?: number;
    likes_received?: number;
    has_active_stories?: boolean;
    recent_posts?: Array<{
        id: number | string;
        content: string;
        created_at: string;
        likes_count: number;
        comments_count: number;
    }>;
}

const isProfileModalOpen = ref(false);
const isLoadingProfile = ref(false);
const activeProfile = ref<ProfileModalData | null>(null);

const profileStoryGroup = computed<UserStoryGroup | null>(() => {
    if (!activeProfile.value) return null;
    return (
        allActiveStoryGroups.value.find((g) => {
            if (
                activeProfile.value?.public_id &&
                g.public_id === activeProfile.value.public_id
            ) {
                return true;
            }
            if (
                g.name &&
                activeProfile.value?.name &&
                g.name.trim().toLowerCase() ===
                    activeProfile.value.name.trim().toLowerCase()
            ) {
                return true;
            }
            return false;
        }) ?? null
    );
});

const profileHasActiveStory = computed(() => {
    if (activeProfile.value?.is_masked) return false;
    return Boolean(
        (profileStoryGroup.value &&
            profileStoryGroup.value.stories.length > 0) ||
        activeProfile.value?.has_active_stories,
    );
});

const isDesktopLayout = (): boolean => {
    if (typeof window === 'undefined') return true;
    return window.innerWidth >= 1024;
};

const scrollFeedBy = (deltaY: number) => {
    if (!feedScrollRef.value) return;
    if (typeof feedScrollRef.value.scrollBy === 'function') {
        feedScrollRef.value.scrollBy({
            top: deltaY,
            behavior: 'auto',
        });
    } else {
        feedScrollRef.value.scrollTop += deltaY;
    }
};

const handleClassmatesWheel = (event: WheelEvent) => {
    const el = event.currentTarget as HTMLElement | null;
    if (!el) return;

    const canScrollDown = el.scrollTop + el.clientHeight < el.scrollHeight - 1;
    const canScrollUp = el.scrollTop > 0;

    if (
        (event.deltaY > 0 && canScrollDown) ||
        (event.deltaY < 0 && canScrollUp)
    ) {
        if (typeof el.scrollBy === 'function') {
            el.scrollBy({ top: event.deltaY, behavior: 'auto' });
        } else {
            el.scrollTop += event.deltaY;
        }
    }
    // Isolate statuses scrolling: never bubble to sidebar when hovering statuses list
    event.preventDefault();
    event.stopPropagation();
};

const handleStoriesTrayWheel = (event: WheelEvent) => {
    const el = event.currentTarget as HTMLElement | null;
    if (!el) return;

    const delta =
        Math.abs(event.deltaX) > Math.abs(event.deltaY)
            ? event.deltaX
            : event.deltaY;

    if (delta === 0) return;

    const canScrollRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 1;
    const canScrollLeft = el.scrollLeft > 0;

    if ((delta > 0 && canScrollRight) || (delta < 0 && canScrollLeft)) {
        if (typeof el.scrollBy === 'function') {
            el.scrollBy({ left: delta, behavior: 'auto' });
        } else {
            el.scrollLeft += delta;
        }
    }

    // Isolate horizontal stories tray scrolling: never scroll the vertical feed when hovering the tray
    event.preventDefault();
    event.stopPropagation();
};

const handleLayoutWheel = (event: WheelEvent) => {
    if (!isDesktopLayout()) return;

    const target = event.target as HTMLElement | null;
    if (target?.closest('[data-test="stories-tray"]')) {
        return;
    }
    if (target?.closest('[data-test="classmates-list"]')) {
        return;
    }
    if (target?.closest('[data-test="community-feed-scroll"]')) {
        return;
    }
    if (target?.closest('[data-test="community-right-sidebar"]')) {
        return;
    }
    if (
        target?.closest(
            '[role="dialog"], [data-test="section-dropdown-menu"], [data-test="mobile-presence-drawer"]',
        )
    ) {
        return;
    }

    scrollFeedBy(event.deltaY);
    event.preventDefault();
};

const handleProfileAvatarClick = () => {
    if (profileStoryGroup.value && profileStoryGroup.value.stories.length > 0) {
        isProfileModalOpen.value = false;
        openStoryViewer(profileStoryGroup.value, 0);
    }
};

const openProfileModal = async (userRef: {
    public_id?: string | null;
    name?: string;
    is_masked?: boolean;
    avatar?: string | null;
    handle?: string;
    section_name?: string | null;
}) => {
    const isSelfUser =
        (Boolean(currentUser.value?.public_id) &&
            userRef.public_id === currentUser.value?.public_id) ||
        (userRef.name === currentUserName.value && !userRef.is_masked);

    // If masked student and NOT self: show identity shield modal
    if (!isSelfUser && (userRef.is_masked || !userRef.public_id)) {
        activeProfile.value = {
            is_masked: true,
            is_self: false,
            name: userRef.name || 'Student',
            handle: userRef.handle || 'student.masked',
            avatar: null,
            bio: 'This student posted anonymously or belongs to another section. Real identity details are shielded to protect classroom privacy.',
            section_name: userRef.section_name || 'Other Section',
            can_follow: false,
            is_following: false,
            followers_count: 0,
            following_count: 0,
            posts_count: 0,
            likes_received: 0,
            recent_posts: [],
        };
        isProfileModalOpen.value = true;
        return;
    }

    // Unmasked classmate or self: fetch profile preview
    isLoadingProfile.value = true;
    isProfileModalOpen.value = true;

    const targetPublicId =
        userRef.public_id || (isSelfUser ? currentUser.value?.public_id : null);

    // Optimistic fallback while fetching
    activeProfile.value = {
        is_masked: false,
        is_self: isSelfUser,
        public_id: targetPublicId,
        name: isSelfUser ? currentUserName.value : userRef.name || 'Classmate',
        handle: isSelfUser
            ? currentUserHandle.value
            : userRef.handle || strToHandle(userRef.name || 'classmate'),
        avatar:
            userRef.avatar || (isSelfUser ? currentUser.value?.avatar : null),
        bio: '',
        level: 1,
        section_name: userRef.section_name || sectionDisplayName.value,
        can_follow: !isSelfUser,
        is_following: false,
        followers_count: 0,
        following_count: 0,
        posts_count: 0,
        likes_received: 0,
        recent_posts: [],
    };

    if (!targetPublicId) {
        isLoadingProfile.value = false;
        return;
    }

    try {
        const res = await fetch(`/community/users/${targetPublicId}/preview`, {
            headers: { Accept: 'application/json' },
        });
        if (res.ok) {
            const data = await res.json();
            activeProfile.value = {
                ...activeProfile.value,
                ...data,
                avatar:
                    data.avatar ||
                    activeProfile.value?.avatar ||
                    userRef.avatar,
                has_active_stories:
                    data.has_active_stories ??
                    activeProfile.value?.has_active_stories,
            };
        }
    } catch {
        // Keep optimistic data
    } finally {
        isLoadingProfile.value = false;
    }
};

const handleToggleFollow = () => {
    if (!activeProfile.value?.public_id || !activeProfile.value.can_follow) {
        return;
    }

    const publicId = activeProfile.value.public_id;
    const currentlyFollowing = activeProfile.value.is_following ?? false;

    // Optimistic UI toggle
    activeProfile.value.is_following = !currentlyFollowing;
    activeProfile.value.followers_count = Math.max(
        0,
        (activeProfile.value.followers_count ?? 0) +
            (currentlyFollowing ? -1 : 1),
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
            { preserveScroll: true, preserveState: true },
        );
    }
};

// ─── Mobile Drawer State ─────────────────────────────────────────────────────
const isMobileDrawerOpen = ref(false);

watch(
    [
        isMobileDrawerOpen,
        isProfileModalOpen,
        isStoryViewerOpen,
        isStoryModalOpen,
        isMediaModalOpen,
        isLightboxOpen,
        isWelcomeModalOpen,
        postToDelete,
        commentToDelete,
    ],
    ([
        drawerOpen,
        modalOpen,
        storyViewerOpen,
        storyModalOpen,
        mediaModalOpen,
        lightboxOpen,
        welcomeOpen,
        postDeleteOpen,
        commentDeleteOpen,
    ]) => {
        if (typeof document !== 'undefined') {
            if (
                drawerOpen ||
                modalOpen ||
                storyViewerOpen ||
                storyModalOpen ||
                mediaModalOpen ||
                lightboxOpen ||
                welcomeOpen ||
                Boolean(postDeleteOpen) ||
                Boolean(commentDeleteOpen)
            ) {
                document.body.style.overflow = 'hidden';
            } else {
                document.body.style.overflow = '';
            }
        }
    },
);

const handleDocumentClick = (e: MouseEvent) => {
    if (isSectionDropdownOpen.value) {
        const target = e.target as HTMLElement | null;
        if (
            target &&
            !target.closest('[data-test="section-dropdown-menu"]') &&
            !target.closest('[data-test="section-dropdown-trigger"]')
        ) {
            isSectionDropdownOpen.value = false;
        }
    }
    if (activePostMenuId.value) {
        const target = e.target as HTMLElement | null;
        if (
            target &&
            !target.closest('[data-test="post-options-menu"]') &&
            !target.closest('[data-test="post-options-button"]')
        ) {
            activePostMenuId.value = null;
        }
    }
    if (isStoryOptionsOpen.value) {
        const target = e.target as HTMLElement | null;
        if (
            target &&
            !target.closest('[data-test="viewer-story-options-menu"]') &&
            !target.closest('[data-test="viewer-story-options-button"]')
        ) {
            isStoryOptionsOpen.value = false;
            resumeStory();
        }
    }
    if (isComposerSectionDropdownOpen.value) {
        const target = e.target as HTMLElement | null;
        if (
            target &&
            !target.closest('[data-test="composer-modal-section-menu"]') &&
            !target.closest('[data-test="composer-modal-section-trigger"]')
        ) {
            isComposerSectionDropdownOpen.value = false;
        }
    }
    if (isInlineSectionDropdownOpen.value) {
        const target = e.target as HTMLElement | null;
        if (
            target &&
            !target.closest('[data-test="inline-composer-section-menu"]') &&
            !target.closest('[data-test="inline-composer-section-trigger"]')
        ) {
            isInlineSectionDropdownOpen.value = false;
        }
    }
};

if (typeof window !== 'undefined') {
    window.addEventListener('keydown', handleStoryKeydown);
    window.addEventListener('click', handleDocumentClick);
}

onBeforeUnmount(() => {
    clearStoryMedia();
    stopStoryPlayback();
    revokeMediaPreviews();
    stopMusicPreview();
    stopFeedPostMusic();
    if (typeof window !== 'undefined') {
        window.removeEventListener('keydown', handleStoryKeydown);
        window.removeEventListener('click', handleDocumentClick);
    }
    if (typeof document !== 'undefined') {
        document.body.style.overflow = '';
    }
});

// ─── Trending Topics & Related Posts Modal ───────────────────────────────────
const defaultTrendingTopics: TrendingTopic[] = [
    { tag: 'midterm-exam', name: 'MidtermExam', count: 28 },
    { tag: 'networking-lab', name: 'NetworkingLab', count: 14 },
    { tag: 'review-notes', name: 'ReviewNotes', count: 9 },
    { tag: 'dijkstra', name: 'DijkstraAlgorithm', count: 6 },
    { tag: 'study-group', name: 'StudyGroup', count: 4 },
];

const computeTrendingFromPosts = (
    postsList: CommunityFeedPost[],
): TrendingTopic[] => {
    const tagCounts: Record<string, number> = {};
    const tagDisplayNames: Record<string, string> = {};

    for (const post of postsList) {
        const content = post.content || '';
        const matches = content.matchAll(/#([a-zA-Z0-9_]+)/g);
        const seenInPost = new Set<string>();
        for (const match of matches) {
            const rawTag = match[1];
            if (!rawTag) continue;
            const lower = rawTag.toLowerCase();
            if (seenInPost.has(lower)) continue;
            seenInPost.add(lower);
            tagCounts[lower] = (tagCounts[lower] ?? 0) + 1;
            if (!tagDisplayNames[lower]) {
                tagDisplayNames[lower] = rawTag;
            }
        }
    }

    return Object.entries(tagCounts)
        .sort((a, b) => b[1] - a[1])
        .slice(0, 5)
        .map(([lowerTag, count]) => ({
            tag: lowerTag,
            name: tagDisplayNames[lowerTag] || lowerTag,
            count,
        }));
};

const trendingTopics = computed<TrendingTopic[]>(() => {
    if (props.posts !== undefined) {
        return computeTrendingFromPosts(displayPosts.value);
    }
    if (props.trendingTopics !== undefined) {
        return props.trendingTopics;
    }
    return defaultTrendingTopics;
});

const selectedTrendingTopic = ref<TrendingTopic | null>(null);

const postContainsHashtag = (
    post: CommunityFeedPost,
    tagOrName: string,
): boolean => {
    const content = post.content || '';
    const target = tagOrName.replace(/^#/, '').toLowerCase();
    if (!target) return false;
    const matches = content.matchAll(/#([a-zA-Z0-9_]+)/g);
    for (const match of matches) {
        if (match[1] && match[1].toLowerCase() === target) {
            return true;
        }
    }
    return false;
};

const trendingRelatedPosts = computed<CommunityFeedPost[]>(() => {
    if (!selectedTrendingTopic.value) {
        return [];
    }
    const targetTag =
        selectedTrendingTopic.value.tag || selectedTrendingTopic.value.name;
    return displayPosts.value.filter((post) =>
        postContainsHashtag(post, targetTag),
    );
});

const openTrendingTopicModal = (topic: TrendingTopic) => {
    selectedTrendingTopic.value = topic;
};

const closeTrendingTopicModal = () => {
    selectedTrendingTopic.value = null;
};

const jumpToPostFromTrending = (post: CommunityFeedPost) => {
    closeTrendingTopicModal();
    expandedComments.value[String(post.id)] = true;
    nextTick(() => {
        if (typeof document !== 'undefined') {
            const el = document.getElementById(`community-post-${post.id}`);
            if (el && typeof el.scrollIntoView === 'function') {
                el.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
        }
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs" :hide-sidebar="isStandalone">
        <Head title="Community Lounge" />

        <div
            class="relative min-h-[calc(100vh-4rem)] [scrollbar-width:none] scrollbar-none p-2.5 [-ms-overflow-style:none] sm:p-3.5 lg:h-[calc(100dvh-5rem)] lg:min-h-0 lg:overflow-hidden lg:p-4 [&::-webkit-scrollbar]:hidden"
            data-test="community-layout"
            data-lenis-prevent
            @wheel="handleLayoutWheel"
        >
            <div
                class="mx-auto max-w-6xl lg:flex lg:h-full lg:min-h-0 lg:flex-col"
            >
                <!-- ═══ Instagram / Threads Clean Navigation Bar ═══ -->
                <!-- ─── Minimal Header ─────────────────────────────────────────────── -->
                <header
                    class="flex shrink-0 flex-wrap items-center justify-between gap-3 border-b border-border/50 pb-2.5 sm:pb-3"
                >
                    <div class="flex items-center gap-3">
                        <button
                            v-if="!isStandalone"
                            type="button"
                            data-test="community-back-button"
                            class="flex items-center gap-1.5 rounded-xl border border-border/70 bg-card/80 px-3 py-1.5 text-xs font-semibold text-foreground shadow-2xs backdrop-blur-xs transition hover:bg-muted"
                            @click="handleReturn"
                        >
                            <ArrowLeft class="h-3.5 w-3.5" />
                            <span>Back to Dashboard</span>
                        </button>

                        <div
                            v-if="!isStandalone"
                            class="h-4 w-px bg-border/60"
                        />

                        <div class="flex items-center gap-2">
                            <h1
                                class="text-base font-bold tracking-tight text-foreground sm:text-lg"
                            >
                                Community Lounge
                            </h1>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <CommunityInstallPrompt
                            v-if="props.pwaEnabled"
                            :enabled="props.pwaEnabled"
                        />

                        <div
                            v-if="isStandalone"
                            class="flex items-center gap-1.5"
                        >
                            <button
                                type="button"
                                data-test="community-standalone-logout"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border/70 bg-card/80 px-2.5 py-1.5 text-xs font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                title="Log out"
                                @click="showLogoutModal = true"
                            >
                                <LogOut
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                                <span class="hidden sm:inline">Log out</span>
                            </button>
                            <button
                                type="button"
                                data-test="community-standalone-exit"
                                class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-border/70 bg-card/80 px-2.5 py-1.5 text-xs font-semibold text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                title="Open Main Portal"
                                @click="handleReturn"
                            >
                                <ExternalLink
                                    class="h-3.5 w-3.5"
                                    aria-hidden="true"
                                />
                                <span class="hidden sm:inline">Portal</span>
                            </button>
                        </div>
                    </div>
                </header>

                <!-- ═══ 2-Column Social Feed Layout ═══ -->
                <div
                    class="grid grid-cols-1 items-start gap-4 lg:min-h-0 lg:flex-1 lg:grid-cols-12 lg:grid-rows-1 lg:items-stretch lg:gap-5 xl:gap-6"
                >
                    <!-- Main Feed Column (col-span-7 or 8 on desktop) -->
                    <main
                        ref="feedScrollRef"
                        class="min-w-0 [scrollbar-width:none] scrollbar-none space-y-3.5 [-ms-overflow-style:none] sm:space-y-4 lg:col-span-7 lg:min-h-0 lg:overflow-y-auto lg:overscroll-y-contain lg:pr-1 xl:col-span-8 [&::-webkit-scrollbar]:hidden"
                        data-test="community-feed-scroll"
                        data-lenis-prevent
                        scroll-region
                        tabindex="0"
                        aria-label="Community feed"
                    >
                        <!-- ─── Instagram Top Stories Tray ──────────────────────────── -->
                        <section
                            v-if="props.storiesEnabled !== false"
                            aria-label="Classmate Stories"
                            data-test="stories-tray"
                            data-lenis-prevent
                            class="flex [scrollbar-width:none] scrollbar-none items-center gap-3 overflow-x-auto overscroll-x-contain rounded-xl border border-border/50 bg-card/60 p-2.5 pb-2 shadow-2xs backdrop-blur-xs [-ms-overflow-style:none] [&::-webkit-scrollbar]:hidden"
                            @wheel="handleStoriesTrayWheel"
                        >
                            <!-- Story Item -->
                            <div
                                v-for="story in storyTrayItems"
                                :key="story.id"
                                class="group flex shrink-0 cursor-pointer flex-col items-center gap-1 select-none"
                                @click="handleStoryClick(story)"
                            >
                                <div class="relative">
                                    <!-- Story Ring: Instagram gradient for active stories -->
                                    <div
                                        class="flex h-11 w-11 items-center justify-center rounded-full transition-transform duration-200 group-hover:scale-105 active:scale-95 sm:h-12 sm:w-12"
                                        :class="[
                                            story.isUser
                                                ? story.hasStory
                                                    ? 'bg-gradient-to-tr from-brand via-[#e6a875] to-[#f5eedc] p-[2px]'
                                                    : 'border-2 border-dashed border-border/80 p-0.5'
                                                : story.hasStory
                                                  ? 'bg-gradient-to-tr from-brand via-[#e6a875] to-[#f5eedc] p-[2px]'
                                                  : 'border-2 border-border/70 p-0.5',
                                        ]"
                                    >
                                        <!-- Inner Avatar Circle -->
                                        <div
                                            class="flex h-full w-full items-center justify-center overflow-hidden rounded-full border-2 border-background text-xs font-bold"
                                            :class="
                                                story.isUser && !story.hasStory
                                                    ? 'bg-brand/15 text-brand'
                                                    : 'bg-muted text-foreground'
                                            "
                                        >
                                            <!-- User active story thumbnail preview -->
                                            <template
                                                v-if="
                                                    story.isUser &&
                                                    story.hasStory &&
                                                    story.storyGroup?.stories
                                                        ?.length
                                                "
                                            >
                                                <video
                                                    v-if="
                                                        story.storyGroup
                                                            .stories[
                                                            story.storyGroup
                                                                .stories
                                                                .length - 1
                                                        ].media_type === 'video'
                                                    "
                                                    :src="
                                                        story.storyGroup
                                                            .stories[
                                                            story.storyGroup
                                                                .stories
                                                                .length - 1
                                                        ].media_url
                                                    "
                                                    class="h-full w-full object-cover"
                                                    data-test="your-story-preview-video"
                                                    preload="metadata"
                                                    muted
                                                />
                                                <img
                                                    v-else
                                                    :src="
                                                        story.storyGroup
                                                            .stories[
                                                            story.storyGroup
                                                                .stories
                                                                .length - 1
                                                        ].media_url
                                                    "
                                                    :alt="story.name"
                                                    class="h-full w-full object-cover"
                                                    data-test="your-story-preview-image"
                                                />
                                            </template>
                                            <img
                                                v-else-if="story.avatar"
                                                :src="story.avatar"
                                                :alt="story.name"
                                                class="h-full w-full object-cover"
                                            />
                                            <span v-else>{{
                                                story.initials
                                            }}</span>
                                        </div>
                                    </div>

                                    <!-- Plus badge on user story -->
                                    <button
                                        v-if="story.isUser"
                                        type="button"
                                        class="absolute -right-0.5 -bottom-0.5 flex h-3.5 w-3.5 cursor-pointer items-center justify-center rounded-full bg-brand text-[9px] font-bold text-primary-foreground shadow-xs transition-transform hover:scale-110 active:scale-95"
                                        title="Add to story"
                                        @click.stop="openStoryModal"
                                    >
                                        <Plus class="h-2 w-2 stroke-[3]" />
                                    </button>

                                    <!-- Online Green Beacon on classmates -->
                                    <span
                                        v-else-if="story.status === 'online'"
                                        class="absolute right-0.5 bottom-0.5 flex h-2.5 w-2.5 items-center justify-center rounded-full border-2 border-background bg-emerald-500 shadow-2xs"
                                        title="Active now"
                                    />
                                </div>

                                <span
                                    class="max-w-[54px] truncate text-[10px] font-medium tracking-tight text-muted-foreground group-hover:text-foreground"
                                >
                                    {{ story.name }}
                                </span>
                            </div>
                        </section>

                        <!-- ─── Threads Style Feed Filter Tabs ─────────────────────── -->
                        <div
                            class="flex items-center justify-around gap-1 border-b border-border/40 text-xs font-semibold sm:justify-start sm:gap-6"
                        >
                            <button
                                type="button"
                                data-test="community-tab-all"
                                class="group relative flex cursor-pointer items-center gap-1.5 py-2.5 transition-colors"
                                :class="
                                    activeTab === 'all'
                                        ? 'text-brand'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="handleTabChange('all')"
                            >
                                <FileText
                                    class="h-3.5 w-3.5 transition-colors group-hover:text-foreground"
                                    :class="
                                        activeTab === 'all'
                                            ? 'text-brand'
                                            : 'text-muted-foreground/70 group-hover:text-foreground'
                                    "
                                />
                                <span>Feed</span>
                                <span
                                    v-if="activeTab === 'all'"
                                    class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-brand"
                                />
                            </button>

                            <button
                                v-if="!props.isSectionOnly"
                                type="button"
                                data-test="community-tab-section"
                                class="group relative flex cursor-pointer items-center gap-1.5 py-2.5 transition-colors"
                                :class="
                                    activeTab === 'section'
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="handleTabChange('section')"
                            >
                                <Users
                                    class="h-3.5 w-3.5 transition-colors group-hover:text-foreground"
                                    :class="
                                        activeTab === 'section'
                                            ? 'text-emerald-600 dark:text-emerald-400'
                                            : 'text-muted-foreground/70 group-hover:text-foreground'
                                    "
                                />
                                <span>My Section</span>
                                <span
                                    v-if="activeTab === 'section'"
                                    class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-emerald-500"
                                />
                            </button>

                            <button
                                type="button"
                                data-test="community-tab-saved"
                                class="group relative flex cursor-pointer items-center gap-1.5 py-2.5 transition-colors"
                                :class="
                                    activeTab === 'saved'
                                        ? 'text-amber-600 dark:text-amber-400'
                                        : 'text-muted-foreground hover:text-foreground'
                                "
                                @click="handleTabChange('saved')"
                            >
                                <Bookmark
                                    class="h-3.5 w-3.5 transition-colors group-hover:text-foreground"
                                    :class="
                                        activeTab === 'saved'
                                            ? 'fill-amber-500 text-amber-600 dark:text-amber-400'
                                            : 'text-muted-foreground/70 group-hover:text-foreground'
                                    "
                                />
                                <span>Saved</span>
                                <span
                                    v-if="activeTab === 'saved'"
                                    class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-amber-500"
                                />
                            </button>

                            <!-- Mobile Classroom Presence Button in Tabs row -->
                            <button
                                type="button"
                                data-test="mobile-presence-drawer-trigger"
                                class="group relative flex cursor-pointer items-center gap-1.5 py-2.5 transition-colors lg:hidden"
                                :class="
                                    isMobileDrawerOpen
                                        ? 'text-emerald-600 dark:text-emerald-400'
                                        : 'text-muted-foreground hover:text-emerald-600 dark:hover:text-emerald-400'
                                "
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
                                <span
                                    v-if="isMobileDrawerOpen"
                                    class="absolute inset-x-0 bottom-0 h-0.5 rounded-full bg-emerald-500"
                                />
                            </button>

                            <!-- Hidden Own Posts Trigger -->
                            <button
                                v-if="localHiddenOwnPosts.length > 0"
                                type="button"
                                data-test="open-hidden-posts-modal-button"
                                class="ml-auto inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-border/60 bg-muted/40 px-2.5 py-1 text-[11px] font-medium text-muted-foreground transition hover:border-border hover:bg-muted hover:text-foreground"
                                @click="isHiddenPostsModalOpen = true"
                            >
                                <EyeOff class="h-3 w-3" />
                                <span
                                    >Hidden ({{
                                        localHiddenOwnPosts.length
                                    }})</span
                                >
                            </button>
                        </div>

                        <!-- ─── Feed Container ─────────────────────────────────────── -->
                        <div
                            class="space-y-3.5 sm:space-y-4"
                            data-test="community-feed-container"
                        >
                            <!-- Instagram / Threads Composer Row -->
                            <div
                                class="rounded-xl border border-border/60 bg-card p-3 shadow-2xs transition-colors hover:border-border"
                            >
                                <div class="flex items-start gap-2.5">
                                    <!-- User Avatar -->
                                    <div
                                        class="flex h-8 w-8 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/80 bg-brand/15 text-xs font-bold text-brand select-none"
                                    >
                                        <img
                                            v-if="currentUser?.avatar"
                                            :src="currentUser.avatar"
                                            :alt="currentUserName"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else>
                                            {{ currentUserInitials }}
                                        </span>
                                    </div>

                                    <!-- Input Row -->
                                    <div class="min-w-0 flex-1">
                                        <div
                                            v-if="
                                                !isComposerOpen &&
                                                !composerForm.content
                                            "
                                            class="w-full cursor-text rounded-full border border-border/60 bg-muted/40 px-4 py-2 text-xs text-muted-foreground transition-all hover:border-border hover:bg-muted/60"
                                            @click="isComposerOpen = true"
                                        >
                                            Share learning tips, ask questions,
                                            or start a discussion...
                                        </div>

                                        <div v-else class="space-y-2">
                                            <textarea
                                                v-model="composerForm.content"
                                                rows="2"
                                                placeholder="Share learning tips, ask questions, or start a discussion..."
                                                class="w-full resize-none rounded-xl border border-border/60 bg-muted/30 px-3.5 py-2.5 text-xs text-foreground placeholder:text-muted-foreground/70 focus:border-foreground/30 focus:bg-background focus:outline-none"
                                            />
                                        </div>

                                        <!-- Attachment Preview if selected -->
                                        <div
                                            v-if="composerFile"
                                            class="mt-2.5 flex items-center justify-between rounded-xl border border-border/60 bg-muted/40 p-2 text-xs"
                                        >
                                            <div
                                                class="flex min-w-0 items-center gap-2"
                                            >
                                                <img
                                                    v-if="composerFilePreview"
                                                    :src="composerFilePreview"
                                                    alt="Preview"
                                                    class="h-10 w-10 rounded-lg object-cover"
                                                />
                                                <FileText
                                                    v-else
                                                    class="h-6 w-6 text-brand"
                                                />
                                                <span
                                                    class="truncate font-medium text-foreground"
                                                >
                                                    {{ composerFile.name }}
                                                </span>
                                            </div>
                                            <button
                                                type="button"
                                                class="cursor-pointer text-muted-foreground hover:text-foreground"
                                                @click="clearComposerFile"
                                            >
                                                <X class="h-4 w-4" />
                                            </button>
                                        </div>

                                        <!-- Hidden file input for documents/code -->
                                        <input
                                            ref="fileInputRef"
                                            type="file"
                                            class="hidden"
                                            @change="handleFileSelect"
                                        />

                                        <!-- Hidden file input for photos/videos -->
                                        <input
                                            ref="mediaFileInputRef"
                                            type="file"
                                            accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
                                            multiple
                                            class="hidden"
                                            data-test="media-post-file-input"
                                            @change="handleMediaFileInputChange"
                                        />

                                        <!-- Composer Action Tray -->
                                        <div
                                            :class="[
                                                'mt-3 items-center gap-x-2 gap-y-2 border-t border-border/30 pt-2.5 text-xs sm:flex sm:items-center sm:justify-between',
                                                props.allowsAnonymous === false
                                                    ? 'flex justify-between'
                                                    : 'grid grid-cols-[1fr_auto]',
                                            ]"
                                        >
                                            <!-- Media Triggers (Photo, Music, Code) -->
                                            <div
                                                class="flex shrink-0 items-center gap-1 text-muted-foreground sm:gap-1.5"
                                            >
                                                <!-- Photo / Video Trigger (Instagram / Facebook / TikTok Style Modal) -->
                                                <button
                                                    v-if="
                                                        props.mediaUploadsEnabled !==
                                                        false
                                                    "
                                                    type="button"
                                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full px-2 py-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                    title="Attach photos or videos"
                                                    data-test="open-media-modal-button"
                                                    @click="
                                                        mediaFileInputRef?.click()
                                                    "
                                                >
                                                    <ImageIcon
                                                        class="h-3.5 w-3.5 text-brand"
                                                    />
                                                    <span
                                                        class="hidden text-[11px] font-medium sm:inline"
                                                        >Photo/Video</span
                                                    >
                                                </button>

                                                <!-- Music Soundtrack Trigger -->
                                                <button
                                                    v-if="
                                                        props.musicEnabled !==
                                                            false &&
                                                        props.musicTracks &&
                                                        props.musicTracks
                                                            .length > 0
                                                    "
                                                    type="button"
                                                    class="inline-flex cursor-pointer items-center gap-1 rounded-full px-2 py-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                                                    title="Add soundtrack"
                                                    data-test="open-music-modal-button"
                                                    @click="
                                                        isMediaModalOpen = true;
                                                        isMusicPickerOpen = true;
                                                    "
                                                >
                                                    <Music
                                                        class="h-3.5 w-3.5 text-amber-500"
                                                    />
                                                    <span
                                                        class="hidden text-[11px] font-medium sm:inline"
                                                        >Music</span
                                                    >
                                                </button>

                                                <!-- Code / File Trigger -->
                                                <button
                                                    v-if="
                                                        props.fileAttachmentsEnabled !==
                                                        false
                                                    "
                                                    type="button"
                                                    class="inline-flex h-7 w-7 cursor-pointer items-center justify-center rounded-full text-muted-foreground hover:bg-muted hover:text-foreground"
                                                    title="Attach document or code snippet"
                                                    data-test="open-code-snippet-button"
                                                    @click="
                                                        fileInputRef?.click()
                                                    "
                                                >
                                                    <Code2
                                                        class="h-3.5 w-3.5"
                                                    />
                                                </button>
                                            </div>

                                            <!-- Post Button & Privacy info (Right on mobile line 1, end on desktop) -->
                                            <div
                                                class="order-last flex shrink-0 items-center justify-end gap-2 sm:order-last"
                                            >
                                                <span
                                                    :class="[
                                                        'text-[11px] whitespace-nowrap text-muted-foreground',
                                                        props.availableSections &&
                                                        props.availableSections
                                                            .length > 1
                                                            ? 'hidden lg:inline-flex'
                                                            : 'hidden sm:inline-flex',
                                                    ]"
                                                >
                                                    {{
                                                        composerForm.is_anonymous
                                                            ? 'Identity hidden from everyone'
                                                            : props.isSectionOnly
                                                              ? 'Visible only to your section'
                                                              : 'Identity masked outside section'
                                                    }}
                                                </span>
                                                <button
                                                    type="button"
                                                    :disabled="
                                                        (!composerForm.content.trim() &&
                                                            !composerForm.attachment) ||
                                                        composerForm.processing
                                                    "
                                                    class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-full bg-foreground px-4 py-1.5 text-xs font-semibold text-background transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                                                    @click="submitPost"
                                                >
                                                    <Loader2
                                                        v-if="
                                                            composerForm.processing
                                                        "
                                                        class="h-3 w-3 animate-spin"
                                                    />
                                                    <span>Post</span>
                                                </button>
                                            </div>

                                            <!-- Post Options (Anonymous Toggle & Section Picker: Line 2 on mobile when anonymous toggle is active, inline on mobile when anonymous is disabled) -->
                                            <div
                                                v-if="
                                                    props.allowsAnonymous !==
                                                        false ||
                                                    (props.availableSections &&
                                                        props.availableSections
                                                            .length > 1)
                                                "
                                                :class="[
                                                    'flex items-center gap-2 sm:order-2 sm:col-span-1 sm:mr-auto sm:ml-1 sm:shrink-0 sm:flex-nowrap sm:justify-start sm:gap-2.5 sm:border-0 sm:pt-0',
                                                    props.allowsAnonymous ===
                                                    false
                                                        ? 'order-2 mr-auto ml-1 min-w-0 lg:hidden'
                                                        : 'col-span-2 flex-wrap justify-between border-t border-border/20 pt-1.5',
                                                ]"
                                            >
                                                <!-- Anonymous Toggle -->
                                                <label
                                                    v-if="
                                                        props.allowsAnonymous !==
                                                        false
                                                    "
                                                    class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 text-[11px] whitespace-nowrap text-muted-foreground hover:text-foreground"
                                                >
                                                    <input
                                                        v-model="
                                                            composerForm.is_anonymous
                                                        "
                                                        type="checkbox"
                                                        class="h-3 w-3 rounded border-border text-brand focus:ring-0"
                                                    />
                                                    <span
                                                        >Post anonymously</span
                                                    >
                                                </label>

                                                <!-- Section Picker for Multi-Section Students (Mobile/Tablet only; desktop uses Classroom Presence switcher) -->
                                                <div
                                                    v-if="
                                                        props.availableSections &&
                                                        props.availableSections
                                                            .length > 1
                                                    "
                                                    class="relative shrink-0 lg:hidden"
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex cursor-pointer items-center gap-1 rounded-full border border-border/70 bg-muted/40 px-2.5 py-0.5 text-[11px] font-medium whitespace-nowrap text-foreground transition hover:border-border hover:bg-muted"
                                                        data-test="inline-composer-section-trigger"
                                                        title="Choose posting section"
                                                        @click.stop="
                                                            isInlineSectionDropdownOpen =
                                                                !isInlineSectionDropdownOpen
                                                        "
                                                    >
                                                        <Users
                                                            class="h-3 w-3 shrink-0 text-brand"
                                                        />
                                                        <span
                                                            class="max-w-[120px] truncate sm:max-w-[130px]"
                                                            >{{
                                                                postingSectionName
                                                            }}</span
                                                        >
                                                        <ChevronDown
                                                            class="h-2.5 w-2.5 shrink-0 text-muted-foreground"
                                                        />
                                                    </button>

                                                    <div
                                                        v-if="
                                                            isInlineSectionDropdownOpen
                                                        "
                                                        :class="[
                                                            'absolute bottom-full z-50 mb-1 max-h-48 w-48 overflow-y-auto rounded-xl border border-border/80 bg-card p-1 shadow-2xl ring-1 ring-black/10',
                                                            props.allowsAnonymous ===
                                                            false
                                                                ? 'left-0'
                                                                : 'right-0 sm:right-auto sm:left-0',
                                                        ]"
                                                        data-test="inline-composer-section-menu"
                                                    >
                                                        <button
                                                            v-for="sec in props.availableSections"
                                                            :key="sec.id"
                                                            type="button"
                                                            class="flex w-full cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition hover:bg-muted"
                                                            :class="
                                                                sec.id ===
                                                                composerSectionId
                                                                    ? 'bg-brand/10 font-bold text-brand'
                                                                    : 'text-foreground'
                                                            "
                                                            @click="
                                                                selectSection(
                                                                    sec.id,
                                                                )
                                                            "
                                                        >
                                                            <span
                                                                class="truncate"
                                                                >{{
                                                                    sec.name
                                                                }}</span
                                                            >
                                                            <Check
                                                                v-if="
                                                                    sec.id ===
                                                                    composerSectionId
                                                                "
                                                                class="h-3.5 w-3.5 shrink-0 text-brand"
                                                            />
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- ─── Empty Feed State ───────────────────────────────────── -->
                            <div
                                v-if="displayPosts.length === 0"
                                class="flex flex-col items-center justify-center rounded-2xl border border-dashed border-border/80 bg-card/40 p-12 text-center"
                            >
                                <div
                                    class="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground"
                                >
                                    <MessageCircle class="h-6 w-6" />
                                </div>
                                <h3
                                    class="mt-4 text-sm font-semibold text-foreground"
                                >
                                    {{
                                        activeTab === 'saved'
                                            ? 'No saved posts yet'
                                            : activeTab === 'section'
                                              ? 'No posts in your section yet'
                                              : 'No posts yet'
                                    }}
                                </h3>
                                <p
                                    class="mt-1 max-w-sm text-xs text-muted-foreground"
                                >
                                    {{
                                        activeTab === 'saved'
                                            ? 'Posts you bookmark will appear here for easy reference.'
                                            : activeTab === 'section'
                                              ? 'Be the first from your section to share a question or update!'
                                              : 'The lounge is quiet. Share a question, study tip, or resource to start the conversation.'
                                    }}
                                </p>
                                <button
                                    v-if="activeTab !== 'saved'"
                                    type="button"
                                    class="mt-4 inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-brand px-3.5 py-1.5 text-xs font-semibold text-white shadow-2xs transition-opacity hover:opacity-90 active:scale-95"
                                    @click="isComposerOpen = true"
                                >
                                    <Plus class="h-3.5 w-3.5" />
                                    <span>Create Post</span>
                                </button>
                            </div>

                            <!-- ─── Post Cards List ────────────────────────────────────── -->
                            <article
                                v-for="post in displayPosts"
                                :id="'community-post-' + post.id"
                                :key="post.id"
                                class="overflow-hidden rounded-2xl border border-border/60 bg-card shadow-2xs transition-colors hover:border-border"
                            >
                                <!-- Post Header -->
                                <div
                                    class="flex items-center justify-between p-4 pb-3"
                                >
                                    <div class="flex items-center gap-2.5">
                                        <!-- Avatar -->
                                        <div
                                            class="relative shrink-0 cursor-pointer"
                                            @click="
                                                openProfileModal({
                                                    public_id:
                                                        post.author.public_id,
                                                    name: post.author.name,
                                                    handle: post.author.handle,
                                                    avatar:
                                                        post.author.avatar ||
                                                        (String(
                                                            post.author.id,
                                                        ) ===
                                                            String(
                                                                currentUser?.id,
                                                            ) &&
                                                        currentUser?.avatar
                                                            ? currentUser.avatar
                                                            : null),
                                                    is_masked:
                                                        post.author.is_masked,
                                                    section_name:
                                                        post.author
                                                            .section_name,
                                                })
                                            "
                                        >
                                            <div
                                                v-if="post.author.is_masked"
                                                class="flex h-10 w-10 items-center justify-center rounded-full border border-border/80 bg-muted text-xs font-semibold text-muted-foreground"
                                            >
                                                <EyeOff class="h-4 w-4" />
                                            </div>
                                            <div
                                                v-else
                                                class="flex h-10 w-10 items-center justify-center rounded-full bg-gradient-to-tr from-brand via-[#e6a875] to-[#f5eedc] p-[2px]"
                                            >
                                                <div
                                                    class="flex h-full w-full items-center justify-center overflow-hidden rounded-full border-2 border-card bg-emerald-500/10 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                                >
                                                    <img
                                                        v-if="
                                                            post.author
                                                                .avatar ||
                                                            (String(
                                                                post.author.id,
                                                            ) ===
                                                                String(
                                                                    currentUser?.id,
                                                                ) &&
                                                                currentUser?.avatar)
                                                        "
                                                        :src="
                                                            post.author
                                                                .avatar ||
                                                            currentUser?.avatar
                                                        "
                                                        :alt="post.author.name"
                                                        class="h-full w-full object-cover"
                                                    />
                                                    <span v-else>
                                                        {{
                                                            getInitials(
                                                                post.author
                                                                    .name,
                                                            )
                                                        }}
                                                    </span>
                                                </div>
                                            </div>
                                            <span
                                                v-if="!post.author.is_masked"
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
                                                    data-test="post-author-handle"
                                                    @click="
                                                        openProfileModal({
                                                            public_id:
                                                                post.author
                                                                    .public_id,
                                                            name: post.author
                                                                .name,
                                                            handle: post.author
                                                                .handle,
                                                            is_masked:
                                                                post.author
                                                                    .is_masked,
                                                            section_name:
                                                                post.author
                                                                    .section_name,
                                                        })
                                                    "
                                                >
                                                    {{
                                                        post.author.is_masked
                                                            ? post.author.name
                                                            : post.author.name
                                                    }}
                                                </span>
                                                <span
                                                    v-if="
                                                        !post.author
                                                            .is_masked &&
                                                        post.author.handle
                                                    "
                                                    class="text-[11px] text-muted-foreground"
                                                >
                                                    @{{ post.author.handle }}
                                                </span>
                                            </div>
                                            <p
                                                class="text-[11px] text-muted-foreground"
                                            >
                                                {{
                                                    post.author.is_masked
                                                        ? post.author
                                                              .section_name ||
                                                          'Anonymous'
                                                        : post.author
                                                              .section_name ||
                                                          sectionDisplayName
                                                }}
                                                · {{ post.created_at }}
                                            </p>
                                        </div>
                                    </div>

                                    <div class="relative">
                                        <button
                                            v-if="canManagePost(post)"
                                            type="button"
                                            class="cursor-pointer rounded-full p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                            aria-label="Post options"
                                            data-test="post-options-button"
                                            @click.stop="
                                                togglePostMenu(post.id)
                                            "
                                        >
                                            <MoreHorizontal class="h-4 w-4" />
                                        </button>
                                        <button
                                            v-else
                                            type="button"
                                            class="cursor-pointer rounded-full p-1.5 text-muted-foreground hover:bg-muted hover:text-foreground"
                                            aria-label="Post options"
                                            data-test="other-post-options-button"
                                            @click.stop="
                                                togglePostMenu(post.id)
                                            "
                                        >
                                            <MoreHorizontal class="h-4 w-4" />
                                        </button>
                                        <div
                                            v-if="
                                                activePostMenuId ===
                                                String(post.id)
                                            "
                                            class="absolute top-full right-0 z-20 mt-1 min-w-[130px] overflow-hidden rounded-xl border border-border/80 bg-popover/95 p-1 shadow-lg backdrop-blur-md"
                                            data-test="post-options-menu"
                                        >
                                            <button
                                                type="button"
                                                class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-foreground hover:bg-muted"
                                                data-test="hide-post-button"
                                                @click="hidePost(post)"
                                            >
                                                <EyeOff class="h-3.5 w-3.5" />
                                                <span>Hide post</span>
                                            </button>
                                            <button
                                                v-if="canManagePost(post)"
                                                type="button"
                                                class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-foreground hover:bg-muted"
                                                data-test="edit-post-button"
                                                @click="startEditPost(post)"
                                            >
                                                <Pencil class="h-3.5 w-3.5" />
                                                <span>Edit</span>
                                            </button>
                                            <button
                                                v-if="canManagePost(post)"
                                                type="button"
                                                class="flex w-full cursor-pointer items-center gap-2 rounded-lg px-2.5 py-1.5 text-xs text-destructive hover:bg-destructive/10"
                                                data-test="delete-post-button"
                                                @click="confirmDeletePost(post)"
                                            >
                                                <Trash2 class="h-3.5 w-3.5" />
                                                <span>Delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Post Text Content -->
                                <div class="px-4 pb-3">
                                    <div
                                        v-if="editingPostId === String(post.id)"
                                        class="space-y-3 rounded-xl border border-border/80 bg-muted/20 p-3"
                                        data-test="post-edit-form"
                                    >
                                        <textarea
                                            v-model="editingPostContent"
                                            rows="3"
                                            maxlength="2000"
                                            class="w-full resize-none rounded-lg border border-border/70 bg-background p-2.5 text-xs text-foreground focus:border-brand focus:outline-none sm:text-[13px]"
                                            placeholder="Edit your post..."
                                            data-test="post-edit-textarea"
                                        ></textarea>
                                        <div
                                            class="flex flex-wrap items-center justify-between gap-2"
                                        >
                                            <label
                                                class="flex cursor-pointer items-center gap-2 text-xs text-muted-foreground select-none hover:text-foreground"
                                            >
                                                <input
                                                    v-model="
                                                        editingPostIsAnonymous
                                                    "
                                                    type="checkbox"
                                                    class="h-3.5 w-3.5 rounded border-border text-brand focus:ring-brand"
                                                    data-test="post-edit-anonymous-checkbox"
                                                />
                                                <span>Post anonymously</span>
                                            </label>
                                            <div
                                                class="flex items-center gap-2"
                                            >
                                                <button
                                                    type="button"
                                                    class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground"
                                                    data-test="cancel-post-edit-button"
                                                    @click="cancelEditPost"
                                                >
                                                    Cancel
                                                </button>
                                                <button
                                                    type="button"
                                                    class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-brand px-3 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-brand/90 disabled:opacity-50"
                                                    :disabled="
                                                        isSavingPost ||
                                                        !editingPostContent.trim()
                                                    "
                                                    data-test="save-post-edit-button"
                                                    @click="saveEditPost(post)"
                                                >
                                                    <Loader2
                                                        v-if="isSavingPost"
                                                        class="h-3.5 w-3.5 animate-spin"
                                                    />
                                                    <span>Save</span>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    <p
                                        v-else
                                        class="text-xs leading-relaxed text-foreground/90 sm:text-[13px]"
                                        data-test="post-content"
                                    >
                                        {{ post.content }}
                                    </p>
                                </div>

                                <!-- Post Music Soundtrack (Instagram / TikTok Style) -->
                                <div
                                    v-if="
                                        props.musicEnabled !== false &&
                                        post.music
                                    "
                                    class="px-4 pb-2.5"
                                >
                                    <button
                                        type="button"
                                        class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-brand/35 bg-brand/10 px-3 py-1 text-xs font-semibold text-brand transition-all hover:bg-brand/20 active:scale-95"
                                        data-test="post-soundtrack-badge"
                                        @click="toggleFeedPostMusic(post)"
                                    >
                                        <Music
                                            v-if="
                                                activePlayingPostId === post.id
                                            "
                                            class="h-3.5 w-3.5 animate-pulse text-brand"
                                        />
                                        <Music
                                            v-else
                                            class="h-3.5 w-3.5 text-brand"
                                        />
                                        <span
                                            class="max-w-[200px] truncate sm:max-w-xs"
                                        >
                                            {{ post.music.title }} •
                                            {{ post.music.artist }}
                                        </span>
                                        <span
                                            class="rounded-full bg-brand/20 px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-brand uppercase"
                                        >
                                            {{
                                                activePlayingPostId === post.id
                                                    ? 'Pause'
                                                    : 'Play'
                                            }}
                                        </span>
                                    </button>
                                </div>

                                <!-- Post Media Bento Grid (Facebook / Social Style) -->
                                <div
                                    v-if="post.media && post.media.length > 0"
                                    class="px-4 pb-3"
                                    data-test="post-media-container"
                                >
                                    <!-- 1 Item (Full width) -->
                                    <div
                                        v-if="post.media.length === 1"
                                        class="overflow-hidden rounded-xl border border-border/50 bg-zinc-950/5 dark:bg-zinc-950/40"
                                    >
                                        <video
                                            v-if="
                                                post.media[0].type === 'video'
                                            "
                                            :src="post.media[0].url"
                                            controls
                                            class="max-h-[520px] w-full bg-black object-contain"
                                            data-test="post-media-video"
                                        />
                                        <img
                                            v-else
                                            :src="post.media[0].url"
                                            :alt="
                                                post.media[0].name ||
                                                post.content ||
                                                'Post media'
                                            "
                                            class="max-h-[520px] w-full cursor-pointer object-contain transition hover:opacity-95"
                                            data-test="post-media-image"
                                            @click="openPostLightbox(post, 0)"
                                        />
                                    </div>

                                    <!-- 2 Items (Side-by-side Bento Grid) -->
                                    <div
                                        v-else-if="post.media.length === 2"
                                        class="grid h-72 w-full grid-cols-2 gap-2 overflow-hidden rounded-xl border border-border/50 bg-black/5 sm:h-80 md:h-96 dark:bg-black/25"
                                        data-test="bento-grid-2"
                                    >
                                        <div
                                            v-for="(
                                                item, itemIdx
                                            ) in post.media.slice(0, 2)"
                                            :key="item.id || itemIdx"
                                            class="group relative h-full w-full cursor-pointer overflow-hidden bg-black/10"
                                            data-test="bento-media-item"
                                            @click="
                                                openPostLightbox(post, itemIdx)
                                            "
                                        >
                                            <video
                                                v-if="item.type === 'video'"
                                                :src="item.url"
                                                class="h-full w-full object-cover"
                                            />
                                            <img
                                                v-else
                                                :src="item.url"
                                                :alt="
                                                    item.name ||
                                                    'Photo ' + (itemIdx + 1)
                                                "
                                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                data-test="post-media-image"
                                            />
                                        </div>
                                    </div>

                                    <!-- 3 Items (1 large on left, 2 stacked on right) -->
                                    <div
                                        v-else-if="post.media.length === 3"
                                        class="grid h-72 w-full grid-cols-2 gap-2 overflow-hidden rounded-xl border border-border/50 bg-black/5 sm:h-80 md:h-96 dark:bg-black/25"
                                        data-test="bento-grid-3"
                                    >
                                        <div
                                            class="group relative h-full w-full cursor-pointer overflow-hidden bg-black/10"
                                            data-test="bento-media-item"
                                            @click="openPostLightbox(post, 0)"
                                        >
                                            <video
                                                v-if="
                                                    post.media[0].type ===
                                                    'video'
                                                "
                                                :src="post.media[0].url"
                                                class="h-full w-full object-cover"
                                            />
                                            <img
                                                v-else
                                                :src="post.media[0].url"
                                                :alt="
                                                    post.media[0].name ||
                                                    'Photo 1'
                                                "
                                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                data-test="post-media-image"
                                            />
                                        </div>
                                        <div
                                            class="grid h-full grid-rows-2 gap-2"
                                        >
                                            <div
                                                v-for="(
                                                    item, itemIdx
                                                ) in post.media.slice(1, 3)"
                                                :key="item.id || itemIdx"
                                                class="group relative h-full w-full cursor-pointer overflow-hidden bg-black/10"
                                                data-test="bento-media-item"
                                                @click="
                                                    openPostLightbox(
                                                        post,
                                                        itemIdx + 1,
                                                    )
                                                "
                                            >
                                                <video
                                                    v-if="item.type === 'video'"
                                                    :src="item.url"
                                                    class="h-full w-full object-cover"
                                                />
                                                <img
                                                    v-else
                                                    :src="item.url"
                                                    :alt="
                                                        item.name ||
                                                        'Photo ' + (itemIdx + 2)
                                                    "
                                                    class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                />
                                            </div>
                                        </div>
                                    </div>

                                    <!-- 4+ Items (2x2 Bento Grid with +N overlay on 4th) -->
                                    <div
                                        v-else
                                        class="grid h-72 w-full grid-cols-2 grid-rows-2 gap-2 overflow-hidden rounded-xl border border-border/50 bg-black/5 sm:h-80 md:h-96 dark:bg-black/25"
                                        data-test="bento-grid-4"
                                    >
                                        <div
                                            v-for="(
                                                item, itemIdx
                                            ) in post.media.slice(0, 4)"
                                            :key="item.id || itemIdx"
                                            class="group relative h-full w-full cursor-pointer overflow-hidden bg-black/10"
                                            data-test="bento-media-item"
                                            @click="
                                                openPostLightbox(post, itemIdx)
                                            "
                                        >
                                            <video
                                                v-if="item.type === 'video'"
                                                :src="item.url"
                                                class="h-full w-full object-cover"
                                            />
                                            <img
                                                v-else
                                                :src="item.url"
                                                :alt="
                                                    item.name ||
                                                    'Photo ' + (itemIdx + 1)
                                                "
                                                class="h-full w-full object-cover transition-transform duration-300 group-hover:scale-105"
                                                data-test="post-media-image"
                                            />
                                            <div
                                                v-if="
                                                    itemIdx === 3 &&
                                                    post.media.length > 4
                                                "
                                                class="absolute inset-0 flex items-center justify-center bg-black/60 text-base font-bold text-white backdrop-blur-xs sm:text-lg"
                                            >
                                                +{{ post.media.length - 3 }}
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Legacy Single Attachment Fallback -->
                                <div
                                    v-else-if="post.attachment_url"
                                    class="px-4 pb-3"
                                >
                                    <!-- Image Preview -->
                                    <div
                                        v-if="post.attachment_type === 'image'"
                                        class="cursor-pointer overflow-hidden rounded-xl border border-border/50 bg-zinc-950/5 transition hover:opacity-95 dark:bg-zinc-950/40"
                                        @click="openPostLightbox(post, 0)"
                                    >
                                        <img
                                            :src="post.attachment_url"
                                            :alt="
                                                post.attachment_name ||
                                                'Post attachment'
                                            "
                                            class="max-h-[520px] w-full object-contain"
                                        />
                                    </div>

                                    <!-- Document / File Card -->
                                    <div
                                        v-else
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
                                                    {{
                                                        post.attachment_name ||
                                                        'Attachment File'
                                                    }}
                                                </p>
                                                <p
                                                    class="text-[11px] text-muted-foreground"
                                                >
                                                    {{
                                                        post.attachment_size
                                                            ? Math.round(
                                                                  post.attachment_size /
                                                                      1024,
                                                              ) + ' KB'
                                                            : 'Document'
                                                    }}
                                                    ·
                                                    {{
                                                        post.author
                                                            .section_name ||
                                                        'Class File'
                                                    }}
                                                </p>
                                            </div>
                                        </div>
                                        <a
                                            v-if="post.attachment_url !== '#'"
                                            :href="post.attachment_url"
                                            download
                                            class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border/60 bg-card text-muted-foreground transition-all hover:bg-foreground hover:text-background"
                                            title="Download preview"
                                        >
                                            <Download class="h-3.5 w-3.5" />
                                        </a>
                                        <button
                                            v-else
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
                                                isPostLiked(post.id)
                                                    ? 'text-rose-500'
                                                    : 'text-muted-foreground hover:text-foreground'
                                            "
                                            data-test="post-like-button"
                                            @click="toggleLike(post.id)"
                                        >
                                            <Heart
                                                class="h-4 w-4 transition-transform group-hover:scale-115 active:scale-90"
                                                :class="{
                                                    'fill-rose-500':
                                                        isPostLiked(post.id),
                                                    'scale-125 duration-150':
                                                        isHeartBouncing[
                                                            String(post.id)
                                                        ],
                                                }"
                                            />
                                            <span class="font-medium">{{
                                                getPostLikesCount(post.id)
                                            }}</span>
                                        </button>

                                        <!-- Comment -->
                                        <button
                                            type="button"
                                            class="group inline-flex cursor-pointer items-center gap-1.5 text-xs text-muted-foreground transition-colors hover:text-foreground"
                                            data-test="post-comments-toggle-button"
                                            @click="toggleComments(post.id)"
                                        >
                                            <MessageCircle
                                                class="h-4 w-4 transition-transform group-hover:scale-115 active:scale-90"
                                            />
                                            <span
                                                class="font-medium"
                                                data-test="post-comments-count"
                                                >{{
                                                    getPostCommentsCount(post)
                                                }}</span
                                            >
                                        </button>

                                        <!-- Share / Copy Link -->
                                        <button
                                            type="button"
                                            class="group inline-flex cursor-pointer items-center gap-1 text-xs text-muted-foreground transition-colors hover:text-foreground"
                                            :title="
                                                copiedPostId === post.id
                                                    ? 'Link copied!'
                                                    : 'Share post (Copy link)'
                                            "
                                            aria-label="Share post"
                                            @click="handleSharePost(post)"
                                        >
                                            <Check
                                                v-if="copiedPostId === post.id"
                                                class="animate-in zoom-in-75 h-4 w-4 text-emerald-500 duration-200"
                                            />
                                            <Send
                                                v-else
                                                class="h-4 w-4 -rotate-12 transition-transform group-hover:scale-115 active:scale-90"
                                            />
                                            <span
                                                v-if="copiedPostId === post.id"
                                                class="text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                                            >
                                                Copied!
                                            </span>
                                        </button>
                                    </div>

                                    <!-- Bookmark -->
                                    <button
                                        type="button"
                                        class="cursor-pointer text-muted-foreground transition-colors hover:text-foreground"
                                        :class="{
                                            'text-foreground': isPostBookmarked(
                                                post.id,
                                            ),
                                        }"
                                        data-test="post-bookmark-button"
                                        aria-label="Save post"
                                        @click="toggleBookmark(post.id)"
                                    >
                                        <Bookmark
                                            class="h-4 w-4"
                                            :class="{
                                                'fill-foreground':
                                                    isPostBookmarked(post.id),
                                            }"
                                        />
                                    </button>
                                </div>

                                <!-- Instagram Social Proof / Comments Feed -->
                                <div
                                    class="space-y-2 border-t border-border/30 bg-muted/15 px-4 py-2.5 text-xs"
                                >
                                    <p
                                        v-if="getPostLikesCount(post.id) > 0"
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        <span
                                            class="font-semibold text-foreground"
                                            >{{
                                                getPostLikesCount(post.id)
                                            }}</span
                                        >
                                        {{
                                            getPostLikesCount(post.id) === 1
                                                ? 'like'
                                                : 'likes'
                                        }}
                                    </p>

                                    <!-- View all comments toggle -->
                                    <button
                                        v-if="getPostCommentsCount(post) > 1"
                                        type="button"
                                        class="cursor-pointer text-[11px] text-muted-foreground hover:underline"
                                        @click="toggleComments(post.id)"
                                    >
                                        {{
                                            expandedComments[String(post.id)]
                                                ? 'Hide comments'
                                                : `View all ${getPostCommentsCount(post)} comments`
                                        }}
                                    </button>

                                    <!-- Comments list -->
                                    <div class="space-y-3 pt-1">
                                        <div
                                            v-for="comment in getPostComments(
                                                post,
                                            )"
                                            :key="comment.id"
                                            class="text-[11px] text-foreground/90"
                                            data-test="comment-item"
                                        >
                                            <div
                                                class="flex items-start gap-2.5"
                                            >
                                                <!-- Commenter Avatar -->
                                                <button
                                                    type="button"
                                                    class="mt-0.5 shrink-0 cursor-pointer rounded-full transition-transform hover:scale-105"
                                                    data-test="comment-avatar"
                                                    @click="
                                                        openProfileModal({
                                                            public_id:
                                                                comment.author_public_id,
                                                            name: comment.author_name,
                                                            handle: comment.author_handle,
                                                            avatar: comment.author_avatar,
                                                            is_masked:
                                                                comment.is_masked,
                                                            section_name:
                                                                comment.section_name,
                                                        })
                                                    "
                                                >
                                                    <img
                                                        v-if="
                                                            comment.author_avatar &&
                                                            !comment.is_masked
                                                        "
                                                        :src="
                                                            comment.author_avatar
                                                        "
                                                        :alt="
                                                            comment.author_name
                                                        "
                                                        class="h-6 w-6 rounded-full object-cover"
                                                    />
                                                    <div
                                                        v-else
                                                        class="flex h-6 w-6 items-center justify-center rounded-full bg-brand/15 text-[10px] font-bold text-brand select-none"
                                                    >
                                                        {{
                                                            getInitials(
                                                                comment.author_name,
                                                            )
                                                        }}
                                                    </div>
                                                </button>

                                                <!-- Comment Body & Thread -->
                                                <div class="min-w-0 flex-1">
                                                    <div
                                                        class="leading-relaxed"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="cursor-pointer font-semibold text-foreground hover:underline"
                                                            data-test="comment-author-handle"
                                                            @click="
                                                                openProfileModal(
                                                                    {
                                                                        public_id:
                                                                            comment.author_public_id,
                                                                        name: comment.author_name,
                                                                        handle: comment.author_handle,
                                                                        avatar: comment.author_avatar,
                                                                        is_masked:
                                                                            comment.is_masked,
                                                                        section_name:
                                                                            comment.section_name,
                                                                    },
                                                                )
                                                            "
                                                        >
                                                            {{
                                                                comment.author_handle
                                                            }}
                                                        </button>
                                                        <div
                                                            v-if="
                                                                editingCommentId ===
                                                                String(
                                                                    comment.id,
                                                                )
                                                            "
                                                            class="mt-1.5 space-y-1.5"
                                                            data-test="comment-edit-form"
                                                        >
                                                            <input
                                                                v-model="
                                                                    editingCommentContent
                                                                "
                                                                type="text"
                                                                maxlength="1000"
                                                                class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1 text-xs text-foreground focus:border-brand focus:outline-none"
                                                                data-test="comment-edit-input"
                                                                @keydown.enter.prevent="
                                                                    saveEditComment(
                                                                        comment,
                                                                    )
                                                                "
                                                                @keydown.esc="
                                                                    cancelEditComment
                                                                "
                                                            />
                                                            <div
                                                                class="flex items-center gap-2"
                                                            >
                                                                <button
                                                                    type="button"
                                                                    class="cursor-pointer rounded-md bg-brand px-2 py-0.5 text-[10px] font-semibold text-white hover:bg-brand/90"
                                                                    data-test="save-comment-edit-button"
                                                                    :disabled="
                                                                        isSavingComment
                                                                    "
                                                                    @click="
                                                                        saveEditComment(
                                                                            comment,
                                                                        )
                                                                    "
                                                                >
                                                                    Save
                                                                </button>
                                                                <button
                                                                    type="button"
                                                                    class="cursor-pointer rounded-md px-2 py-0.5 text-[10px] font-semibold text-muted-foreground hover:text-foreground"
                                                                    data-test="cancel-comment-edit-button"
                                                                    @click="
                                                                        cancelEditComment
                                                                    "
                                                                >
                                                                    Cancel
                                                                </button>
                                                            </div>
                                                        </div>
                                                        <span
                                                            v-else
                                                            class="ml-1.5 text-muted-foreground"
                                                            data-test="comment-content"
                                                            >{{
                                                                comment.content
                                                            }}</span
                                                        >
                                                    </div>

                                                    <!-- Comment Meta (time + reply button + like reaction + edit/delete) -->
                                                    <div
                                                        class="mt-1 flex items-center gap-3 text-[10px] text-muted-foreground"
                                                    >
                                                        <span>{{
                                                            comment.created_at
                                                        }}</span>
                                                        <button
                                                            type="button"
                                                            class="cursor-pointer font-semibold hover:text-foreground"
                                                            data-test="comment-reply-button"
                                                            @click="
                                                                setReplyingTo(
                                                                    post.id,
                                                                    comment,
                                                                )
                                                            "
                                                        >
                                                            Reply
                                                        </button>
                                                        <button
                                                            type="button"
                                                            class="inline-flex cursor-pointer items-center gap-1 transition-colors hover:text-foreground"
                                                            :class="{
                                                                'text-rose-500 hover:text-rose-600':
                                                                    isCommentLiked(
                                                                        comment.id,
                                                                    ),
                                                            }"
                                                            data-test="comment-like-button"
                                                            aria-label="Like comment"
                                                            @click="
                                                                toggleCommentLike(
                                                                    comment.id,
                                                                )
                                                            "
                                                        >
                                                            <Heart
                                                                class="h-3 w-3 transition-transform active:scale-75"
                                                                :class="{
                                                                    'fill-rose-500 text-rose-500':
                                                                        isCommentLiked(
                                                                            comment.id,
                                                                        ),
                                                                    'scale-125 duration-150':
                                                                        isCommentHeartBouncing[
                                                                            String(
                                                                                comment.id,
                                                                            )
                                                                        ],
                                                                }"
                                                            />
                                                            <span
                                                                v-if="
                                                                    getCommentLikesCount(
                                                                        comment.id,
                                                                    ) > 0
                                                                "
                                                                class="font-medium"
                                                            >
                                                                {{
                                                                    getCommentLikesCount(
                                                                        comment.id,
                                                                    )
                                                                }}
                                                            </span>
                                                        </button>
                                                        <button
                                                            v-if="
                                                                canEditComment(
                                                                    comment,
                                                                )
                                                            "
                                                            type="button"
                                                            class="cursor-pointer font-semibold hover:text-foreground"
                                                            data-test="comment-edit-button"
                                                            @click="
                                                                startEditComment(
                                                                    comment,
                                                                )
                                                            "
                                                        >
                                                            Edit
                                                        </button>
                                                        <button
                                                            v-if="
                                                                canDeleteComment(
                                                                    post,
                                                                    comment,
                                                                )
                                                            "
                                                            type="button"
                                                            class="cursor-pointer font-semibold text-destructive/80 hover:text-destructive"
                                                            data-test="comment-delete-button"
                                                            @click="
                                                                confirmDeleteComment(
                                                                    post.id,
                                                                    comment,
                                                                )
                                                            "
                                                        >
                                                            Delete
                                                        </button>
                                                    </div>

                                                    <!-- View / Hide Threaded Replies Button -->
                                                    <div
                                                        v-if="
                                                            comment.replies &&
                                                            comment.replies
                                                                .length > 0
                                                        "
                                                        class="mt-1.5"
                                                    >
                                                        <button
                                                            type="button"
                                                            class="inline-flex cursor-pointer items-center gap-1.5 text-[10px] font-semibold text-muted-foreground hover:text-foreground"
                                                            data-test="toggle-replies-button"
                                                            @click="
                                                                toggleReplies(
                                                                    comment.id,
                                                                )
                                                            "
                                                        >
                                                            <span
                                                                class="inline-block h-px w-3 bg-muted-foreground/40"
                                                            ></span>
                                                            <span>{{
                                                                expandedReplies[
                                                                    String(
                                                                        comment.id,
                                                                    )
                                                                ]
                                                                    ? 'Hide replies'
                                                                    : `View ${comment.replies.length} ${
                                                                          comment
                                                                              .replies
                                                                              .length ===
                                                                          1
                                                                              ? 'reply'
                                                                              : 'replies'
                                                                      }`
                                                            }}</span>
                                                            <ChevronUp
                                                                v-if="
                                                                    expandedReplies[
                                                                        String(
                                                                            comment.id,
                                                                        )
                                                                    ]
                                                                "
                                                                class="h-3 w-3"
                                                            />
                                                            <ChevronDown
                                                                v-else
                                                                class="h-3 w-3"
                                                            />
                                                        </button>

                                                        <!-- Threaded Nested Replies Tree -->
                                                        <div
                                                            v-if="
                                                                expandedReplies[
                                                                    String(
                                                                        comment.id,
                                                                    )
                                                                ]
                                                            "
                                                            class="mt-2 space-y-2 border-l-2 border-border/50 pl-3"
                                                            data-test="comment-replies-container"
                                                        >
                                                            <div
                                                                v-for="reply in comment.replies"
                                                                :key="reply.id"
                                                                class="flex items-start gap-2 text-[11px]"
                                                                data-test="reply-item"
                                                            >
                                                                <!-- Reply Author Avatar -->
                                                                <button
                                                                    type="button"
                                                                    class="mt-0.5 shrink-0 cursor-pointer rounded-full transition-transform hover:scale-105"
                                                                    data-test="reply-avatar"
                                                                    @click="
                                                                        openProfileModal(
                                                                            {
                                                                                public_id:
                                                                                    reply.author_public_id,
                                                                                name: reply.author_name,
                                                                                handle: reply.author_handle,
                                                                                avatar: reply.author_avatar,
                                                                                is_masked:
                                                                                    reply.is_masked,
                                                                                section_name:
                                                                                    reply.section_name,
                                                                            },
                                                                        )
                                                                    "
                                                                >
                                                                    <img
                                                                        v-if="
                                                                            reply.author_avatar &&
                                                                            !reply.is_masked
                                                                        "
                                                                        :src="
                                                                            reply.author_avatar
                                                                        "
                                                                        :alt="
                                                                            reply.author_name
                                                                        "
                                                                        class="h-5 w-5 rounded-full object-cover"
                                                                    />
                                                                    <div
                                                                        v-else
                                                                        class="flex h-5 w-5 items-center justify-center rounded-full bg-brand/15 text-[9px] font-bold text-brand select-none"
                                                                    >
                                                                        {{
                                                                            getInitials(
                                                                                reply.author_name,
                                                                            )
                                                                        }}
                                                                    </div>
                                                                </button>

                                                                <!-- Reply Body -->
                                                                <div
                                                                    class="min-w-0 flex-1"
                                                                >
                                                                    <div
                                                                        class="leading-relaxed"
                                                                    >
                                                                        <button
                                                                            type="button"
                                                                            class="cursor-pointer font-semibold text-foreground hover:underline"
                                                                            data-test="reply-author-handle"
                                                                            @click="
                                                                                openProfileModal(
                                                                                    {
                                                                                        public_id:
                                                                                            reply.author_public_id,
                                                                                        name: reply.author_name,
                                                                                        handle: reply.author_handle,
                                                                                        avatar: reply.author_avatar,
                                                                                        is_masked:
                                                                                            reply.is_masked,
                                                                                        section_name:
                                                                                            reply.section_name,
                                                                                    },
                                                                                )
                                                                            "
                                                                        >
                                                                            {{
                                                                                reply.author_handle
                                                                            }}
                                                                        </button>
                                                                        <div
                                                                            v-if="
                                                                                editingCommentId ===
                                                                                String(
                                                                                    reply.id,
                                                                                )
                                                                            "
                                                                            class="mt-1.5 space-y-1.5"
                                                                            data-test="reply-edit-form"
                                                                        >
                                                                            <input
                                                                                v-model="
                                                                                    editingCommentContent
                                                                                "
                                                                                type="text"
                                                                                maxlength="1000"
                                                                                class="w-full rounded-lg border border-border/70 bg-background px-2.5 py-1 text-xs text-foreground focus:border-brand focus:outline-none"
                                                                                data-test="reply-edit-input"
                                                                                @keydown.enter.prevent="
                                                                                    saveEditComment(
                                                                                        reply,
                                                                                    )
                                                                                "
                                                                                @keydown.esc="
                                                                                    cancelEditComment
                                                                                "
                                                                            />
                                                                            <div
                                                                                class="flex items-center gap-2"
                                                                            >
                                                                                <button
                                                                                    type="button"
                                                                                    class="cursor-pointer rounded-md bg-brand px-2 py-0.5 text-[10px] font-semibold text-white hover:bg-brand/90"
                                                                                    data-test="save-reply-edit-button"
                                                                                    :disabled="
                                                                                        isSavingComment
                                                                                    "
                                                                                    @click="
                                                                                        saveEditComment(
                                                                                            reply,
                                                                                        )
                                                                                    "
                                                                                >
                                                                                    Save
                                                                                </button>
                                                                                <button
                                                                                    type="button"
                                                                                    class="cursor-pointer rounded-md px-2 py-0.5 text-[10px] font-semibold text-muted-foreground hover:text-foreground"
                                                                                    data-test="cancel-reply-edit-button"
                                                                                    @click="
                                                                                        cancelEditComment
                                                                                    "
                                                                                >
                                                                                    Cancel
                                                                                </button>
                                                                            </div>
                                                                        </div>
                                                                        <span
                                                                            v-else
                                                                            class="ml-1.5 text-muted-foreground"
                                                                            data-test="reply-content"
                                                                            >{{
                                                                                reply.content
                                                                            }}</span
                                                                        >
                                                                    </div>
                                                                    <div
                                                                        class="mt-0.5 flex items-center gap-3 text-[10px] text-muted-foreground"
                                                                    >
                                                                        <span>{{
                                                                            reply.created_at
                                                                        }}</span>
                                                                        <button
                                                                            type="button"
                                                                            class="cursor-pointer font-semibold hover:text-foreground"
                                                                            data-test="nested-reply-button"
                                                                            @click="
                                                                                setReplyingTo(
                                                                                    post.id,
                                                                                    comment,
                                                                                )
                                                                            "
                                                                        >
                                                                            Reply
                                                                        </button>
                                                                        <button
                                                                            type="button"
                                                                            class="inline-flex cursor-pointer items-center gap-1 transition-colors hover:text-foreground"
                                                                            :class="{
                                                                                'text-rose-500 hover:text-rose-600':
                                                                                    isCommentLiked(
                                                                                        reply.id,
                                                                                    ),
                                                                            }"
                                                                            data-test="reply-like-button"
                                                                            aria-label="Like reply"
                                                                            @click="
                                                                                toggleCommentLike(
                                                                                    reply.id,
                                                                                )
                                                                            "
                                                                        >
                                                                            <Heart
                                                                                class="h-3 w-3 transition-transform active:scale-75"
                                                                                :class="{
                                                                                    'fill-rose-500 text-rose-500':
                                                                                        isCommentLiked(
                                                                                            reply.id,
                                                                                        ),
                                                                                    'scale-125 duration-150':
                                                                                        isCommentHeartBouncing[
                                                                                            String(
                                                                                                reply.id,
                                                                                            )
                                                                                        ],
                                                                                }"
                                                                            />
                                                                            <span
                                                                                v-if="
                                                                                    getCommentLikesCount(
                                                                                        reply.id,
                                                                                    ) >
                                                                                    0
                                                                                "
                                                                                class="font-medium"
                                                                            >
                                                                                {{
                                                                                    getCommentLikesCount(
                                                                                        reply.id,
                                                                                    )
                                                                                }}
                                                                            </span>
                                                                        </button>
                                                                        <button
                                                                            v-if="
                                                                                canEditComment(
                                                                                    reply,
                                                                                )
                                                                            "
                                                                            type="button"
                                                                            class="cursor-pointer font-semibold hover:text-foreground"
                                                                            data-test="reply-edit-button"
                                                                            @click="
                                                                                startEditComment(
                                                                                    reply,
                                                                                )
                                                                            "
                                                                        >
                                                                            Edit
                                                                        </button>
                                                                        <button
                                                                            v-if="
                                                                                canDeleteComment(
                                                                                    post,
                                                                                    reply,
                                                                                )
                                                                            "
                                                                            type="button"
                                                                            class="cursor-pointer font-semibold text-destructive/80 hover:text-destructive"
                                                                            data-test="reply-delete-button"
                                                                            @click="
                                                                                confirmDeleteReply(
                                                                                    post.id,
                                                                                    comment,
                                                                                    reply,
                                                                                )
                                                                            "
                                                                        >
                                                                            Delete
                                                                        </button>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>

                                    <!-- Replying to Indicator Pill -->
                                    <div
                                        v-if="replyingTo[String(post.id)]"
                                        class="mt-2 flex items-center justify-between rounded-md bg-muted/60 px-2.5 py-1 text-[11px] text-muted-foreground"
                                        data-test="replying-to-indicator"
                                    >
                                        <div
                                            class="flex items-center gap-1.5 truncate"
                                        >
                                            <CornerDownRight
                                                class="h-3 w-3 shrink-0 text-brand"
                                            />
                                            <span class="truncate">
                                                Replying to
                                                <span
                                                    class="font-semibold text-foreground"
                                                    >@{{
                                                        replyingTo[
                                                            String(post.id)
                                                        ]?.author_handle
                                                    }}</span
                                                >
                                            </span>
                                        </div>
                                        <button
                                            type="button"
                                            class="cursor-pointer p-0.5 text-muted-foreground hover:text-foreground"
                                            data-test="cancel-reply-button"
                                            aria-label="Cancel reply"
                                            @click="cancelReply(post.id)"
                                        >
                                            <X class="h-3 w-3" />
                                        </button>
                                    </div>

                                    <!-- Quick comment input -->
                                    <div
                                        class="mt-2 flex items-center justify-between border-t border-border/20 pt-1"
                                    >
                                        <input
                                            :ref="
                                                (el) => {
                                                    commentInputRefs[
                                                        String(post.id)
                                                    ] =
                                                        el as HTMLInputElement | null;
                                                }
                                            "
                                            v-model="
                                                commentInputs[String(post.id)]
                                            "
                                            type="text"
                                            :placeholder="
                                                replyingTo[String(post.id)]
                                                    ? `Reply to @${replyingTo[String(post.id)]?.author_handle}…`
                                                    : 'Add a comment…'
                                            "
                                            class="w-full bg-transparent text-[11px] placeholder:text-muted-foreground/60 focus:outline-none"
                                            @keydown.enter.prevent="
                                                handleAddComment(post.id)
                                            "
                                        />
                                        <button
                                            type="button"
                                            :disabled="
                                                !commentInputs[
                                                    String(post.id)
                                                ]?.trim() ||
                                                isSubmittingComment[
                                                    String(post.id)
                                                ]
                                            "
                                            class="cursor-pointer text-[11px] font-semibold text-brand transition-opacity hover:opacity-80 disabled:opacity-40"
                                            data-test="post-comment-button"
                                            @click="handleAddComment(post.id)"
                                        >
                                            {{
                                                isSubmittingComment[
                                                    String(post.id)
                                                ]
                                                    ? 'Posting…'
                                                    : 'Post'
                                            }}
                                        </button>
                                    </div>
                                </div>
                            </article>
                        </div>
                    </main>

                    <!-- ═══ Right Sidebar (Instagram Web 'Suggested for You' Style) ═══ -->
                    <aside
                        class="hidden min-w-0 [scrollbar-width:none] scrollbar-none space-y-3.5 [-ms-overflow-style:none] lg:col-span-5 lg:block lg:min-h-0 lg:overflow-y-auto lg:overscroll-y-contain lg:pr-1 xl:col-span-4 [&::-webkit-scrollbar]:hidden"
                        data-test="community-right-sidebar"
                        data-lenis-prevent
                        scroll-region
                        tabindex="0"
                        aria-label="Community sidebar"
                    >
                        <!-- Instagram Profile Snippet at top of rail -->
                        <div
                            class="flex cursor-pointer items-center justify-between px-1"
                            @click="
                                openProfileModal({
                                    public_id: currentUser?.public_id,
                                    name: currentUserName,
                                    avatar: currentUser?.avatar,
                                    section_name: sectionDisplayName,
                                })
                            "
                        >
                            <div class="flex items-center gap-2.5">
                                <div
                                    class="relative flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/80 bg-brand/15 text-xs font-bold text-brand select-none"
                                >
                                    <img
                                        v-if="currentUser?.avatar"
                                        :src="currentUser.avatar"
                                        :alt="currentUserName"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>
                                        {{ currentUserInitials }}
                                    </span>
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="text-xs font-bold text-foreground"
                                    >
                                        {{ currentUserHandle }}
                                    </p>
                                    <p
                                        class="truncate text-[11px] text-muted-foreground"
                                    >
                                        {{ currentUserName }} ·
                                        {{ sectionDisplayName }}
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
                            class="rounded-xl border border-border/60 bg-card p-3 shadow-2xs sm:p-3.5"
                        >
                            <!-- Instagram 'Suggested for you' style header -->
                            <div
                                class="flex items-center justify-between border-b border-border/40 pb-2"
                            >
                                <div
                                    v-if="
                                        availableSections &&
                                        availableSections.length > 1
                                    "
                                    ref="sectionDropdownRef"
                                    class="relative min-w-0 flex-1 pr-2"
                                >
                                    <button
                                        type="button"
                                        class="group flex cursor-pointer items-center gap-1.5 text-left text-xs font-bold tracking-tight text-foreground uppercase transition-colors hover:text-brand"
                                        data-test="section-dropdown-trigger"
                                        @click.stop="
                                            isSectionDropdownOpen =
                                                !isSectionDropdownOpen
                                        "
                                    >
                                        <span class="max-w-[170px] truncate">{{
                                            sectionDisplayName
                                        }}</span>
                                        <ChevronDown
                                            class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform duration-200 group-hover:text-brand"
                                            :class="{
                                                'rotate-180':
                                                    isSectionDropdownOpen,
                                            }"
                                        />
                                    </button>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        Classroom Presence
                                    </p>

                                    <!-- Section switcher popover -->
                                    <div
                                        v-if="isSectionDropdownOpen"
                                        data-test="section-dropdown-menu"
                                        class="absolute top-full left-0 z-50 mt-1.5 w-64 rounded-xl border border-border/80 bg-popover/95 p-1.5 shadow-xl backdrop-blur-md"
                                    >
                                        <div
                                            class="px-2 py-1 text-[10px] font-semibold tracking-wider text-muted-foreground uppercase"
                                        >
                                            Switch Section
                                        </div>
                                        <button
                                            v-for="sec in availableSections"
                                            :key="sec.id"
                                            type="button"
                                            class="flex w-full cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition-colors"
                                            :class="
                                                sec.id === selectedSectionId
                                                    ? 'bg-brand/10 font-semibold text-brand'
                                                    : 'text-foreground hover:bg-muted'
                                            "
                                            @click.stop="selectSection(sec.id)"
                                        >
                                            <span class="truncate">{{
                                                sec.name
                                            }}</span>
                                            <Check
                                                v-if="
                                                    sec.id === selectedSectionId
                                                "
                                                class="h-3.5 w-3.5 shrink-0 text-brand"
                                            />
                                        </button>
                                    </div>
                                </div>

                                <div v-else class="min-w-0 flex-1 pr-2">
                                    <h2
                                        class="truncate text-xs font-bold tracking-tight text-foreground uppercase"
                                    >
                                        {{ sectionDisplayName }}
                                    </h2>
                                </div>

                                <div class="flex items-center gap-2">
                                    <span
                                        class="inline-flex items-center gap-1 rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-emerald-600 dark:text-emerald-400"
                                    >
                                        <span
                                            class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                                        />
                                        {{ onlineCount }} Online
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
                            <div class="relative mt-2">
                                <Search
                                    class="pointer-events-none absolute top-1/2 left-2.5 h-3.5 w-3.5 -translate-y-1/2 text-muted-foreground"
                                />
                                <input
                                    v-model="classmateSearch"
                                    type="text"
                                    placeholder="Search classmates..."
                                    class="h-7 w-full rounded-lg border border-border/60 bg-muted/40 pr-7 pl-7.5 text-xs text-foreground placeholder:text-muted-foreground/70 focus:border-foreground/30 focus:bg-background focus:outline-none"
                                />
                                <button
                                    v-if="classmateSearch"
                                    type="button"
                                    class="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                                    aria-label="Clear search"
                                    @click="classmateSearch = ''"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>

                            <!-- Classmate Member List -->
                            <div
                                data-test="classmates-list"
                                data-lenis-prevent
                                class="mt-2 max-h-[240px] [scrollbar-width:none] scrollbar-none divide-y divide-border/30 overflow-y-auto overscroll-contain pr-1 [-ms-overflow-style:none] sm:max-h-[280px] [&::-webkit-scrollbar]:hidden"
                                @wheel="handleClassmatesWheel"
                            >
                                <div
                                    v-for="member in filteredClassmates"
                                    :key="member.id"
                                    class="group flex cursor-pointer items-center justify-between gap-2.5 py-1.5 first:pt-1 last:pb-0.5"
                                    @click="
                                        openProfileModal({
                                            public_id: member.public_id,
                                            name: member.name,
                                            avatar: member.avatar,
                                            section_name: member.section_name,
                                        })
                                    "
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <!-- Avatar -->
                                        <div class="relative shrink-0">
                                            <div
                                                class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full text-xs font-semibold select-none"
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
                                                <img
                                                    v-if="member.avatar"
                                                    :src="member.avatar"
                                                    :alt="member.name"
                                                    class="h-full w-full object-cover"
                                                />
                                                <span v-else>
                                                    {{
                                                        getInitials(member.name)
                                                    }}
                                                </span>
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
                                                    class="truncate text-xs font-semibold text-foreground group-hover:underline"
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
                                                class="truncate text-[10px] text-muted-foreground"
                                            >
                                                {{
                                                    member.section_name
                                                        ? member.section_name
                                                        : member.activity
                                                }}
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
                                    <p>
                                        {{
                                            classmateSearch
                                                ? 'No classmates match your search.'
                                                : 'No classmates enrolled or online yet.'
                                        }}
                                    </p>
                                    <button
                                        v-if="classmateSearch"
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
                                class="mt-2 flex items-start gap-1.5 rounded-lg border border-border/40 bg-muted/20 p-2 text-[10px] leading-snug text-muted-foreground"
                            >
                                <ShieldCheck
                                    class="mt-0.5 h-3 w-3 shrink-0 text-emerald-600 dark:text-emerald-400"
                                />
                                <span>
                                    Real names are visible only to classmates in
                                    your section.
                                </span>
                            </div>
                        </div>

                        <!-- Card 2: Trending in Section (Clean Hashtag Feed) -->
                        <div
                            data-test="section-topics-card"
                            class="rounded-xl border border-border/60 bg-card p-3 shadow-2xs"
                        >
                            <div
                                class="flex items-center justify-between pb-1.5"
                            >
                                <h3
                                    class="text-xs font-bold tracking-tight text-foreground uppercase"
                                >
                                    Trending in Section
                                </h3>
                                <span class="text-[9.5px] text-muted-foreground"
                                    >This week</span
                                >
                            </div>

                            <div class="mt-1.5 divide-y divide-border/30">
                                <div
                                    v-for="topic in trendingTopics"
                                    :key="topic.tag"
                                    data-test="trending-topic-item"
                                    class="group flex cursor-pointer items-center justify-between rounded-lg px-1.5 py-1.5 text-xs transition-colors hover:bg-muted/50 hover:text-brand"
                                    @click="openTrendingTopicModal(topic)"
                                >
                                    <div>
                                        <p
                                            class="font-semibold text-foreground transition-colors group-hover:text-brand"
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
                                        class="text-[10px] text-muted-foreground transition-colors group-hover:text-brand"
                                        >Trending</span
                                    >
                                </div>
                                <div
                                    v-if="trendingTopics.length === 0"
                                    class="py-4 text-center text-xs text-muted-foreground"
                                >
                                    No trending tags yet
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
                        <div
                            v-if="
                                availableSections &&
                                availableSections.length > 1
                            "
                            class="relative min-w-0 flex-1"
                        >
                            <button
                                type="button"
                                class="group flex cursor-pointer items-center gap-1.5 text-left text-sm font-bold tracking-tight text-foreground hover:text-brand"
                                @click="
                                    isMobileSectionDropdownOpen =
                                        !isMobileSectionDropdownOpen
                                "
                            >
                                <span class="max-w-[160px] truncate">{{
                                    sectionDisplayName
                                }}</span>
                                <ChevronDown
                                    class="h-3.5 w-3.5 shrink-0 text-muted-foreground transition-transform"
                                    :class="{
                                        'rotate-180':
                                            isMobileSectionDropdownOpen,
                                    }"
                                />
                            </button>
                            <p class="text-[10px] text-muted-foreground">
                                Classroom Presence
                            </p>

                            <!-- Mobile Section Switcher -->
                            <div
                                v-if="isMobileSectionDropdownOpen"
                                class="absolute top-full left-0 z-50 mt-1 w-60 rounded-xl border border-border/80 bg-popover p-1.5 shadow-xl backdrop-blur-md"
                            >
                                <button
                                    v-for="sec in availableSections"
                                    :key="'mobile-sec-' + sec.id"
                                    type="button"
                                    class="flex w-full cursor-pointer items-center justify-between rounded-lg px-2.5 py-2 text-left text-xs font-medium transition-colors"
                                    :class="
                                        sec.id === selectedSectionId
                                            ? 'bg-brand/10 font-semibold text-brand'
                                            : 'text-foreground hover:bg-muted'
                                    "
                                    @click="
                                        isMobileSectionDropdownOpen = false;
                                        selectSection(sec.id);
                                    "
                                >
                                    <span class="truncate">{{ sec.name }}</span>
                                    <Check
                                        v-if="sec.id === selectedSectionId"
                                        class="h-3.5 w-3.5 shrink-0 text-brand"
                                    />
                                </button>
                            </div>
                        </div>

                        <div v-else class="min-w-0 flex-1">
                            <h2
                                class="truncate text-sm font-bold tracking-tight text-foreground"
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
                                <span
                                    class="mr-1.5 h-1.5 w-1.5 rounded-full bg-emerald-500"
                                />
                                {{ onlineCount }} Online
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
                        class="mt-3.5 min-h-0 flex-1 scrollbar-none divide-y divide-border/30 overflow-y-auto overscroll-contain pr-0.5"
                    >
                        <div
                            v-for="member in filteredClassmates"
                            :key="'mobile-' + member.id"
                            class="flex cursor-pointer items-center gap-3 py-2.5 first:pt-0 last:pb-0"
                            @click="
                                isMobileDrawerOpen = false;
                                openProfileModal({
                                    public_id: member.public_id,
                                    name: member.name,
                                    avatar: member.avatar,
                                    section_name: member.section_name,
                                });
                            "
                        >
                            <div class="relative shrink-0">
                                <div
                                    class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full text-xs font-semibold select-none"
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
                                    <img
                                        v-if="member.avatar"
                                        :src="member.avatar"
                                        :alt="member.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>
                                        {{ getInitials(member.name) }}
                                    </span>
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
                            <p>
                                {{
                                    classmateSearch
                                        ? 'No classmates match your search.'
                                        : 'No classmates enrolled or online yet.'
                                }}
                            </p>
                            <button
                                v-if="classmateSearch"
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

        <!-- ═══ Instagram Profile Preview Modal ═══ -->
        <div
            v-if="isProfileModalOpen"
            class="fixed inset-0 z-[150] flex items-center justify-center p-4"
            data-test="profile-preview-modal"
        >
            <div
                class="fixed inset-0 bg-black/60 backdrop-blur-xs"
                @click="isProfileModalOpen = false"
            />

            <div
                class="relative z-[160] w-full max-w-sm overflow-hidden rounded-3xl border border-border/70 bg-card shadow-2xl backdrop-blur-2xl"
            >
                <!-- Top Cover Header -->
                <div
                    class="relative h-24 bg-gradient-to-r from-brand/30 via-[#e6a875]/25 to-brand/10"
                >
                    <button
                        type="button"
                        class="absolute top-3 right-3 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-xs transition-opacity hover:opacity-80"
                        @click="isProfileModalOpen = false"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Modal Content -->
                <div class="px-5 pt-0 pb-6 text-center">
                    <!-- Avatar circle centered overlapping header -->
                    <div class="-mt-12 mb-3 inline-block">
                        <div
                            class="relative flex items-center justify-center rounded-full transition-transform"
                            :class="[
                                profileHasActiveStory
                                    ? 'cursor-pointer bg-gradient-to-tr from-brand via-[#e6a875] to-[#f5eedc] p-[3px] shadow-lg hover:scale-105 active:scale-95'
                                    : 'p-0',
                            ]"
                            :title="
                                profileHasActiveStory
                                    ? 'Click to view active story'
                                    : undefined
                            "
                            @click="handleProfileAvatarClick"
                        >
                            <div
                                class="relative flex h-20 w-20 items-center justify-center overflow-hidden rounded-full border-4 border-card bg-muted text-xl font-bold text-foreground shadow-sm"
                            >
                                <EyeOff
                                    v-if="activeProfile?.is_masked"
                                    class="h-8 w-8 text-muted-foreground"
                                />
                                <img
                                    v-else-if="activeProfile?.avatar"
                                    :src="activeProfile.avatar"
                                    :alt="activeProfile.name"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>
                                    {{
                                        getInitials(
                                            activeProfile?.name || 'Student',
                                        )
                                    }}
                                </span>
                            </div>

                            <span
                                v-if="!activeProfile?.is_masked"
                                class="absolute right-1 bottom-1 h-3.5 w-3.5 rounded-full border-2 border-card"
                                :class="{
                                    'bg-emerald-500':
                                        activeProfile?.presence_status ===
                                            'online' ||
                                        activeProfile?.presence_activity ===
                                            'Active in Lounge' ||
                                        !activeProfile?.presence_status,
                                    'bg-amber-500':
                                        activeProfile?.presence_status ===
                                        'idle',
                                    'bg-muted-foreground/40':
                                        activeProfile?.presence_status ===
                                            'offline' &&
                                        activeProfile?.presence_activity !==
                                            'Active in Lounge',
                                }"
                            />
                        </div>

                        <!-- Story available badge -->
                        <div
                            v-if="profileHasActiveStory"
                            class="mt-1 flex justify-center"
                        >
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 rounded-full bg-brand/10 px-2 py-0.5 text-[10px] font-semibold text-brand transition-colors hover:bg-brand/20 active:scale-95"
                                @click="handleProfileAvatarClick"
                            >
                                <Sparkles class="h-2.5 w-2.5" />
                                <span>Story available</span>
                            </button>
                        </div>
                    </div>

                    <!-- User Identity -->
                    <div class="space-y-1">
                        <div class="flex items-center justify-center gap-1.5">
                            <h3 class="text-base font-bold text-foreground">
                                {{ activeProfile?.name }}
                            </h3>
                            <span
                                v-if="activeProfile?.level"
                                class="rounded-full bg-brand/15 px-2 py-0.5 text-[10px] font-bold text-brand"
                            >
                                Lv. {{ activeProfile.level }}
                            </span>
                        </div>
                        <p class="text-xs font-medium text-muted-foreground">
                            @{{ activeProfile?.handle }} ·
                            {{ activeProfile?.section_name }}
                        </p>
                    </div>

                    <!-- Bio -->
                    <p class="mt-3 text-xs leading-relaxed text-foreground/80">
                        {{ activeProfile?.bio }}
                    </p>

                    <!-- Stats Row (Instagram Style) -->
                    <div
                        v-if="!activeProfile?.is_masked"
                        class="mt-4 grid grid-cols-4 items-center rounded-2xl border border-border/50 bg-muted/30 py-2.5 text-center text-xs"
                    >
                        <div>
                            <span class="block font-bold text-foreground">{{
                                activeProfile?.posts_count ?? 0
                            }}</span>
                            <span class="text-[10px] text-muted-foreground"
                                >Posts</span
                            >
                        </div>
                        <div class="border-l border-border/50">
                            <span class="block font-bold text-foreground">{{
                                activeProfile?.likes_received ?? 0
                            }}</span>
                            <span class="text-[10px] text-muted-foreground"
                                >Likes</span
                            >
                        </div>
                        <div class="border-l border-border/50">
                            <span class="block font-bold text-foreground">{{
                                activeProfile?.followers_count ?? 0
                            }}</span>
                            <span class="text-[10px] text-muted-foreground"
                                >Followers</span
                            >
                        </div>
                        <div class="border-l border-border/50">
                            <span class="block font-bold text-foreground">{{
                                activeProfile?.following_count ?? 0
                            }}</span>
                            <span class="text-[10px] text-muted-foreground"
                                >Following</span
                            >
                        </div>
                    </div>

                    <!-- Action Buttons -->
                    <div class="mt-5 space-y-2">
                        <div class="flex items-center gap-2">
                            <!-- Follow / Following button -->
                            <button
                                v-if="activeProfile?.can_follow"
                                type="button"
                                class="flex flex-1 cursor-pointer items-center justify-center gap-1.5 rounded-full py-2 text-xs font-semibold transition-all active:scale-95"
                                :class="
                                    activeProfile.is_following
                                        ? 'border border-border/80 bg-card text-foreground hover:bg-muted'
                                        : 'bg-brand text-primary-foreground hover:opacity-90'
                                "
                                @click="handleToggleFollow"
                            >
                                <UserCheck
                                    v-if="activeProfile.is_following"
                                    class="h-3.5 w-3.5"
                                />
                                <UserPlus v-else class="h-3.5 w-3.5" />
                                <span>{{
                                    activeProfile.is_following
                                        ? 'Following'
                                        : 'Follow'
                                }}</span>
                            </button>

                            <!-- View Community Lounge Profile Link -->
                            <a
                                v-if="activeProfile?.public_id"
                                :href="
                                    '/community/u/' + activeProfile.public_id
                                "
                                class="inline-flex flex-1 items-center justify-center gap-1.5 rounded-full border border-border/80 bg-card py-2 text-xs font-semibold text-foreground transition-all hover:bg-muted active:scale-95"
                            >
                                <span>Lounge Profile</span>
                                <ExternalLink class="h-3 w-3" />
                            </a>
                        </div>

                        <!-- Secondary Switcher: Academic Learning Platform Profile -->
                        <div
                            v-if="
                                activeProfile?.public_id &&
                                !activeProfile?.is_masked
                            "
                            class="pt-0.5"
                        >
                            <a
                                :href="'/u/' + activeProfile.public_id"
                                class="inline-flex w-full items-center justify-center gap-1.5 rounded-xl border border-border/40 bg-muted/20 py-1.5 text-[11px] font-medium text-muted-foreground transition-colors hover:bg-muted/50 hover:text-foreground"
                            >
                                <GraduationCap class="h-3 w-3 text-brand" />
                                <span>Academic Profile (Courses & Badges)</span>
                                <ExternalLink class="h-2.5 w-2.5 opacity-60" />
                            </a>
                        </div>

                        <!-- Dismiss for masked -->
                        <button
                            v-if="activeProfile?.is_masked"
                            type="button"
                            class="w-full cursor-pointer rounded-full bg-muted py-2 text-xs font-semibold text-foreground hover:bg-muted/80"
                            @click="isProfileModalOpen = false"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ MyDay Story Upload Modal ═══ -->
        <!-- ═══ Create MyDay Story Modal (Instagram / Snapchat Style WYSIWYG) ═══ -->
        <div
            v-if="isStoryModalOpen"
            class="fixed inset-0 z-[160] flex items-center justify-center p-4"
            data-test="community-story-modal"
        >
            <div
                class="fixed inset-0 bg-black/70 backdrop-blur-xs transition-opacity"
                @click="closeStoryModal"
            />
            <div
                class="relative z-[170] max-h-[92vh] w-full max-w-md overflow-y-auto rounded-3xl border border-border/70 bg-card p-5 shadow-2xl sm:p-6"
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
                                Create MyDay Story
                            </h2>
                            <p class="text-[11px] text-muted-foreground">
                                Expires automatically in 24 hours
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground"
                        @click="closeStoryModal"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="space-y-4">
                    <!-- Hidden file input -->
                    <input
                        ref="storyFileInputRef"
                        type="file"
                        accept="image/jpeg,image/png,image/webp,image/gif,video/mp4,video/webm,video/quicktime"
                        class="hidden"
                        data-test="story-file-input"
                        @change="handleStoryFileChange"
                    />

                    <!-- Drag & Drop / Select placeholder -->
                    <div
                        v-if="!storyMediaPreview"
                        :class="[
                            'group flex cursor-pointer flex-col items-center justify-center gap-3 rounded-2xl border-2 border-dashed p-8 text-center transition-all',
                            isStoryDragOver
                                ? 'border-brand bg-brand/10 ring-2 ring-brand/30'
                                : 'border-border/80 bg-muted/20 hover:border-brand/60 hover:bg-brand/5',
                        ]"
                        data-test="story-dropzone"
                        @dragover.prevent="handleStoryDragOver"
                        @dragleave.prevent="handleStoryDragLeave"
                        @drop.prevent="handleStoryDrop"
                        @click="storyFileInputRef?.click()"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-full bg-muted text-muted-foreground transition-colors group-hover:bg-brand/20 group-hover:text-brand"
                        >
                            <UploadCloud class="h-6 w-6" />
                        </div>
                        <div>
                            <p class="text-xs font-semibold text-foreground">
                                Click or drag & drop photo or video
                            </p>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                Images up to 10MB · MP4/WebM up to 30MB (max
                                30s)
                            </p>
                        </div>
                    </div>

                    <!-- WYSIWYG Story Canvas Live Preview -->
                    <div
                        v-else
                        class="relative mx-auto flex aspect-[9/16] w-full max-w-[260px] flex-col overflow-hidden rounded-3xl border border-white/10 bg-zinc-950 shadow-2xl ring-1 ring-black/40 select-none sm:max-w-[280px]"
                        data-test="story-live-preview"
                    >
                        <!-- Top Progress Bar Mockup -->
                        <div
                            class="absolute inset-x-3 top-3 z-30 flex items-center gap-1"
                        >
                            <div
                                class="h-1 flex-1 overflow-hidden rounded-full bg-white/40"
                            >
                                <div class="h-full w-full bg-white" />
                            </div>
                        </div>

                        <!-- Top Story Header: Author, Duration Badge, Replace Action -->
                        <div
                            class="absolute inset-x-3 top-6 z-30 flex items-center justify-between text-white"
                        >
                            <div class="flex items-center gap-2">
                                <div
                                    class="flex h-7 w-7 items-center justify-center overflow-hidden rounded-full border border-white/40 bg-zinc-800 text-[10px] font-bold text-white"
                                >
                                    <img
                                        v-if="currentUser?.avatar"
                                        :src="currentUser.avatar"
                                        :alt="currentUserName"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>{{
                                        currentUserInitials
                                    }}</span>
                                </div>
                                <div class="leading-none">
                                    <div class="flex items-center gap-1">
                                        <span
                                            class="text-[11px] font-bold drop-shadow"
                                            >{{ currentUserName }}</span
                                        >
                                        <span class="text-[9px] text-white/70"
                                            >· 24h</span
                                        >
                                    </div>
                                    <span class="text-[9px] text-white/70"
                                        >@{{ currentUserHandle }}</span
                                    >
                                </div>
                            </div>

                            <div class="flex items-center gap-1.5">
                                <!-- Duration Badge -->
                                <div
                                    class="flex items-center gap-1 rounded-full bg-black/60 px-2 py-0.5 text-[10px] font-medium text-white/90 backdrop-blur-xs"
                                    data-test="story-preview-duration-badge"
                                >
                                    <Film
                                        v-if="storyMediaType === 'video'"
                                        class="h-3 w-3"
                                    />
                                    <ImageIcon v-else class="h-3 w-3" />
                                    <span>{{ storyDuration }}s</span>
                                </div>

                                <!-- Change / Remove Media Button -->
                                <button
                                    type="button"
                                    class="flex h-6 w-6 cursor-pointer items-center justify-center rounded-full bg-black/60 text-white transition hover:bg-black/80"
                                    title="Change media"
                                    data-test="story-change-media"
                                    @click="clearStoryMedia"
                                >
                                    <X class="h-3.5 w-3.5" />
                                </button>
                            </div>
                        </div>

                        <!-- Center Media Rendering -->
                        <div
                            class="relative flex h-full w-full items-center justify-center bg-black"
                        >
                            <img
                                v-if="storyMediaType === 'image'"
                                :src="storyMediaPreview"
                                alt="Story preview"
                                class="h-full w-full object-contain"
                            />
                            <video
                                v-else
                                :src="storyMediaPreview"
                                class="h-full w-full object-contain"
                                autoplay
                                playsinline
                                muted
                                loop
                            />
                        </div>

                        <!-- Bottom Caption Overlay (Live WYSIWYG) -->
                        <div
                            v-if="storyCaption.trim()"
                            class="absolute inset-x-3 bottom-3 z-30 max-h-24 overflow-y-auto rounded-2xl bg-black/65 p-2.5 text-center text-xs font-medium break-words text-white shadow-lg backdrop-blur-md"
                            data-test="story-preview-caption"
                        >
                            {{ storyCaption }}
                        </div>
                    </div>

                    <!-- Photo Duration Selector (Segmented Chips) -->
                    <div
                        v-if="storyMediaType === 'image' && storyMediaPreview"
                        class="flex items-center justify-between rounded-xl border border-border/70 bg-muted/30 px-3 py-2"
                    >
                        <div
                            class="flex items-center gap-1.5 text-xs text-muted-foreground"
                        >
                            <Clock class="h-3.5 w-3.5 text-brand" />
                            <span class="font-medium text-foreground"
                                >Duration</span
                            >
                        </div>
                        <div
                            class="flex items-center gap-1"
                            data-test="story-duration-selector"
                        >
                            <button
                                v-for="dur in [3, 5, 10, 15]"
                                :key="dur"
                                type="button"
                                :class="[
                                    'cursor-pointer rounded-lg px-2.5 py-1 text-xs font-semibold transition',
                                    storyDuration === dur
                                        ? 'bg-brand text-primary-foreground shadow-xs'
                                        : 'bg-muted/60 text-muted-foreground hover:bg-muted hover:text-foreground',
                                ]"
                                :data-test="`story-duration-${dur}`"
                                @click="storyDuration = dur"
                            >
                                {{ dur }}s
                            </button>
                        </div>
                    </div>

                    <!-- Video Duration Indicator -->
                    <div
                        v-else-if="
                            storyMediaType === 'video' && storyMediaPreview
                        "
                        class="flex items-center justify-between rounded-xl border border-border/70 bg-muted/30 px-3 py-2 text-xs"
                    >
                        <div
                            class="flex items-center gap-1.5 text-muted-foreground"
                        >
                            <Clock class="h-3.5 w-3.5 text-brand" />
                            <span class="font-medium text-foreground"
                                >Video Duration</span
                            >
                        </div>
                        <span class="font-semibold text-foreground"
                            >{{ storyDuration }}s (auto)</span
                        >
                    </div>

                    <!-- Caption Input -->
                    <div>
                        <label
                            class="mb-1 block text-[11px] font-semibold text-foreground"
                        >
                            Caption (optional)
                        </label>
                        <input
                            v-model="storyCaption"
                            type="text"
                            maxlength="255"
                            placeholder="Add a caption to your story..."
                            class="w-full rounded-xl border border-border/70 bg-background px-3 py-2 text-xs text-foreground placeholder:text-muted-foreground focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none"
                            data-test="story-caption-input"
                        />
                    </div>

                    <!-- Upload Error Banner -->
                    <div
                        v-if="storyUploadError"
                        class="rounded-xl border border-rose-500/30 bg-rose-500/10 p-2.5 text-xs font-medium text-rose-600 dark:text-rose-400"
                    >
                        {{ storyUploadError }}
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-2 pt-2">
                        <button
                            type="button"
                            class="cursor-pointer rounded-xl border border-border/70 px-4 py-2 text-xs font-semibold text-foreground hover:bg-muted"
                            @click="closeStoryModal"
                        >
                            Cancel
                        </button>
                        <button
                            type="button"
                            :disabled="!storyMediaFile || isSubmittingStory"
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl bg-brand px-5 py-2 text-xs font-semibold text-primary-foreground transition-all hover:opacity-90 disabled:opacity-50"
                            @click="handleUploadStory"
                        >
                            <Loader2
                                v-if="isSubmittingStory"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <span>{{
                                isSubmittingStory
                                    ? 'Sharing...'
                                    : 'Share to MyDay'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Full-Screen Story Viewer (Instagram / Facebook Style) ═══ -->
        <div
            v-if="isStoryViewerOpen && currentStory"
            class="fixed inset-0 z-[180] flex items-center justify-center bg-black/95 select-none"
            data-test="community-story-viewer"
        >
            <div
                class="relative flex h-full max-h-[92vh] w-full max-w-sm flex-col overflow-hidden rounded-3xl bg-zinc-950 shadow-2xl ring-1 ring-white/10"
                @pointerdown="pauseStory"
                @pointerup="resumeStory"
            >
                <!-- Segmented Progress Bar -->
                <div
                    class="absolute inset-x-3 top-3 z-30 flex items-center gap-1"
                >
                    <div
                        v-for="(_, idx) in activeStoryGroup?.stories"
                        :key="idx"
                        class="h-1 flex-1 overflow-hidden rounded-full bg-white/30 backdrop-blur-xs"
                    >
                        <div
                            class="h-full bg-white transition-all duration-75 ease-linear"
                            :style="{
                                width:
                                    idx < currentStoryIndex
                                        ? '100%'
                                        : idx === currentStoryIndex
                                          ? `${storyProgress}%`
                                          : '0%',
                            }"
                        />
                    </div>
                </div>

                <!-- Header: Author & Controls -->
                <div
                    class="absolute inset-x-3 top-6 z-30 flex items-center justify-between text-white"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            v-if="!activeStoryGroup?.is_self"
                            class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-full border border-white/40 bg-zinc-800 text-xs font-bold text-white"
                            data-test="viewer-author-avatar"
                        >
                            <img
                                v-if="activeStoryGroup?.avatar"
                                :src="activeStoryGroup.avatar"
                                :alt="activeStoryGroup.name"
                                class="h-full w-full object-cover"
                            />
                            <span v-else>{{
                                activeStoryGroup?.name
                                    ?.slice(0, 2)
                                    .toUpperCase()
                            }}</span>
                        </div>
                        <div class="leading-tight">
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-bold drop-shadow">{{
                                    activeStoryGroup?.is_self
                                        ? 'Your story'
                                        : activeStoryGroup?.name
                                }}</span>
                                <span class="text-[10px] text-white/70"
                                    >· {{ currentStory.created_at }}</span
                                >
                            </div>
                            <span
                                v-if="!activeStoryGroup?.is_self"
                                class="text-[10px] text-white/70"
                                data-test="viewer-author-handle"
                                >@{{ activeStoryGroup?.handle }}</span
                            >
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- Views counter -->
                        <div
                            v-if="activeStoryGroup?.is_self"
                            class="flex items-center gap-1 rounded-full bg-black/40 px-2.5 py-1 text-[11px] font-semibold text-white/90 backdrop-blur-xs"
                            title="Views"
                            data-test="viewer-views-count"
                        >
                            <Eye class="h-3 w-3" />
                            <span>{{ currentStory.views_count }}</span>
                        </div>

                        <!-- Play / Pause toggle button -->
                        <button
                            type="button"
                            class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-xs transition hover:bg-white/20 active:scale-95"
                            :title="
                                isStoryPaused ? 'Play story' : 'Pause story'
                            "
                            data-test="viewer-play-pause-button"
                            @pointerdown.stop
                            @pointerup.stop
                            @click.stop="togglePlayPause"
                        >
                            <Play
                                v-if="isStoryPaused"
                                class="ml-0.5 h-3.5 w-3.5 fill-white"
                            />
                            <Pause v-else class="h-3.5 w-3.5 fill-white" />
                        </button>

                        <!-- Story options menu (own story) -->
                        <div
                            v-if="activeStoryGroup?.is_self"
                            class="relative"
                            @pointerdown.stop
                            @pointerup.stop
                        >
                            <button
                                type="button"
                                class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-xs transition hover:bg-white/20 active:scale-95"
                                title="Story options"
                                data-test="viewer-story-options-button"
                                @click.stop="toggleStoryOptions"
                            >
                                <MoreHorizontal class="h-4 w-4" />
                            </button>

                            <!-- Click-outside invisible backdrop -->
                            <div
                                v-if="isStoryOptionsOpen"
                                class="fixed inset-0 z-40"
                                @pointerdown.stop
                                @pointerup.stop
                                @click.stop="toggleStoryOptions"
                            />

                            <!-- Dropdown menu -->
                            <div
                                v-if="isStoryOptionsOpen"
                                class="absolute top-10 right-0 z-50 w-44 rounded-2xl border border-white/15 bg-zinc-900/95 p-1.5 shadow-2xl backdrop-blur-md transition-all duration-150"
                                data-test="viewer-story-options-menu"
                                @pointerdown.stop
                                @pointerup.stop
                                @click.stop
                            >
                                <button
                                    type="button"
                                    class="flex w-full cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-left text-xs font-semibold text-white transition hover:bg-white/15"
                                    data-test="viewer-add-story-button"
                                    @click.stop="
                                        isStoryOptionsOpen = false;
                                        openStoryModalFromViewer();
                                    "
                                >
                                    <Plus class="h-4 w-4 text-emerald-400" />
                                    <span>Add to story</span>
                                </button>

                                <button
                                    type="button"
                                    class="flex w-full cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-left text-xs font-semibold text-white transition hover:bg-white/15 disabled:opacity-50"
                                    data-test="viewer-feature-story-button"
                                    :disabled="isFeaturingStory"
                                    @click.stop="
                                        isStoryOptionsOpen = false;
                                        resumeStory();
                                        handleFeatureStoryFromViewer();
                                    "
                                >
                                    <Sparkles class="h-4 w-4 text-amber-400" />
                                    <span>Add to Featured</span>
                                </button>

                                <div class="my-1 border-t border-white/10" />

                                <button
                                    type="button"
                                    class="flex w-full cursor-pointer items-center gap-2 rounded-xl px-3 py-2 text-left text-xs font-semibold text-rose-400 transition hover:bg-rose-500/20"
                                    data-test="viewer-delete-story-button"
                                    @click.stop="
                                        isStoryOptionsOpen = false;
                                        handleDeleteStory();
                                    "
                                >
                                    <Trash2 class="h-4 w-4" />
                                    <span>Delete story</span>
                                </button>
                            </div>
                        </div>

                        <!-- Close button -->
                        <button
                            type="button"
                            class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-black/40 text-white backdrop-blur-xs transition hover:bg-white/20"
                            @click.stop="closeStoryViewer"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>
                </div>

                <!-- Toast banner in viewer -->
                <div
                    v-if="featuredToast"
                    class="absolute inset-x-4 top-16 z-40 rounded-xl bg-amber-500/95 px-3 py-2 text-center text-xs font-semibold text-black shadow-lg backdrop-blur-xs"
                    data-test="viewer-featured-toast"
                >
                    {{ featuredToast }}
                </div>

                <!-- Center Media -->
                <div
                    class="relative flex h-full w-full items-center justify-center bg-black"
                >
                    <img
                        v-if="currentStory.media_type === 'image'"
                        :src="currentStory.media_url"
                        alt="Story content"
                        class="h-full w-full object-contain"
                    />
                    <video
                        v-else
                        ref="storyVideoRef"
                        :src="currentStory.media_url"
                        class="h-full w-full object-contain"
                        autoplay
                        playsinline
                        muted
                    />

                    <!-- Left / Right Click Nav Zones -->
                    <div
                        class="absolute inset-y-0 left-0 z-10 w-1/3 cursor-pointer"
                        @click.stop="goToPreviousStory"
                    />
                    <div
                        class="absolute inset-y-0 right-0 z-10 w-2/3 cursor-pointer"
                        @click.stop="goToNextStory"
                    />
                </div>

                <!-- Floating Reaction Particles Overlay -->
                <div
                    class="pointer-events-none absolute inset-0 z-40 overflow-hidden"
                >
                    <div
                        v-for="particle in flyingReactions"
                        :key="particle.id"
                        class="flying-reaction-item pointer-events-none absolute bottom-16 select-none"
                        :style="{
                            left: `${particle.left}%`,
                            '--particle-rot': `${particle.rotation}deg`,
                        }"
                    >
                        <component
                            :is="getReactionIcon(particle.emoji)"
                            class="fill-white/10 stroke-[1.8] text-white drop-shadow-md"
                            :style="{
                                width: `${particle.size}px`,
                                height: `${particle.size}px`,
                            }"
                        />
                    </div>
                </div>

                <!-- Bottom Caption Overlay -->
                <div
                    v-if="currentStory.caption"
                    class="absolute inset-x-3 z-30 rounded-2xl bg-black/60 p-3 text-center text-xs font-medium text-white backdrop-blur-md"
                    :class="
                        !activeStoryGroup?.is_self ? 'bottom-16' : 'bottom-14'
                    "
                    data-test="story-caption-overlay"
                >
                    {{ currentStory.caption }}
                </div>

                <!-- Bottom Left Reactions Counter (own story) -->
                <div
                    v-if="activeStoryGroup?.is_self"
                    class="absolute bottom-3.5 left-3.5 z-30 flex items-center gap-1.5 rounded-full bg-black/50 px-3 py-1.5 text-xs font-semibold text-white/95 ring-1 ring-white/15 backdrop-blur-md"
                    title="Story reactions"
                    data-test="viewer-reactions-count"
                    @pointerdown.stop
                    @pointerup.stop
                    @click.stop
                >
                    <Heart class="h-3.5 w-3.5 fill-rose-500 text-rose-500" />
                    <span>{{ currentStory.reactions_count ?? 0 }}</span>
                </div>

                <!-- Bottom Viewer Reactions Bar (when viewing classmate story) -->
                <div
                    v-if="!activeStoryGroup?.is_self"
                    class="absolute inset-x-4 bottom-3.5 z-30 mx-auto flex max-w-xs items-center justify-center gap-1.5 rounded-full border border-white/20 bg-black/40 px-3 py-1.5 shadow-2xl ring-1 ring-white/10 backdrop-blur-md"
                    data-test="story-reactions-bar"
                    @pointerdown.stop
                    @pointerup.stop
                    @click.stop
                >
                    <button
                        v-for="opt in storyReactionOptions"
                        :key="opt.id"
                        type="button"
                        class="group relative flex h-9 w-9 cursor-pointer items-center justify-center rounded-full transition-all duration-150 hover:scale-115 active:scale-95"
                        :class="
                            currentStory.user_reaction === opt.id
                                ? 'ring-1.5 scale-110 bg-white/20 shadow-sm ring-white/70'
                                : 'hover:bg-white/10'
                        "
                        :title="`React with ${opt.label}`"
                        :aria-label="`React with ${opt.label}`"
                        :data-test="`story-reaction-${opt.id}`"
                        @click.stop="handleStoryReaction(opt.id)"
                    >
                        <component
                            :is="opt.icon"
                            class="h-4.5 w-4.5 fill-transparent stroke-[1.8] transition-transform group-hover:scale-110"
                            :class="
                                currentStory.user_reaction === opt.id
                                    ? 'fill-white/10 stroke-[2] text-white'
                                    : 'text-white/85 group-hover:text-white'
                            "
                        />
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Post Confirmation Modal -->
        <div
            v-if="postToDelete"
            class="fixed inset-0 z-[170] flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
            data-test="delete-post-modal"
            @click.self="cancelDeletePost"
        >
            <div
                class="w-full max-w-sm space-y-4 rounded-2xl border border-border/80 bg-card p-5 shadow-2xl"
            >
                <div class="flex items-center gap-3 text-destructive">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive/10"
                    >
                        <Trash2 class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            Delete Post
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            Are you sure you want to delete this post?
                        </p>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    This will permanently remove your post and all associated
                    comments. This action cannot be undone.
                </p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground"
                        data-test="cancel-delete-post-button"
                        @click="cancelDeletePost"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-destructive px-3 py-1.5 text-xs font-semibold text-white hover:bg-destructive/90 disabled:opacity-50"
                        :disabled="isDeletingPost"
                        data-test="confirm-delete-post-button"
                        @click="deletePost(postToDelete)"
                    >
                        <Loader2
                            v-if="isDeletingPost"
                            class="h-3.5 w-3.5 animate-spin"
                        />
                        <span>Delete</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Delete Comment / Reply Confirmation Modal -->
        <div
            v-if="commentToDelete"
            class="fixed inset-0 z-[170] flex items-center justify-center bg-background/80 p-4 backdrop-blur-sm"
            data-test="delete-comment-modal"
            @click.self="cancelDeleteComment"
        >
            <div
                class="w-full max-w-sm space-y-4 rounded-2xl border border-border/80 bg-card p-5 shadow-2xl"
            >
                <div class="flex items-center gap-3 text-destructive">
                    <div
                        class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-destructive/10"
                    >
                        <Trash2 class="h-5 w-5" />
                    </div>
                    <div>
                        <h3 class="text-sm font-semibold text-foreground">
                            {{
                                commentToDelete.isReply
                                    ? 'Delete Reply'
                                    : 'Delete Comment'
                            }}
                        </h3>
                        <p class="text-xs text-muted-foreground">
                            {{
                                commentToDelete.isReply
                                    ? 'Are you sure you want to delete this reply?'
                                    : 'Are you sure you want to delete this comment?'
                            }}
                        </p>
                    </div>
                </div>
                <p class="text-xs text-muted-foreground">
                    {{
                        commentToDelete.isReply
                            ? 'This reply will be permanently removed. This action cannot be undone.'
                            : 'This comment and any threaded replies will be permanently removed. This action cannot be undone.'
                    }}
                </p>
                <div class="flex items-center justify-end gap-2 pt-2">
                    <button
                        type="button"
                        class="cursor-pointer rounded-lg px-3 py-1.5 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground"
                        data-test="cancel-delete-comment-button"
                        @click="cancelDeleteComment"
                    >
                        Cancel
                    </button>
                    <button
                        type="button"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-lg bg-destructive px-3 py-1.5 text-xs font-semibold text-white hover:bg-destructive/90 disabled:opacity-50"
                        :disabled="
                            Boolean(
                                commentToDelete &&
                                isDeletingComment[
                                    String(commentToDelete.comment.id)
                                ],
                            )
                        "
                        data-test="confirm-delete-comment-button"
                        @click="handleConfirmDeleteComment"
                    >
                        <Loader2
                            v-if="
                                commentToDelete &&
                                isDeletingComment[
                                    String(commentToDelete.comment.id)
                                ]
                            "
                            class="h-3.5 w-3.5 animate-spin"
                        />
                        <span>Delete</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- First-Time Member Welcome Modal (Apple-style What's New dialog) -->
        <div
            v-if="isWelcomeModalOpen"
            class="fixed inset-0 z-[180] flex items-center justify-center p-4 backdrop-blur-md transition-all duration-300"
            data-test="welcome-modal"
            @click.self="dismissWelcomeModal"
        >
            <!-- Ambient backdrop scrim -->
            <div
                class="fixed inset-0 -z-10 bg-black/60 backdrop-blur-md"
                data-test="welcome-modal-backdrop"
                @click="dismissWelcomeModal"
            />

            <div
                class="relative w-full max-w-[420px] overflow-hidden rounded-[28px] border border-border/60 bg-card/95 p-6 shadow-2xl ring-1 ring-border/20 backdrop-blur-2xl sm:p-7 dark:border-white/10 dark:bg-zinc-900/95"
            >
                <!-- Top specular light highlight -->
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"
                />

                <!-- Top close button -->
                <button
                    type="button"
                    class="absolute top-4 right-4 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-muted/60 text-muted-foreground transition-all duration-150 hover:bg-muted hover:text-foreground active:scale-95"
                    aria-label="Close welcome modal"
                    data-test="welcome-modal-close"
                    @click="dismissWelcomeModal"
                >
                    <X class="h-3.5 w-3.5" />
                </button>

                <!-- Icon Badge (Apple squircle) -->
                <div class="flex justify-center pt-1">
                    <div
                        class="flex h-13 w-13 items-center justify-center rounded-2xl bg-brand/10 text-brand shadow-sm ring-1 ring-brand/25"
                    >
                        <Sparkles class="h-6 w-6 stroke-[1.8]" />
                    </div>
                </div>

                <!-- Text Content -->
                <div class="mt-4 text-center">
                    <h2
                        class="text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                    >
                        Welcome to the Community Lounge! 🎉
                    </h2>

                    <p
                        class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                    >
                        You're now an official member of the private student
                        lounge. Connect with your section, share stories, and
                        stay in the loop.
                    </p>
                </div>

                <!-- Feature Highlights (Apple "What's New" row style) -->
                <div class="mt-6 space-y-3.5 text-left">
                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-muted/70 text-foreground ring-1 ring-border/60"
                        >
                            <Clock class="h-4 w-4 stroke-[1.8]" />
                        </div>
                        <div class="min-w-0 flex-1 pt-0.5">
                            <p
                                class="text-xs font-semibold text-foreground sm:text-sm"
                            >
                                24-Hour Stories
                            </p>
                            <p
                                class="text-[11px] leading-normal text-muted-foreground sm:text-xs"
                            >
                                Share ephemeral photo and video moments with
                                your section.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-muted/70 text-foreground ring-1 ring-border/60"
                        >
                            <Users class="h-4 w-4 stroke-[1.8]" />
                        </div>
                        <div class="min-w-0 flex-1 pt-0.5">
                            <p
                                class="text-xs font-semibold text-foreground sm:text-sm"
                            >
                                Live Section Presence
                            </p>
                            <p
                                class="text-[11px] leading-normal text-muted-foreground sm:text-xs"
                            >
                                See active classmates and stay connected across
                                your classroom.
                            </p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3">
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-xl bg-muted/70 text-foreground ring-1 ring-border/60"
                        >
                            <Heart class="h-4 w-4 stroke-[1.8]" />
                        </div>
                        <div class="min-w-0 flex-1 pt-0.5">
                            <p
                                class="text-xs font-semibold text-foreground sm:text-sm"
                            >
                                Threads & Reactions
                            </p>
                            <p
                                class="text-[11px] leading-normal text-muted-foreground sm:text-xs"
                            >
                                Drop thoughts, react to classmates' updates, and
                                join discussions.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons (Apple bottom bar) -->
                <div class="mt-7 flex items-center gap-2.5 sm:gap-3">
                    <button
                        type="button"
                        class="flex h-11 flex-1 cursor-pointer items-center justify-center rounded-full border border-border/70 bg-muted/30 text-xs font-semibold text-muted-foreground transition-all duration-150 hover:bg-muted/70 hover:text-foreground active:scale-[0.98] sm:text-sm"
                        data-test="welcome-modal-skip"
                        @click="dismissWelcomeModal"
                    >
                        Skip
                    </button>
                    <button
                        type="button"
                        class="flex h-11 flex-1 cursor-pointer items-center justify-center rounded-full bg-foreground text-xs font-semibold text-background shadow-sm transition-all duration-150 hover:opacity-90 active:scale-[0.98] sm:text-sm dark:bg-white dark:text-zinc-950"
                        data-test="welcome-modal-enter"
                        @click="dismissWelcomeModal"
                    >
                        Enter Lounge
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ Create Post Modal (Instagram / Facebook / TikTok Style) ═══ -->
        <div
            v-if="isMediaModalOpen"
            class="fixed inset-0 z-[180] flex items-center justify-center p-3 backdrop-blur-md transition-all duration-300 sm:p-4"
            data-test="create-media-post-modal"
            @click.self="closeMediaModal"
        >
            <!-- Ambient backdrop scrim -->
            <div
                class="fixed inset-0 -z-10 bg-black/75 backdrop-blur-md"
                data-test="create-media-modal-backdrop"
                @click="closeMediaModal"
            />

            <div
                class="relative flex max-h-[92vh] w-full max-w-4xl flex-col overflow-hidden rounded-2xl border border-border/70 bg-card shadow-2xl ring-1 ring-border/20 sm:rounded-3xl md:flex-row dark:border-white/10 dark:bg-zinc-900"
            >
                <!-- Top close button -->
                <button
                    type="button"
                    class="absolute top-3 right-3 z-30 flex h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-xs transition hover:scale-105 hover:bg-black/80 active:scale-95"
                    aria-label="Close composer"
                    data-test="close-media-modal-button"
                    @click="closeMediaModal"
                >
                    <X class="h-4 w-4" />
                </button>

                <!-- Left Column / Media Stage (Desktop 58%, Mobile 100%) -->
                <div
                    class="relative flex min-h-[260px] flex-1 flex-col justify-between overflow-hidden bg-zinc-950 text-white sm:min-h-[360px] md:min-h-[460px]"
                >
                    <!-- Main Active Media Preview Stage -->
                    <div
                        class="relative flex flex-1 items-center justify-center overflow-hidden"
                    >
                        <template v-if="activeMediaItem">
                            <video
                                v-if="activeMediaItem.type === 'video'"
                                :src="activeMediaItem.preview"
                                controls
                                class="max-h-[380px] w-full object-contain md:max-h-[480px]"
                                data-test="media-modal-video-preview"
                            />
                            <img
                                v-else
                                :src="activeMediaItem.preview"
                                :alt="activeMediaItem.name"
                                class="max-h-[380px] w-full object-contain md:max-h-[480px]"
                                data-test="media-modal-image-preview"
                            />
                        </template>

                        <!-- Empty Dropzone if no media selected yet -->
                        <div
                            v-else
                            class="flex flex-col items-center justify-center p-8 text-center"
                            @click="mediaFileInputRef?.click()"
                        >
                            <div
                                class="mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-white/10 text-brand"
                            >
                                <ImageIcon class="h-7 w-7" />
                            </div>
                            <h4 class="text-sm font-bold">
                                Select photos and videos
                            </h4>
                            <p class="mt-1 text-xs text-zinc-400">
                                Drag photos and videos here or click to browse
                            </p>
                            <button
                                type="button"
                                class="mt-4 rounded-full bg-brand px-4 py-1.5 text-xs font-semibold text-white shadow-xs hover:bg-brand/90"
                            >
                                Select from computer
                            </button>
                        </div>

                        <!-- Previous / Next Arrows for carousel -->
                        <template v-if="mediaFiles.length > 1">
                            <button
                                type="button"
                                class="absolute top-1/2 left-2.5 flex h-8 w-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-xs transition hover:scale-110 hover:bg-black/80 active:scale-95"
                                data-test="media-prev-button"
                                @click="moveMediaCarousel(-1)"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </button>
                            <button
                                type="button"
                                class="absolute top-1/2 right-2.5 flex h-8 w-8 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-xs transition hover:scale-110 hover:bg-black/80 active:scale-95"
                                data-test="media-next-button"
                                @click="moveMediaCarousel(1)"
                            >
                                <ChevronRight class="h-4 w-4" />
                            </button>

                            <!-- Counter badge -->
                            <div
                                class="absolute top-3 left-3 rounded-full bg-black/70 px-2.5 py-0.5 text-[11px] font-semibold text-white backdrop-blur-xs"
                                data-test="media-modal-counter"
                            >
                                {{ activeMediaIndex + 1 }} /
                                {{ mediaFiles.length }}
                            </div>
                        </template>

                        <!-- Attached Music Track Badge on Preview (Instagram style) -->
                        <div
                            v-if="selectedMusicTrack"
                            class="absolute top-3 left-1/2 flex -translate-x-1/2 items-center gap-1.5 rounded-full border border-white/20 bg-black/75 px-3 py-1 text-xs font-medium text-white shadow-lg backdrop-blur-md"
                            data-test="modal-attached-soundtrack-badge"
                        >
                            <Music
                                class="h-3.5 w-3.5 animate-pulse text-brand"
                            />
                            <span
                                class="max-w-[140px] truncate sm:max-w-[200px]"
                            >
                                {{ selectedMusicTrack.title }} •
                                {{ selectedMusicTrack.artist }}
                            </span>
                            <button
                                type="button"
                                class="ml-1 cursor-pointer text-white/70 hover:text-white"
                                title="Remove music"
                                @click.stop="removeSelectedMusic"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>
                    </div>

                    <!-- Bottom Thumbnail Strip -->
                    <div
                        v-if="mediaFiles.length > 0"
                        class="flex scrollbar-none items-center gap-2 overflow-x-auto border-t border-white/10 bg-black/40 p-2.5 backdrop-blur-xs"
                    >
                        <div
                            v-for="(item, idx) in mediaFiles"
                            :key="idx"
                            :class="[
                                'group relative h-12 w-12 shrink-0 cursor-pointer overflow-hidden rounded-lg border-2 transition-all',
                                activeMediaIndex === idx
                                    ? 'scale-105 border-brand ring-2 ring-brand/50'
                                    : 'border-white/20 opacity-70 hover:opacity-100',
                            ]"
                            @click="activeMediaIndex = idx"
                        >
                            <img
                                v-if="item.type === 'image'"
                                :src="item.preview"
                                :alt="item.name"
                                class="h-full w-full object-cover"
                            />
                            <div
                                v-else
                                class="flex h-full w-full items-center justify-center bg-zinc-800 text-[10px] font-bold text-white"
                            >
                                <Film class="h-4 w-4" />
                            </div>

                            <!-- Remove thumbnail button -->
                            <button
                                type="button"
                                class="absolute top-0.5 right-0.5 flex h-4 w-4 cursor-pointer items-center justify-center rounded-full bg-black/80 text-white opacity-0 transition-opacity group-hover:opacity-100"
                                title="Remove this photo"
                                @click.stop="removeMediaItem(idx)"
                            >
                                <X class="h-2.5 w-2.5" />
                            </button>
                        </div>

                        <!-- Add more button -->
                        <button
                            v-if="mediaFiles.length < 10"
                            type="button"
                            class="flex h-12 w-12 shrink-0 cursor-pointer flex-col items-center justify-center rounded-lg border border-dashed border-white/30 bg-white/5 text-white/80 transition-colors hover:border-brand hover:bg-brand/10 hover:text-brand"
                            title="Add more photos or videos"
                            data-test="add-more-media-button"
                            @click="mediaFileInputRef?.click()"
                        >
                            <Plus class="h-4 w-4" />
                            <span class="text-[9px] font-semibold">Add</span>
                        </button>
                    </div>
                </div>

                <!-- Right Column / Caption, Music, Privacy & Publish (Desktop 42%, Mobile 100%) -->
                <div
                    class="flex w-full flex-col justify-between border-t border-border/70 bg-card p-4 sm:p-5 md:w-[380px] md:border-t-0 md:border-l md:p-6"
                >
                    <div
                        class="max-h-[50vh] space-y-4 overflow-y-auto md:max-h-none"
                    >
                        <!-- Author Header -->
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/80 bg-brand/15 text-xs font-bold text-brand select-none"
                            >
                                <img
                                    v-if="currentUser?.avatar"
                                    :src="currentUser.avatar"
                                    :alt="currentUserName"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>{{ currentUserInitials }}</span>
                            </div>
                            <div class="min-w-0">
                                <h3
                                    class="text-xs font-bold text-foreground sm:text-sm"
                                >
                                    {{ currentUserName }}
                                </h3>
                                <div class="mt-0.5 flex items-center gap-1.5">
                                    <span
                                        class="text-[11px] text-muted-foreground"
                                        >Posting to</span
                                    >
                                    <div
                                        v-if="
                                            props.availableSections &&
                                            props.availableSections.length > 1
                                        "
                                        class="relative"
                                    >
                                        <button
                                            type="button"
                                            class="inline-flex cursor-pointer items-center gap-1 rounded-md border border-border/80 bg-muted/60 px-2 py-0.5 text-xs font-semibold text-foreground transition hover:bg-muted active:scale-95"
                                            data-test="composer-modal-section-trigger"
                                            @click.stop="
                                                isComposerSectionDropdownOpen =
                                                    !isComposerSectionDropdownOpen
                                            "
                                        >
                                            <span
                                                class="max-w-[130px] truncate sm:max-w-[170px]"
                                                >{{ postingSectionName }}</span
                                            >
                                            <ChevronDown
                                                class="h-3 w-3 text-muted-foreground"
                                            />
                                        </button>

                                        <div
                                            v-if="isComposerSectionDropdownOpen"
                                            class="absolute top-full left-0 z-50 mt-1 max-h-48 w-48 overflow-y-auto rounded-xl border border-border/80 bg-card p-1 shadow-2xl ring-1 ring-black/10"
                                            data-test="composer-modal-section-menu"
                                        >
                                            <button
                                                v-for="sec in props.availableSections"
                                                :key="sec.id"
                                                type="button"
                                                class="flex w-full cursor-pointer items-center justify-between rounded-lg px-2.5 py-1.5 text-left text-xs font-medium transition hover:bg-muted"
                                                :class="
                                                    sec.id === composerSectionId
                                                        ? 'bg-brand/10 font-bold text-brand'
                                                        : 'text-foreground'
                                                "
                                                @click="selectSection(sec.id)"
                                            >
                                                <span class="truncate">{{
                                                    sec.name
                                                }}</span>
                                                <Check
                                                    v-if="
                                                        sec.id ===
                                                        composerSectionId
                                                    "
                                                    class="h-3.5 w-3.5 shrink-0 text-brand"
                                                />
                                            </button>
                                        </div>
                                    </div>
                                    <span
                                        v-else
                                        class="text-xs font-semibold text-foreground"
                                    >
                                        {{ sectionDisplayName }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Caption Textarea -->
                        <div>
                            <textarea
                                v-model="composerForm.content"
                                rows="4"
                                maxlength="2000"
                                placeholder="Write a caption, share learning tips, or start a discussion..."
                                class="w-full resize-none rounded-xl border border-border/60 bg-muted/20 p-3 text-xs text-foreground placeholder:text-muted-foreground/70 focus:border-brand focus:ring-1 focus:ring-brand focus:outline-none sm:text-sm"
                                data-test="media-modal-caption-textarea"
                            />
                            <div
                                class="mt-1 flex items-center justify-between text-[11px] text-muted-foreground"
                            >
                                <span
                                    >{{ composerForm.content.length }} /
                                    2000</span
                                >
                                <span
                                    v-if="mediaFiles.length > 0"
                                    class="font-medium text-brand"
                                >
                                    {{ mediaFiles.length }} media item{{
                                        mediaFiles.length > 1 ? 's' : ''
                                    }}
                                    attached
                                </span>
                            </div>
                        </div>

                        <!-- Music Soundtrack Selector Row -->
                        <div
                            v-if="props.musicEnabled !== false"
                            class="rounded-xl border border-border/70 bg-muted/30 p-3"
                        >
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <div
                                        class="flex h-7 w-7 items-center justify-center rounded-lg bg-brand/15 text-brand"
                                    >
                                        <Music class="h-3.5 w-3.5" />
                                    </div>
                                    <div>
                                        <p
                                            class="text-xs font-semibold text-foreground"
                                        >
                                            Music Soundtrack
                                        </p>
                                        <p
                                            class="text-[10px] text-muted-foreground"
                                        >
                                            {{
                                                selectedMusicTrack
                                                    ? selectedMusicTrack.title +
                                                      ' • ' +
                                                      selectedMusicTrack.artist
                                                    : 'Add background music'
                                            }}
                                        </p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    class="cursor-pointer rounded-lg border border-border/60 bg-background px-2.5 py-1 text-xs font-semibold text-foreground transition hover:bg-muted"
                                    data-test="toggle-music-picker-button"
                                    @click="
                                        isMusicPickerOpen = !isMusicPickerOpen
                                    "
                                >
                                    {{
                                        selectedMusicTrack ? 'Change' : '+ Add'
                                    }}
                                </button>
                            </div>

                            <!-- Music Picker Track List -->
                            <div
                                v-if="isMusicPickerOpen"
                                class="mt-3 space-y-2 border-t border-border/50 pt-2.5"
                                data-test="music-tracks-picker"
                            >
                                <div
                                    v-if="
                                        !props.musicTracks ||
                                        props.musicTracks.length === 0
                                    "
                                    class="py-3 text-center text-xs text-muted-foreground"
                                >
                                    No music tracks available in this workspace.
                                </div>
                                <div
                                    v-for="track in props.musicTracks"
                                    :key="track.id"
                                    class="flex items-center justify-between rounded-lg p-1.5 transition hover:bg-muted/60"
                                    :class="
                                        selectedMusicTrackId === track.id
                                            ? 'border border-brand/40 bg-brand/10'
                                            : ''
                                    "
                                >
                                    <div
                                        class="flex min-w-0 items-center gap-2"
                                    >
                                        <!-- Play preview audio button -->
                                        <button
                                            type="button"
                                            class="flex h-7 w-7 shrink-0 cursor-pointer items-center justify-center rounded-full bg-foreground text-background transition hover:opacity-90 active:scale-95"
                                            :title="
                                                playingTrackId === track.id
                                                    ? 'Pause preview'
                                                    : 'Play preview'
                                            "
                                            @click.stop="
                                                toggleTrackPreview(track)
                                            "
                                        >
                                            <Pause
                                                v-if="
                                                    playingTrackId === track.id
                                                "
                                                class="h-3 w-3"
                                            />
                                            <Play
                                                v-else
                                                class="ml-0.5 h-3 w-3"
                                            />
                                        </button>
                                        <div class="min-w-0">
                                            <p
                                                class="truncate text-xs font-semibold text-foreground"
                                            >
                                                {{ track.title }}
                                            </p>
                                            <p
                                                class="truncate text-[10px] text-muted-foreground"
                                            >
                                                {{ track.artist }}
                                            </p>
                                        </div>
                                    </div>

                                    <button
                                        type="button"
                                        class="cursor-pointer rounded-md px-2 py-0.5 text-xs font-semibold"
                                        :class="
                                            selectedMusicTrackId === track.id
                                                ? 'text-rose-500 hover:text-rose-600'
                                                : 'text-brand hover:underline'
                                        "
                                        @click="selectMusicTrack(track)"
                                    >
                                        {{
                                            selectedMusicTrackId === track.id
                                                ? 'Remove'
                                                : 'Select'
                                        }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <!-- Anonymous Toggle & Visibility note -->
                        <div
                            class="space-y-2 rounded-xl border border-border/70 bg-muted/20 p-3"
                        >
                            <label
                                v-if="props.allowsAnonymous !== false"
                                class="flex cursor-pointer items-center gap-2 text-xs font-medium text-foreground select-none"
                            >
                                <input
                                    v-model="composerForm.is_anonymous"
                                    type="checkbox"
                                    class="h-3.5 w-3.5 rounded border-border text-brand focus:ring-0"
                                    data-test="media-modal-anonymous-checkbox"
                                />
                                <span>Post anonymously</span>
                            </label>
                            <p class="text-[11px] text-muted-foreground">
                                {{
                                    composerForm.is_anonymous
                                        ? 'Your name and avatar will be hidden from everyone.'
                                        : props.isSectionOnly
                                          ? 'Visible only to enrolled students in your section.'
                                          : 'Your identity is visible to your section, masked outside.'
                                }}
                            </p>
                        </div>
                        <!-- Upload / Validation Error Notice -->
                        <p
                            v-if="
                                mediaUploadError ||
                                composerForm.errors?.attachments ||
                                composerForm.errors?.attachment ||
                                composerForm.errors?.content
                            "
                            class="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-xs font-medium text-red-600 dark:text-red-400"
                            data-test="media-modal-error"
                        >
                            {{
                                mediaUploadError ||
                                composerForm.errors?.attachments ||
                                composerForm.errors?.attachment ||
                                composerForm.errors?.content
                            }}
                        </p>
                    </div>

                    <!-- Actions Bottom Bar -->
                    <div
                        class="mt-4 flex items-center justify-between border-t border-border/50 pt-3"
                    >
                        <button
                            type="button"
                            class="cursor-pointer rounded-xl px-3.5 py-2 text-xs font-semibold text-muted-foreground hover:bg-muted hover:text-foreground"
                            data-test="media-modal-cancel-button"
                            @click="closeMediaModal"
                        >
                            Discard
                        </button>

                        <button
                            type="button"
                            :disabled="
                                (!composerForm.content.trim() &&
                                    mediaFiles.length === 0) ||
                                composerForm.processing
                            "
                            class="inline-flex cursor-pointer items-center gap-1.5 rounded-full bg-foreground px-5 py-2 text-xs font-semibold text-background transition-opacity hover:opacity-90 disabled:cursor-not-allowed disabled:opacity-40"
                            data-test="media-modal-publish-button"
                            @click="submitMediaPost"
                        >
                            <Loader2
                                v-if="composerForm.processing"
                                class="h-3.5 w-3.5 animate-spin"
                            />
                            <span>{{
                                composerForm.processing
                                    ? 'Publishing...'
                                    : 'Share Post'
                            }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Post Lightbox / Full-screen Media Viewer Modal (Facebook / Instagram Style) ═══ -->
        <div
            v-if="isLightboxOpen && lightboxPost"
            class="fixed inset-0 z-[190] flex items-center justify-center p-2 backdrop-blur-md transition-all duration-200 sm:p-4 md:p-6"
            data-test="post-lightbox-modal"
            @click.self="closePostLightbox"
        >
            <!-- Ambient backdrop scrim -->
            <div
                class="fixed inset-0 -z-10 bg-black/85 backdrop-blur-md"
                data-test="post-lightbox-backdrop"
                @click="closePostLightbox"
            />

            <!-- Dialog Container -->
            <div
                class="relative flex h-full max-h-[92vh] w-full max-w-6xl flex-col overflow-hidden rounded-2xl border border-white/10 bg-card shadow-2xl sm:rounded-3xl md:flex-row dark:bg-zinc-950"
            >
                <!-- Left Stage: Large Media Viewport -->
                <div
                    class="relative flex min-h-[300px] flex-1 items-center justify-center overflow-hidden bg-black text-white select-none sm:min-h-[420px]"
                >
                    <!-- Close button in media viewport (visible on mobile) -->
                    <button
                        type="button"
                        class="absolute top-3 left-3 z-30 flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-xs transition hover:scale-105 hover:bg-black/80 active:scale-95 md:hidden"
                        aria-label="Close viewer"
                        data-test="lightbox-mobile-close"
                        @click="closePostLightbox"
                    >
                        <X class="h-4 w-4" />
                    </button>

                    <!-- Active Media (Image or Video) -->
                    <template v-if="activeLightboxMediaItem">
                        <video
                            v-if="activeLightboxMediaItem.type === 'video'"
                            :src="activeLightboxMediaItem.url"
                            controls
                            autoplay
                            class="max-h-[85vh] max-w-full object-contain"
                            data-test="lightbox-video"
                        />
                        <img
                            v-else
                            :src="activeLightboxMediaItem.url"
                            :alt="activeLightboxMediaItem.name || 'Post media'"
                            class="max-h-[85vh] max-w-full object-contain select-none"
                            data-test="lightbox-image"
                        />
                    </template>

                    <!-- Carousel Arrows (if multiple media) -->
                    <template
                        v-if="
                            lightboxPost.media && lightboxPost.media.length > 1
                        "
                    >
                        <button
                            type="button"
                            class="absolute top-1/2 left-3 flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-xs transition hover:scale-110 hover:bg-black/80 active:scale-95"
                            aria-label="Previous photo"
                            data-test="lightbox-prev-button"
                            @click="lightboxPrevMedia"
                        >
                            <ChevronLeft class="h-5 w-5" />
                        </button>
                        <button
                            type="button"
                            class="absolute top-1/2 right-3 flex h-10 w-10 -translate-y-1/2 cursor-pointer items-center justify-center rounded-full bg-black/60 text-white backdrop-blur-xs transition hover:scale-110 hover:bg-black/80 active:scale-95"
                            aria-label="Next photo"
                            data-test="lightbox-next-button"
                            @click="lightboxNextMedia"
                        >
                            <ChevronRight class="h-5 w-5" />
                        </button>

                        <!-- Counter badge -->
                        <div
                            class="absolute top-3.5 right-3.5 rounded-full bg-black/70 px-3 py-1 text-xs font-semibold text-white backdrop-blur-xs"
                            data-test="lightbox-counter"
                        >
                            {{ lightboxMediaIndex + 1 }} /
                            {{ lightboxPost.media.length }}
                        </div>
                    </template>
                </div>

                <!-- Right Stage: Sidebar with Author, Content, Reactions & Comments -->
                <div
                    class="flex w-full flex-col justify-between border-t border-border/70 bg-card md:w-[380px] md:border-t-0 md:border-l lg:w-[420px] dark:border-white/10 dark:bg-zinc-900"
                >
                    <!-- Sidebar Top Header -->
                    <div
                        class="flex items-center justify-between border-b border-border/60 p-3.5 sm:p-4"
                    >
                        <div class="flex items-center gap-2.5">
                            <div
                                class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/80 bg-brand/15 text-xs font-bold text-brand select-none"
                            >
                                <img
                                    v-if="lightboxPost.author.avatar"
                                    :src="lightboxPost.author.avatar"
                                    :alt="lightboxPost.author.name"
                                    class="h-full w-full object-cover"
                                />
                                <span v-else>
                                    {{ getInitials(lightboxPost.author.name) }}
                                </span>
                            </div>
                            <div class="min-w-0">
                                <h3
                                    class="truncate text-xs font-bold text-foreground sm:text-sm"
                                    data-test="lightbox-author-name"
                                >
                                    {{
                                        lightboxPost.author.is_masked
                                            ? lightboxPost.author.name
                                            : lightboxPost.author.name ||
                                              lightboxPost.author.handle
                                    }}
                                </h3>
                                <p class="text-[11px] text-muted-foreground">
                                    {{
                                        lightboxPost.author.is_masked
                                            ? lightboxPost.author
                                                  .section_name || 'Anonymous'
                                            : lightboxPost.author
                                                  .section_name ||
                                              sectionDisplayName
                                    }}
                                    · {{ lightboxPost.created_at }}
                                </p>
                            </div>
                        </div>

                        <!-- Close button on desktop -->
                        <button
                            type="button"
                            class="hidden h-7 w-7 cursor-pointer items-center justify-center rounded-full bg-muted/60 text-muted-foreground transition hover:bg-muted hover:text-foreground md:flex"
                            data-test="lightbox-sidebar-close"
                            @click="closePostLightbox"
                        >
                            <X class="h-4 w-4" />
                        </button>
                    </div>

                    <!-- Scrollable Post Caption, Soundtrack & Comments List -->
                    <div
                        class="flex-1 space-y-4 overflow-y-auto p-3.5 sm:p-4"
                        data-test="lightbox-comments-list"
                    >
                        <!-- Post Text Content -->
                        <p
                            v-if="lightboxPost.content"
                            class="text-xs leading-relaxed text-foreground sm:text-[13px]"
                            data-test="lightbox-post-content"
                        >
                            {{ lightboxPost.content }}
                        </p>

                        <!-- Attached Soundtrack if any -->
                        <div
                            v-if="
                                props.musicEnabled !== false &&
                                lightboxPost.music
                            "
                            class="pt-1"
                        >
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-2 rounded-full border border-brand/35 bg-brand/10 px-3 py-1 text-xs font-semibold text-brand transition hover:bg-brand/20 active:scale-95"
                                @click="toggleFeedPostMusic(lightboxPost)"
                            >
                                <Music
                                    v-if="
                                        activePlayingPostId === lightboxPost.id
                                    "
                                    class="h-3.5 w-3.5 animate-pulse text-brand"
                                />
                                <Music v-else class="h-3.5 w-3.5 text-brand" />
                                <span
                                    class="max-w-[200px] truncate sm:max-w-xs"
                                >
                                    {{ lightboxPost.music.title }} •
                                    {{ lightboxPost.music.artist }}
                                </span>
                                <span
                                    class="rounded-full bg-brand/20 px-1.5 py-0.5 text-[9px] font-bold tracking-wider text-brand uppercase"
                                >
                                    {{
                                        activePlayingPostId === lightboxPost.id
                                            ? 'Pause'
                                            : 'Play'
                                    }}
                                </span>
                            </button>
                        </div>

                        <div class="border-t border-border/50 pt-2" />

                        <!-- Comments Section Header -->
                        <div
                            class="flex items-center justify-between text-xs font-semibold text-muted-foreground"
                        >
                            <span
                                >Comments ({{
                                    currentLightboxCommentsCount
                                }})</span
                            >
                        </div>

                        <!-- Empty state if no comments -->
                        <div
                            v-if="currentLightboxComments.length === 0"
                            class="py-8 text-center text-xs text-muted-foreground"
                        >
                            No comments yet. Be the first to share your
                            thoughts!
                        </div>

                        <!-- Comments & Replies Tree -->
                        <div v-else class="space-y-3">
                            <div
                                v-for="c in currentLightboxComments"
                                :key="c.id"
                                class="space-y-2 rounded-xl bg-muted/20 p-2.5 text-xs text-foreground"
                                data-test="lightbox-comment-item"
                            >
                                <div class="flex items-start gap-2">
                                    <div
                                        class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/80 bg-brand/15 text-[10px] font-bold text-brand select-none"
                                    >
                                        <img
                                            v-if="c.author_avatar"
                                            :src="c.author_avatar"
                                            :alt="c.author_name"
                                            class="h-full w-full object-cover"
                                        />
                                        <span v-else>{{
                                            getInitials(c.author_name)
                                        }}</span>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-1.5">
                                            <span
                                                class="font-bold text-foreground"
                                            >
                                                {{
                                                    c.is_masked
                                                        ? c.author_name
                                                        : c.author_name ||
                                                          c.author_handle
                                                }}
                                            </span>
                                            <span
                                                class="text-[10px] text-muted-foreground"
                                                >· {{ c.created_at }}</span
                                            >
                                        </div>
                                        <p
                                            class="mt-0.5 text-xs leading-relaxed text-foreground/90"
                                        >
                                            {{ c.content }}
                                        </p>

                                        <!-- Comment Actions (Like & Reply) -->
                                        <div
                                            class="mt-1 flex items-center gap-3 text-[11px] text-muted-foreground"
                                        >
                                            <button
                                                type="button"
                                                class="cursor-pointer transition hover:text-foreground"
                                                :class="
                                                    isCommentLiked(c.id)
                                                        ? 'font-semibold text-rose-500'
                                                        : ''
                                                "
                                                @click="toggleCommentLike(c.id)"
                                            >
                                                {{
                                                    isCommentLiked(c.id)
                                                        ? 'Liked'
                                                        : 'Like'
                                                }}
                                                <span
                                                    v-if="
                                                        getCommentLikesCount(
                                                            c.id,
                                                        ) > 0
                                                    "
                                                    >({{
                                                        getCommentLikesCount(
                                                            c.id,
                                                        )
                                                    }})</span
                                                >
                                            </button>
                                            <button
                                                type="button"
                                                class="cursor-pointer font-medium hover:text-foreground"
                                                @click="
                                                    setReplyingTo(
                                                        lightboxCommentKey,
                                                        c,
                                                    )
                                                "
                                            >
                                                Reply
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Nested Replies -->
                                <div
                                    v-if="c.replies && c.replies.length > 0"
                                    class="ml-6 space-y-2 border-l-2 border-border/60 pl-2.5"
                                >
                                    <div
                                        v-for="r in c.replies"
                                        :key="r.id"
                                        class="flex items-start gap-2"
                                    >
                                        <div
                                            class="flex h-5 w-5 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/80 bg-brand/15 text-[9px] font-bold text-brand select-none"
                                        >
                                            <img
                                                v-if="r.author_avatar"
                                                :src="r.author_avatar"
                                                :alt="r.author_name"
                                                class="h-full w-full object-cover"
                                            />
                                            <span v-else>{{
                                                getInitials(r.author_name)
                                            }}</span>
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div
                                                class="flex items-center gap-1.5"
                                            >
                                                <span
                                                    class="text-[11px] font-bold text-foreground"
                                                >
                                                    {{
                                                        r.is_masked
                                                            ? r.author_name
                                                            : r.author_name ||
                                                              r.author_handle
                                                    }}
                                                </span>
                                                <span
                                                    class="text-[9px] text-muted-foreground"
                                                    >· {{ r.created_at }}</span
                                                >
                                            </div>
                                            <p
                                                class="text-[11px] leading-relaxed text-foreground/90"
                                            >
                                                {{ r.content }}
                                            </p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Sidebar Bottom: Like/Comment/Share Bar & Add Comment Form -->
                    <div
                        class="border-t border-border/60 bg-muted/20 p-3 sm:p-3.5"
                    >
                        <!-- Action Icons Bar -->
                        <div
                            class="mb-2.5 flex items-center justify-between text-xs"
                        >
                            <div class="flex items-center gap-4">
                                <button
                                    type="button"
                                    class="flex cursor-pointer items-center gap-1.5 transition active:scale-95"
                                    :class="
                                        currentLightboxLiked
                                            ? 'font-semibold text-rose-500'
                                            : 'text-muted-foreground hover:text-foreground'
                                    "
                                    data-test="lightbox-like-button"
                                    :title="
                                        activeLightboxMediaItem?.id
                                            ? 'Like this media'
                                            : 'Like this post'
                                    "
                                    @click="
                                        toggleMediaLike(
                                            lightboxPost,
                                            activeLightboxMediaItem,
                                        )
                                    "
                                >
                                    <Heart
                                        class="h-4 w-4"
                                        :class="
                                            currentLightboxLiked
                                                ? 'fill-rose-500'
                                                : ''
                                        "
                                    />
                                    <span>{{ currentLightboxLikesCount }}</span>
                                </button>

                                <div
                                    class="flex items-center gap-1 text-muted-foreground"
                                >
                                    <MessageCircle class="h-4 w-4" />
                                    <span>{{
                                        currentLightboxCommentsCount
                                    }}</span>
                                </div>

                                <button
                                    type="button"
                                    class="cursor-pointer text-muted-foreground transition hover:text-foreground active:scale-95"
                                    title="Share post"
                                    @click="handleSharePost(lightboxPost)"
                                >
                                    <Send class="h-3.5 w-3.5" />
                                </button>
                            </div>

                            <div class="flex items-center gap-2.5">
                                <span
                                    v-if="
                                        lightboxPost.media &&
                                        lightboxPost.media.length > 1
                                    "
                                    class="text-[11px] font-medium text-muted-foreground"
                                    data-test="lightbox-post-total-likes"
                                >
                                    {{ getPostLikesCount(lightboxPost.id) }}
                                    post like{{
                                        getPostLikesCount(lightboxPost.id) === 1
                                            ? ''
                                            : 's'
                                    }}
                                </span>

                                <button
                                    type="button"
                                    class="cursor-pointer text-muted-foreground transition hover:text-foreground active:scale-95"
                                    title="Bookmark post"
                                    @click="toggleBookmark(lightboxPost.id)"
                                >
                                    <Bookmark
                                        class="h-4 w-4"
                                        :class="
                                            isPostBookmarked(lightboxPost.id)
                                                ? 'fill-amber-500 text-amber-500'
                                                : ''
                                        "
                                    />
                                </button>
                            </div>
                        </div>

                        <!-- Replying to Indicator Pill -->
                        <div
                            v-if="replyingTo[lightboxCommentKey]"
                            class="mb-2 flex items-center justify-between rounded-lg bg-brand/10 px-2.5 py-1 text-[11px] text-brand"
                        >
                            <span
                                >Replying to @{{
                                    replyingTo[lightboxCommentKey]
                                        ?.author_handle
                                }}</span
                            >
                            <button
                                type="button"
                                class="cursor-pointer text-muted-foreground hover:text-foreground"
                                @click="cancelReply(lightboxCommentKey)"
                            >
                                <X class="h-3 w-3" />
                            </button>
                        </div>

                        <!-- Add Comment Input Row -->
                        <div class="flex items-center gap-2">
                            <input
                                v-model="commentInputs[lightboxCommentKey]"
                                type="text"
                                maxlength="1000"
                                :placeholder="
                                    replyingTo[lightboxCommentKey]
                                        ? `Reply to @${replyingTo[lightboxCommentKey]?.author_handle}…`
                                        : 'Add a comment…'
                                "
                                class="flex-1 rounded-full border border-border/70 bg-background px-3.5 py-2 text-xs text-foreground placeholder:text-muted-foreground/70 focus:border-brand focus:outline-none"
                                data-test="lightbox-comment-input"
                                @keydown.enter.prevent="
                                    handleAddLightboxComment(
                                        lightboxPost,
                                        activeLightboxMediaItem,
                                    )
                                "
                            />
                            <button
                                type="button"
                                :disabled="
                                    !commentInputs[
                                        lightboxCommentKey
                                    ]?.trim() ||
                                    isSubmittingComment[lightboxCommentKey]
                                "
                                class="cursor-pointer rounded-full bg-foreground px-3.5 py-1.5 text-xs font-semibold text-background transition hover:opacity-90 disabled:opacity-40"
                                data-test="lightbox-submit-comment-button"
                                @click="
                                    handleAddLightboxComment(
                                        lightboxPost,
                                        activeLightboxMediaItem,
                                    )
                                "
                            >
                                <Loader2
                                    v-if="
                                        isSubmittingComment[lightboxCommentKey]
                                    "
                                    class="h-3 w-3 animate-spin"
                                />
                                <span v-else>Post</span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══ Trending Topic Related Posts Modal ═══ -->
        <div
            v-if="selectedTrendingTopic"
            class="fixed inset-0 z-[165] flex items-center justify-center p-4"
            data-test="trending-topic-modal"
        >
            <div
                class="fixed inset-0 bg-black/65 backdrop-blur-xs transition-opacity"
                data-test="trending-topic-modal-backdrop"
                @click="closeTrendingTopicModal"
            />
            <div
                class="relative z-[175] flex max-h-[86vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-border/70 bg-card shadow-2xl"
            >
                <!-- Modal Header -->
                <div
                    class="flex shrink-0 items-center justify-between border-b border-border/50 px-5 py-4"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand/15 text-sm font-bold text-brand"
                        >
                            #
                        </div>
                        <div>
                            <h2
                                class="text-sm font-bold text-foreground"
                                data-test="trending-topic-modal-title"
                            >
                                #{{ selectedTrendingTopic.name }}
                            </h2>
                            <p class="text-[11px] text-muted-foreground">
                                {{ trendingRelatedPosts.length }}
                                {{
                                    trendingRelatedPosts.length === 1
                                        ? 'related post'
                                        : 'related posts'
                                }}
                                in {{ sectionDisplayName }}
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-muted/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        aria-label="Close trending topic modal"
                        data-test="close-trending-modal-button"
                        @click="closeTrendingTopicModal"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <!-- Modal Body: Related Posts List -->
                <div class="flex-1 space-y-3 overflow-y-auto p-4 sm:p-5">
                    <div
                        v-if="trendingRelatedPosts.length === 0"
                        class="rounded-2xl border border-dashed border-border/70 bg-muted/20 p-8 text-center text-xs text-muted-foreground"
                    >
                        No visible posts found for #{{
                            selectedTrendingTopic.name
                        }}.
                    </div>

                    <article
                        v-for="post in trendingRelatedPosts"
                        :key="'trending-modal-post-' + post.id"
                        data-test="trending-modal-post-item"
                        class="overflow-hidden rounded-2xl border border-border/60 bg-background/80 p-4 shadow-2xs transition-colors hover:border-border"
                    >
                        <!-- Author Row -->
                        <div class="flex items-center justify-between gap-2">
                            <div class="flex items-center gap-2.5">
                                <div
                                    v-if="post.author.is_masked"
                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full border border-border/80 bg-muted text-xs font-semibold text-muted-foreground"
                                >
                                    <EyeOff class="h-4 w-4" />
                                </div>
                                <div
                                    v-else
                                    class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/60 bg-emerald-500/10 text-xs font-bold text-emerald-600 dark:text-emerald-400"
                                >
                                    <img
                                        v-if="post.author.avatar"
                                        :src="post.author.avatar"
                                        :alt="post.author.name"
                                        class="h-full w-full object-cover"
                                    />
                                    <span v-else>{{
                                        getInitials(post.author.name)
                                    }}</span>
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center gap-1.5">
                                        <span
                                            class="truncate text-xs font-bold text-foreground"
                                        >
                                            {{ post.author.name }}
                                        </span>
                                        <span
                                            v-if="
                                                !post.author.is_masked &&
                                                post.author.handle
                                            "
                                            class="truncate text-[11px] text-muted-foreground"
                                        >
                                            @{{ post.author.handle }}
                                        </span>
                                    </div>
                                    <p
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{
                                            post.author.section_name ||
                                            sectionDisplayName
                                        }}
                                        · {{ post.created_at }}
                                    </p>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="inline-flex shrink-0 cursor-pointer items-center gap-1 rounded-full border border-border/70 bg-muted/40 px-2.5 py-1 text-[11px] font-medium text-foreground transition hover:border-brand/50 hover:bg-brand/10 hover:text-brand"
                                data-test="trending-modal-view-post-button"
                                @click="jumpToPostFromTrending(post)"
                            >
                                <span>View in Feed</span>
                                <ExternalLink class="h-3 w-3" />
                            </button>
                        </div>

                        <!-- Post Content -->
                        <p
                            class="mt-3 text-xs leading-relaxed whitespace-pre-wrap text-foreground sm:text-[13px]"
                        >
                            {{ post.content }}
                        </p>

                        <!-- Media Preview if present -->
                        <div
                            v-if="post.media && post.media.length > 0"
                            class="mt-3 overflow-hidden rounded-xl border border-border/50 bg-black/5"
                        >
                            <img
                                v-if="post.media[0].type === 'image'"
                                :src="post.media[0].url"
                                alt="Post attachment"
                                class="max-h-56 w-full object-cover"
                            />
                            <video
                                v-else
                                :src="post.media[0].url"
                                controls
                                preload="metadata"
                                class="max-h-56 w-full object-cover"
                            />
                        </div>

                        <!-- Music Track Badge if present -->
                        <div
                            v-if="post.music"
                            class="mt-2.5 inline-flex items-center gap-1.5 rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-[11px] font-medium text-amber-600 dark:text-amber-400"
                        >
                            <Music class="h-3 w-3 shrink-0" />
                            <span class="truncate">
                                {{ post.music.title }}
                                <template v-if="post.music.artist">
                                    — {{ post.music.artist }}
                                </template>
                            </span>
                        </div>

                        <!-- Engagement Footer -->
                        <div
                            class="mt-3 flex items-center justify-between border-t border-border/40 pt-2.5 text-[11px] text-muted-foreground"
                        >
                            <div class="flex items-center gap-4">
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 transition-colors hover:text-rose-500"
                                    :class="
                                        post.is_liked
                                            ? 'font-semibold text-rose-500'
                                            : ''
                                    "
                                    @click="toggleLike(post.id)"
                                >
                                    <Heart
                                        class="h-3.5 w-3.5"
                                        :class="
                                            post.is_liked ? 'fill-current' : ''
                                        "
                                    />
                                    <span>{{ post.likes_count }}</span>
                                </button>
                                <button
                                    type="button"
                                    class="inline-flex cursor-pointer items-center gap-1 transition-colors hover:text-foreground"
                                    @click="jumpToPostFromTrending(post)"
                                >
                                    <MessageCircle class="h-3.5 w-3.5" />
                                    <span>{{ post.comments_count }}</span>
                                </button>
                            </div>
                            <button
                                type="button"
                                class="inline-flex cursor-pointer items-center gap-1 transition-colors hover:text-amber-500"
                                :class="
                                    post.is_bookmarked ? 'text-amber-500' : ''
                                "
                                @click="toggleBookmark(post.id)"
                            >
                                <Bookmark
                                    class="h-3.5 w-3.5"
                                    :class="
                                        post.is_bookmarked ? 'fill-current' : ''
                                    "
                                />
                            </button>
                        </div>
                    </article>
                </div>
            </div>
        </div>

        <!-- ═══ Hidden Own Posts Modal (Archive / Unhide) ═══ -->
        <div
            v-if="isHiddenPostsModalOpen"
            class="fixed inset-0 z-[165] flex items-center justify-center p-4"
            data-test="hidden-posts-modal"
        >
            <div
                class="fixed inset-0 bg-black/65 backdrop-blur-xs transition-opacity"
                @click="isHiddenPostsModalOpen = false"
            />
            <div
                class="relative z-[175] flex max-h-[84vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl border border-border/70 bg-card shadow-2xl"
            >
                <div
                    class="flex shrink-0 items-center justify-between border-b border-border/50 px-5 py-4"
                >
                    <div class="flex items-center gap-2.5">
                        <div
                            class="flex h-9 w-9 items-center justify-center rounded-xl bg-muted text-muted-foreground"
                        >
                            <EyeOff class="h-4 w-4" />
                        </div>
                        <div>
                            <h2 class="text-sm font-bold text-foreground">
                                Hidden Posts
                            </h2>
                            <p class="text-[11px] text-muted-foreground">
                                Hidden from the lounge feed and trending tags
                                until you unhide them
                            </p>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-muted/60 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                        data-test="close-hidden-posts-modal-button"
                        @click="isHiddenPostsModalOpen = false"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>

                <div class="flex-1 space-y-3 overflow-y-auto p-4 sm:p-5">
                    <div
                        v-if="localHiddenOwnPosts.length === 0"
                        class="rounded-2xl border border-dashed border-border/70 bg-muted/20 p-8 text-center text-xs text-muted-foreground"
                    >
                        You have no hidden posts.
                    </div>

                    <article
                        v-for="post in localHiddenOwnPosts"
                        :key="'hidden-own-post-' + post.id"
                        data-test="hidden-own-post-item"
                        class="flex items-start justify-between gap-3 rounded-2xl border border-border/60 bg-background/80 p-3.5 shadow-2xs"
                    >
                        <div class="min-w-0 flex-1">
                            <p class="text-[11px] text-muted-foreground">
                                {{ post.created_at }}
                            </p>
                            <p
                                class="mt-1 line-clamp-3 text-xs leading-relaxed whitespace-pre-wrap text-foreground"
                            >
                                {{ post.content || '(Media post)' }}
                            </p>
                        </div>
                        <button
                            type="button"
                            data-test="unhide-post-button"
                            class="inline-flex shrink-0 cursor-pointer items-center gap-1.5 rounded-full border border-border/70 bg-card px-3 py-1.5 text-xs font-semibold text-foreground transition hover:border-brand/50 hover:bg-brand/10 hover:text-brand"
                            @click="unhidePost(post)"
                        >
                            <span>Unhide</span>
                        </button>
                    </article>
                </div>
            </div>
        </div>

        <!-- Curtain Wipe Overlay -->
        <CurtainWipeOverlay
            ref="curtainRef"
            title="Dashboard"
            subtitle="Returning to dashboard..."
            expected-mount-direction="to_community"
        />

        <!-- Standalone Logout Confirmation Modal -->
        <LogoutConfirmationModal
            :open="showLogoutModal"
            @close="showLogoutModal = false"
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

.flying-reaction-item {
    animation: floatUpFade 1.2s cubic-bezier(0.2, 0.8, 0.3, 1) forwards;
}

@keyframes floatUpFade {
    0% {
        transform: translateY(0) scale(0.6) rotate(0deg);
        opacity: 0;
    }
    15% {
        transform: translateY(-40px) scale(1.15)
            rotate(var(--particle-rot, 0deg));
        opacity: 1;
    }
    100% {
        transform: translateY(-340px) scale(0.9)
            rotate(var(--particle-rot, 0deg));
        opacity: 0;
    }
}
</style>
