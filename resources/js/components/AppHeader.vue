<script setup lang="ts">
import { Link, usePage } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Menu, Search, Moon, Sun } from 'lucide-vue-next';
import { computed, ref, onMounted, onBeforeUnmount } from 'vue';
import AppLogo from '@/components/AppLogo.vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import Breadcrumbs from '@/components/Breadcrumbs.vue';
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
import { useCurrentUrl } from '@/composables/useCurrentUrl';
import { getInitials } from '@/composables/useInitials';
import { toUrl } from '@/lib/utils';
import { dashboard } from '@/routes';
import { useAppearance } from '@/composables/useAppearance';
import type { BreadcrumbItem, NavItem } from '@/types';

type Props = {
    breadcrumbs?: BreadcrumbItem[];
};

const props = withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});

const page = usePage();
const auth = computed(() => page.props.auth);
const { isCurrentUrl, whenCurrentUrl } = useCurrentUrl();
const { appearance, updateAppearance } = useAppearance();

const toggleTheme = (event: MouseEvent) => {
    const newTheme = appearance.value === 'dark' ? 'light' : 'dark';
    
    if (!document.startViewTransition) {
        updateAppearance(newTheme);
        return;
    }

    const x = event.clientX;
    const y = event.clientY;
    const endRadius = Math.hypot(
        Math.max(x, innerWidth - x),
        Math.max(y, innerHeight - y)
    );

    const transition = document.startViewTransition(() => {
        updateAppearance(newTheme);
    });

    transition.ready.then(() => {
        const clipPath = [
            `circle(0px at ${x}px ${y}px)`,
            `circle(${endRadius}px at ${x}px ${y}px)`,
        ];
        
        document.documentElement.animate(
            {
                clipPath: newTheme === 'dark' ? [...clipPath].reverse() : clipPath,
            },
            {
                duration: 500,
                easing: 'ease-in-out',
                pseudoElement: newTheme === 'dark'
                    ? '::view-transition-old(root)'
                    : '::view-transition-new(root)',
            }
        );
    });
};

const currentTime = ref('');
const currentDate = ref('');
let timer: ReturnType<typeof setInterval>;

const updateTime = () => {
    const now = new Date();
    currentTime.value = now.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true });
    currentDate.value = now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: 'numeric', year: 'numeric' });
};

onMounted(() => {
    updateTime();
    timer = setInterval(updateTime, 1000);
});

onBeforeUnmount(() => {
    clearInterval(timer);
});

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
            <div class="mx-auto flex h-16 items-center px-4 md:max-w-7xl">
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

                        <div class="flex items-center gap-2 sm:gap-4 ml-2">
                            <div class="flex flex-col items-end justify-center">
                                <span class="text-[10px] sm:text-xs font-bold text-foreground font-mono tracking-tight">{{ currentTime }}</span>
                                <span class="text-[8px] sm:text-[10px] font-semibold text-muted-foreground uppercase tracking-wider font-sans">{{ currentDate }}</span>
                            </div>
                            
                            <button
                                @click="toggleTheme"
                                class="inline-flex items-center justify-center rounded-md p-1.5 sm:p-2 text-sm font-medium transition-colors hover:bg-neutral-100 dark:hover:bg-neutral-800"
                                :aria-label="`Switch to ${appearance === 'dark' ? 'light' : 'dark'} mode`"
                            >
                                <Sun v-if="appearance === 'dark'" class="h-4 w-4 sm:h-5 sm:w-5" />
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
                                class="relative size-10 w-auto rounded-full p-1 focus-within:ring-2 focus-within:ring-primary"
                            >
                                <Avatar
                                    class="size-8 overflow-hidden rounded-full"
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
                            <UserMenuContent :user="auth.user" />
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
                class="mx-auto flex h-12 w-full items-center justify-start px-4 text-neutral-500 md:max-w-7xl"
            >
                <Breadcrumbs :breadcrumbs="breadcrumbs" />
            </div>
        </div>
    </div>
</template>
