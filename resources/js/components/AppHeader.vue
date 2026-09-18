<script setup lang="ts">
import { Link, router, usePage } from '@inertiajs/vue3';
import { useEcho } from '@laravel/echo-vue';
import {
    BookOpen,
    Check,
    Folder,
    LayoutGrid,
    Menu,
    Search,
    Moon,
    Sun,
    Bell,
    X,
    Zap,
    Shield,
    TrendingUp,
    Users,
} from 'lucide-vue-next';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import LogoutConfirmationModal from '@/components/LogoutConfirmationModal.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    NavigationMenu,
    NavigationMenuItem,
    NavigationMenuList,
    navigationMenuTriggerStyle,
} from '@/components/ui/navigation-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import UserMenuContent from '@/components/UserMenuContent.vue';
import { useAppearance } from '@/composables/useAppearance';
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const showLogoutDialog = ref(false);
const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();
const { appearance, toggleTheme } = useAppearance();

// Laravel broadcasts notifications on the authenticated user's private model
// channel. With Pusher configured, the notification bell updates immediately;
// without Pusher this listener is simply inactive and normal navigation still
// refreshes the same database-backed inbox.
const notificationChannel = `App.Models.User.${page.props.auth.user?.id}`;
useEcho(
    notificationChannel,
    'Illuminate\\Notifications\\Events\\BroadcastNotificationCreated',
    () => router.reload({ only: ['notifications'] }),
);

const sectionName = computed(
    () => (page.props.sectionName as string | undefined) || '',
);

interface HeaderNotification {
    id: string;
    type: string;
    icon: string;
    title: string;
    message?: string | null;
    meta?: string | null;
    image?: string | null;
    href?: string | null;
    inviteId?: number | null;
    assignmentId?: number | null;
    readAt?: string | null;
    createdAt?: string | null;
}

const notifications = computed<{
    unreadCount: number;
    items: HeaderNotification[];
}>(
    () =>
        (page.props.notifications as
            | { unreadCount: number; items: HeaderNotification[] }
            | undefined) ?? {
            unreadCount: 0,
            items: [],
        },
);

const currentTime = ref('');
const currentDate = ref('');
let timer: ReturnType<typeof setInterval>;

const updateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
    currentDate.value = now.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

onMounted(() => {
    updateTime();
    timer = setInterval(updateTime, 1000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
});

const notificationIcon = (icon: string) => {
    if (icon === 'zap') return Zap;
    if (icon === 'shield') return Shield;
    if (icon === 'trending-up') return TrendingUp;
    if (icon === 'users') return Users;

    return Bell;
};

const markNotificationAsRead = (
    notificationId: string,
    href?: string | null,
) => {
    router.post(
        `/notifications/${notificationId}/read`,
        {},
        {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                if (href) {
                    router.visit(href);
                }
            },
        },
    );
};

// Group invites carry inline actions: accept/decline straight from the bell.
// If the invite is no longer actionable (expired/cancelled/handled on another
// device), the request errors out and we simply clear the notification.
const respondToInvite = (
    notification: HeaderNotification,
    action: 'accept' | 'decline',
) => {
    if (!notification.inviteId || !notification.assignmentId) return;

    router.post(
        `/assignments/${notification.assignmentId}/invites/${notification.inviteId}/respond`,
        { action },
        {
            preserveScroll: true,
            preserveState: true,
            only: ['notifications'],
            onSuccess: () => {
                markNotificationAsRead(notification.id);
            },
            onError: () => {
                markNotificationAsRead(notification.id);
            },
        },
    );
};

const markAllNotificationsAsRead = () => {
    router.post(
        '/notifications/read-all',
        {},
        {
            preserveScroll: true,
            preserveState: true,
        },
    );
};

const activeItemStyles =
    'text-neutral-900 dark:bg-neutral-800 dark:text-neutral-100';

const mainNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const rightNavItems: NavItem[] = [
    {
        title: 'Repository',
        href: 'https://github.com/laravel/vue-starter-kit',
        icon: Folder,
    },
    {
        title: 'Documentation',
        href: 'https://laravel.com/docs/starter-kits#vue',
        icon: BookOpen,
    },
];
</script>

