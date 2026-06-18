<script setup lang="ts">
import { router, usePage } from '@inertiajs/vue3';
import { Bell, Moon, Shield, Sun, TrendingUp, Zap } from 'lucide-vue-next';
import { ref, onMounted, onBeforeUnmount } from 'vue';
import AppearanceMenu from '@/components/AppearanceMenu.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { SidebarTrigger } from '@/components/ui/sidebar';
import { useAppearance } from '@/composables/useAppearance';
import type { BreadcrumbItem } from '@/types';

withDefaults(
    defineProps<{
        breadcrumbs?: BreadcrumbItem[];
    }>(),
    {
        breadcrumbs: () => [],
    },
);

const { appearance, toggleTheme } = useAppearance();
const page = usePage();

interface HeaderNotification {
    id: string;
    type: string;
    icon: string;
    title: string;
    message?: string | null;
    meta?: string | null;
    image?: string | null;
    href?: string | null;
    readAt?: string | null;
    createdAt?: string | null;
}

const currentTime = ref('');
const currentDate = ref('');
let timer: ReturnType<typeof setInterval>;

const notifications = () =>
    (page.props.notifications as
        | {
              unreadCount: number;
              items: HeaderNotification[];
          }
        | undefined) ?? {
        unreadCount: 0,
        items: [],
    };

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
</script>

<template>
    <header
        class="flex h-16 shrink-0 items-center justify-between gap-2 border-b border-sidebar-border/70 px-6 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12 md:px-4"
    >
        <div class="flex items-center gap-2">
            <SidebarTrigger class="-ml-1" />
            <template v-if="breadcrumbs && breadcrumbs.length > 0">
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </template>
        </div>

        <div class="flex items-center gap-2 sm:gap-4">
            <DropdownMenu>
                <DropdownMenuTrigger :as-child="true">
                    <button
                        class="relative inline-flex items-center justify-center rounded-md p-1.5 text-sm font-medium transition-colors hover:bg-neutral-100 sm:p-2 dark:hover:bg-neutral-800"
                        aria-label="Open notifications"
                    >
                        <Bell class="h-4 w-4 sm:h-5 sm:w-5" />
                        <span
                            v-if="notifications().unreadCount > 0"
                            class="absolute -top-0.5 -right-0.5 min-w-4 rounded-full bg-primary px-1 text-[9px] leading-4 font-black text-primary-foreground"
                        >
                            {{
                                notifications().unreadCount > 9
                                    ? '9+'
                                    : notifications().unreadCount
                            }}
                        </span>
                    </button>
                </DropdownMenuTrigger>
                <DropdownMenuContent
                    align="end"
                    class="w-80 border-border/80 bg-background/95 p-0 shadow-2xl backdrop-blur-xl supports-[backdrop-filter]:bg-background/90"
                >
                    <div
                        class="flex items-center justify-between border-b border-border/60 px-3 py-2"
                    >
                        <div>
                            <p class="text-sm font-bold">Notifications</p>
                            <p
                                class="text-[10px] tracking-widest text-muted-foreground uppercase"
                            >
                                {{ notifications().unreadCount }} unread
                            </p>
                        </div>
                        <button
                            v-if="notifications().unreadCount > 0"
                            class="text-[10px] font-semibold text-primary transition-colors hover:text-primary/80"
                            @click="markAllNotificationsAsRead"
                        >
                            Mark all read
                        </button>
                    </div>

                    <div
                        v-if="notifications().items.length > 0"
                        class="max-h-96 overflow-y-auto p-2"
                    >
                        <button
                            v-for="notification in notifications().items"
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
                                    :is="notificationIcon(notification.icon)"
                                    class="h-4 w-4 text-primary"
                                />
                            </div>
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center gap-2">
                                    <p
                                        class="truncate text-xs font-bold text-foreground"
                                    >
                                        {{ notification.title }}
                                    </p>
                                    <span
                                        v-if="!notification.readAt"
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
                                        {{ notification.createdAt }}
                                    </span>
                                </div>
                            </div>
                        </button>
                    </div>
                    <div v-else class="bg-background/80 px-4 py-6 text-center">
                        <p class="text-sm font-semibold text-foreground">
                            No notifications yet
                        </p>
                        <p class="mt-1 text-xs text-muted-foreground">
                            XP, level-up, and badge alerts will appear here.
                        </p>
                    </div>
                </DropdownMenuContent>
            </DropdownMenu>

            <div class="flex flex-col items-end justify-center leading-none">
                <span
                    class="text-[11px] font-black tracking-tighter text-foreground uppercase sm:text-xs"
                    >{{ currentTime }}</span
                >
                <span
                    class="mt-0.5 text-[8px] font-bold tracking-widest text-muted-foreground/80 uppercase sm:text-[9px]"
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

            <AppearanceMenu />
        </div>
    </header>
</template>