<template>
    <div>
        <div class="border-b border-sidebar-border/80">
            <div class="mx-auto flex h-14 items-center px-4 md:max-w-7xl">
                <!-- Mobile Menu -->
                <div class="lg:hidden">
                    <Sheet>
                        <SheetTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="mr-2 h-9 w-9"
                            >
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="left" class="w-[300px] p-6">
                            <SheetTitle class="sr-only"
                                >Navigation menu</SheetTitle
                            >
                            <SheetHeader class="flex justify-start text-left">
                                <AppLogoIcon
                                    class="size-6 fill-current text-black dark:text-white"
                                />
                            </SheetHeader>
                            <div
                                class="flex h-full flex-1 flex-col justify-between space-y-4 py-6"
                            >
                                <nav class="-mx-3 space-y-1">
                                    <Link
                                        v-for="item in mainNavItems"
                                        :key="item.title"
                                        :href="item.href"
                                        class="flex items-center gap-x-3 rounded-lg px-3 py-2 text-sm font-medium hover:bg-accent"
                                        :class="
                                            whenCurrentUrl(
                                                item.href,
                                                activeItemStyles,
                                            )
                                        "
                                    >
                                        <component
                                            v-if="item.icon"
                                            :is="item.icon"
                                            class="h-5 w-5"
                                        />
                                        {{ item.title }}
                                    </Link>
                                    <div
                                        v-if="sectionName"
                                        class="mt-3 flex items-center gap-2 rounded-lg border border-border/30 bg-primary/[0.04] px-3 py-2"
                                    >
                                        <div
                                            class="h-1.5 w-1.5 rounded-full bg-primary"
                                        ></div>
                                        <span
                                            class="truncate text-[10px] font-medium text-muted-foreground"
                                        >
                                            {{ sectionName }}
                                        </span>
                                    </div>
                                </nav>
                                <div class="flex flex-col space-y-4">
                                    <a
                                        v-for="item in rightNavItems"
                                        :key="item.title"
                                        :href="toUrl(item.href)"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        class="flex items-center space-x-2 text-sm font-medium"
                                    >
                                        <component
                                            v-if="item.icon"
                                            :is="item.icon"
                                            class="h-5 w-5"
                                        />
                                        <span>{{ item.title }}</span>
                                    </a>
                                </div>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>

                <Link :href="dashboard()" class="flex items-center gap-x-2">
                    <AppLogo />
                </Link>

                <!-- Desktop Menu -->
                <div class="hidden h-full lg:flex lg:flex-1">
                    <!-- Section indicator -->
                    <div
                        v-if="sectionName"
                        class="ml-4 hidden items-center gap-1.5 rounded-lg border border-border/30 bg-primary/[0.03] px-2.5 py-1 lg:flex"
                    >
                        <div class="h-1.5 w-1.5 rounded-full bg-primary"></div>
                        <span
                            class="max-w-[120px] truncate text-[10px] font-medium text-muted-foreground"
                        >
                            {{ sectionName }}
                        </span>
                    </div>

                    <NavigationMenu class="ml-10 flex h-full items-stretch">
                        <NavigationMenuList
                            class="flex h-full items-stretch space-x-2"
                        >
                            <NavigationMenuItem
                                v-for="(item, index) in mainNavItems"
                                :key="index"
                                class="relative flex h-full items-center"
                            >
                                <Link
                                    :class="[
                                        navigationMenuTriggerStyle(),
                                        whenCurrentUrl(
                                            item.href,
                                            activeItemStyles,
                                        ),
                                        'h-9 cursor-pointer px-3',
                                    ]"
                                    :href="item.href"
                                >
                                    <component
                                        v-if="item.icon"
                                        :is="item.icon"
                                        class="mr-2 h-4 w-4"
                                    />
                                    {{ item.title }}
                                </Link>
                                <div
                                    v-if="isCurrentUrl(item.href)"
                                    class="absolute bottom-0 left-0 h-0.5 w-full translate-y-px bg-black dark:bg-white"
                                ></div>
                            </NavigationMenuItem>
                        </NavigationMenuList>
                    </NavigationMenu>
                </div>

                <div class="ml-auto flex items-center space-x-2">
                    <div class="relative flex items-center space-x-1">
                        <Button
                            variant="ghost"
                            size="icon"
                            class="group h-9 w-9 cursor-pointer"
                        >
                            <Search
                                class="size-5 opacity-80 group-hover:opacity-100"
                            />
                        </Button>

                        <div class="ml-2 flex items-center gap-2 sm:gap-4">
                            <DropdownMenu>
                                <DropdownMenuTrigger :as-child="true">
                                    <button
                                        class="relative inline-flex items-center justify-center rounded-md p-1.5 text-sm font-medium transition-colors hover:bg-neutral-100 sm:p-2 dark:hover:bg-neutral-800"
                                        aria-label="Open notifications"
                                    >
                                        <Bell class="h-4 w-4 sm:h-5 sm:w-5" />
                                        <span
                                            v-if="notifications.unreadCount > 0"
                                            class="absolute -top-0.5 -right-0.5 min-w-4 rounded-full bg-primary px-1 text-[9px] leading-4 font-black text-primary-foreground"
                                        >
                                            {{
                                                notifications.unreadCount > 9
                                                    ? '9+'
                                                    : notifications.unreadCount
                                            }}
                                        </span>
                                    </button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent
                                    align="end"
                                    class="w-80 overscroll-contain border-border/80 bg-background/95 p-0 shadow-2xl backdrop-blur-xl supports-[backdrop-filter]:bg-background/90"
                                >
                                    <div
                                        class="flex items-center justify-between border-b border-border/60 px-3 py-2"
                                    >
                                        <div>
                                            <p class="text-sm font-bold">
                                                Notifications
                                            </p>
                                            <p
                                                class="text-[10px] tracking-widest text-muted-foreground uppercase"
                                            >
                                                {{ notifications.unreadCount }}
                                                unread
                                            </p>
                                        </div>
                                        <button
                                            v-if="notifications.unreadCount > 0"
                                            class="text-[10px] font-semibold text-primary transition-colors hover:text-primary/80"
                                            @click="markAllNotificationsAsRead"
                                        >
                                            Mark all read
                                        </button>
                                    </div>

                                    <div
                                        v-if="notifications.items.length > 0"
                                        data-lenis-prevent
                                        class="max-h-[min(24rem,50vh)] overflow-y-auto overscroll-contain p-2"
                                    >
                                        <button
                                            v-for="notification in notifications.items"
                                            :key="notification.id"
                                            class="flex w-full items-start gap-3 rounded-lg px-2 py-2 text-left transition-colors hover:bg-accent/70"
                                            @click="
                                                markNotificationAsRead(
                                                    notification.id,
                                                    notification.href,
                                                )
                                            "
                                        >
                                            <div
                                                class="flex h-9 w-9 shrink-0 items-center justify-center overflow-hidden rounded-lg bg-primary/10"
                                            >
                                                <img
                                                    v-if="notification.image"
                                                    :src="notification.image"
                                                    :alt="notification.title"
                                                    class="h-full w-full object-cover"
                                                />
                                                <component
                                                    v-else
                                                    :is="
                                                        notificationIcon(
                                                            notification.icon,
                                                        )
                                                    "
                                                    class="h-4 w-4 text-primary"
                                                />
                                            </div>
                                            <div class="min-w-0 flex-1">
                                                <div
                                                    class="flex items-center gap-2"
                                                >
                                                    <p
                                                        class="truncate text-xs font-bold text-foreground"
                                                    >
                                                        {{ notification.title }}
                                                    </p>
                                                    <span
                                                        v-if="
                                                            !notification.readAt
                                                        "
                                                        class="h-2 w-2 shrink-0 rounded-full bg-primary"
                                                    />
                                                </div>
                                                <p
                                                    v-if="notification.message"
                                                    class="mt-0.5 line-clamp-2 text-[11px] text-muted-foreground"
                                                >
                                                    {{ notification.message }}
                                                </p>
                                                <div
                                                    class="mt-1 flex items-center justify-between gap-2"
                                                >
                                                    <span
                                                        class="truncate text-[10px] tracking-wide text-muted-foreground/80 uppercase"
                                                    >
                                                        {{
                                                            notification.meta ??
                                                            notification.type
                                                        }}
                                                    </span>
                                                    <span
                                                        class="shrink-0 text-[10px] text-muted-foreground/70"
                                                    >
                                                        {{
                                                            notification.createdAt
                                                        }}
                                                    </span>
                                                </div>

                                                <!-- Inline accept / decline for pending group invites -->
                                                <div
                                                    v-if="
                                                        notification.type ===
                                                            'assignment_invite' &&
                                                        notification.inviteId &&
                                                        notification.assignmentId &&
                                                        !notification.readAt
                                                    "
                                                    class="mt-1.5 flex items-center gap-1.5"
                                                    @click.stop
                                                >
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-6 items-center gap-1 rounded-full bg-emerald-600 px-2.5 text-[10px] font-bold text-white transition-colors hover:bg-emerald-600/90 active:scale-95"
                                                        @click.stop.prevent="
                                                            respondToInvite(
                                                                notification,
                                                                'accept',
                                                            )
                                                        "
                                                    >
                                                        <Check
                                                            class="h-3 w-3"
                                                        />
                                                        <span>Accept</span>
                                                    </button>
                                                    <button
                                                        type="button"
                                                        class="inline-flex h-6 items-center gap-1 rounded-full border border-border/60 px-2.5 text-[10px] font-bold text-muted-foreground transition-colors hover:text-foreground active:scale-95"
                                                        @click.stop.prevent="
                                                            respondToInvite(
                                                                notification,
                                                                'decline',
                                                            )
                                                        "
                                                    >
                                                        <X class="h-3 w-3" />
                                                        <span>Decline</span>
                                                    </button>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                    <div
                                        v-else
                                        class="bg-background/80 px-4 py-6 text-center"
                                    >
                                        <p
                                            class="text-sm font-semibold text-foreground"
                                        >
                                            No notifications yet
                                        </p>
                                        <p
                                            class="mt-1 text-xs text-muted-foreground"
                                        >
                                            XP, level-up, and badge alerts will
                                            appear here.
                                        </p>
                                    </div>
                                </DropdownMenuContent>
                            </DropdownMenu>

                            <div
                                class="hidden flex-col items-end justify-center sm:flex"
                            >
                                <span
                                    class="font-mono text-[10px] font-bold tracking-tight text-foreground sm:text-xs"
                                    >{{ currentTime }}</span
                                >
                                <span
                                    class="font-sans text-[8px] font-semibold tracking-wider text-muted-foreground uppercase sm:text-[10px]"
                                    >{{ currentDate }}</span
                                >
                            </div>

                            <button
                                @click="toggleTheme"
                                class="inline-flex items-center justify-center rounded-md p-1.5 text-sm font-medium transition-colors hover:bg-neutral-100 sm:p-2 dark:hover:bg-neutral-800"
                                :aria-label="`Switch to ${appearance === 'dark' ? 'light' : 'dark'} mode`"
                            >
                                <Sun
                                    v-if="appearance === 'dark'"
                                    class="h-4 w-4 sm:h-5 sm:w-5"
                                />
                                <Moon v-else class="h-4 w-4 sm:h-5 sm:w-5" />
                            </button>
                        </div>

                        <div class="hidden space-x-1 lg:flex">
                            <template
                                v-for="item in rightNavItems"
                                :key="item.title"
                            >
                                <TooltipProvider :delay-duration="0">
                                    <Tooltip>
                                        <TooltipTrigger>
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                as-child
                                                class="group h-9 w-9 cursor-pointer"
                                            >
                                                <a
                                                    :href="toUrl(item.href)"
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                >
                                                    <span class="sr-only">{{
                                                        item.title
                                                    }}</span>
                                                    <component
                                                        :is="item.icon"
                                                        class="size-5 opacity-80 group-hover:opacity-100"
                                                    />
                                                </a>
                                            </Button>
                                        </TooltipTrigger>
                                        <TooltipContent>
                                            <p>{{ item.title }}</p>
                                        </TooltipContent>
                                    </Tooltip>
                                </TooltipProvider>
                            </template>
                        </div>
                    </div>

                    <DropdownMenu>
                        <DropdownMenuTrigger :as-child="true">
                            <Button
                                variant="ghost"
                                size="icon"
                                class="relative size-9 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar
                                    class="size-7 overflow-hidden rounded-full"
                                >
                                    <AvatarImage
                                        v-if="auth.user.avatar"
                                        :src="auth.user.avatar"
                                        :alt="auth.user.name"
                                    />
                                    <AvatarFallback
                                        class="rounded-lg bg-neutral-200 font-semibold text-black dark:bg-neutral-700 dark:text-white"
                                    >
                                        {{ getInitials(auth.user?.name) }}
                                    </AvatarFallback>
                                </Avatar>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-56">
                            <UserMenuContent
                                :user="auth.user"
                                @logout="showLogoutDialog = true"
                            />
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </div>
        </div>

        <div
            v-if="props.breadcrumbs.length > 1"
            class="flex w-full border-b border-sidebar-border/70"
        >
            <div
                class="mx-auto flex h-10 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>

    <LogoutConfirmationModal
        :open="showLogoutDialog"
        @close="showLogoutDialog = false"
    />
</template>
