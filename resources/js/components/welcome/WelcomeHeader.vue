<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Motion } from '@motionone/vue';
import { Command, Sun, Moon, Menu, X } from 'lucide-vue-next';
import { ref, computed, watch, onBeforeUnmount } from 'vue';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetTrigger,
} from '@/components/ui/sheet';
import { useAppearance } from '@/composables/useAppearance';
import { useMobile } from '@/composables/useMobile';

const props = defineProps<{
    canRegister: boolean;
    auth: { user: any };
    dashboard: () => string;
    login: () => string;
    register: () => string;
    isBooted?: boolean;
    branding?: {
        name?: string;
        tagline?: string;
        logoUrl?: string | null;
        accentColor?: string;
    };
    /**
     * When true, only show 'Home' in the scroll nav — hides
     * section-specific items like 'How It Works' and 'Features'.
     * Use on dedicated pages (About, HowItWorks) that aren't
     * scrollable landing sections.
     */
    hideScrollNav?: boolean;
}>();

const { appearance, toggleTheme } = useAppearance();
const { prefersReducedMotion, isLowEndDevice } = useMobile();
const liteMotion = computed(
    () => prefersReducedMotion.value || isLowEndDevice.value,
);
const brandName = computed(() => props.branding?.name || 'LSI Engine');
const brandLogoUrl = computed(() => props.branding?.logoUrl || null);

const scrollToSection = (e: MouseEvent, targetId: string) => {
    e.preventDefault();
    if (targetId === 'top') {
        if (window.location.pathname !== '/') {
            // Not on the home page — navigate there
            window.location.href = '/';
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' });
            history.pushState(null, '', window.location.pathname);
        }
        return;
    }
    const el = document.getElementById(targetId);
    if (el) {
        el.scrollIntoView({ behavior: 'smooth', block: 'start' });
        history.pushState(null, '', `#${targetId}`);
    } else {
        // Section doesn't exist on this page — navigate to home page with anchor
        window.location.href = `/#${targetId}`;
    }
};

const navItems = computed(() => {
    const allItems = [
        {
            label: 'Home',
            target: 'top',
            preview: 'Back to the top of the page',
        },
        {
            label: 'How It Works',
            target: 'architecture',
            preview: 'Learn how LSI works from start to finish',
        },
        {
            label: 'Features',
            target: 'features',
            preview: 'Explore key capabilities and tools',
        },
        {
            label: 'Pricing',
            target: 'pricing',
            preview: 'View plans and pricing options',
        },
    ];
    if (props.hideScrollNav) {
        return allItems.filter((item) => item.target === 'top');
    }
    return allItems;
});

const isMobileMenuOpen = ref(false);

watch(isMobileMenuOpen, (open) => {
    document.body.style.overflow = open ? 'hidden' : '';
});

// Ensure body scroll is restored if component unmounts while menu is open
onBeforeUnmount(() => {
    document.body.style.overflow = '';
});

const closeMobileMenu = () => {
    isMobileMenuOpen.value = false;
};

const handleNavClick = (e: MouseEvent, targetId: string) => {
    closeMobileMenu();
    scrollToSection(e, targetId);
};
</script>

<template>
    <header
        class="sticky top-0 z-50 flex w-full items-center justify-between border-b border-border/10 bg-background/95 px-4 py-3 sm:px-6 sm:py-4 md:bg-background/60 md:backdrop-blur-xl lg:px-16 lg:py-5 dark:border-border/5 dark:bg-background/95 md:dark:bg-background/30"
    >
        <Motion
            :initial="liteMotion ? false : { x: -20, opacity: 0 }"
            :animate="isBooted ? { x: 0, opacity: 1 } : { x: -20, opacity: 0 }"
            :transition="
                liteMotion
                    ? { duration: 0 }
                    : {
                          duration: 0.8,
                          easing: [0.16, 1, 0.3, 1],
                          delay: 0.2,
                      }
            "
            class="flex items-center gap-3"
        >
            <Link href="/" class="flex items-center gap-3">
                <div
                    class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-lg bg-foreground/5 text-foreground transition-all duration-500 hover:bg-primary/10 lg:h-10 lg:w-10"
                >
                    <img
                        v-if="brandLogoUrl"
                        :src="brandLogoUrl"
                        :alt="`${brandName} logo`"
                        class="h-full w-full rounded-lg object-cover"
                    />
                    <Command v-else class="h-5 w-5 lg:h-6 lg:w-6" />
                </div>
                <span class="text-sm font-semibold text-foreground">{{
                    brandName
                }}</span>
            </Link>
        </Motion>

        <Motion
            :initial="liteMotion ? false : { y: -20, opacity: 0 }"
            :animate="isBooted ? { y: 0, opacity: 1 } : { y: -20, opacity: 0 }"
            :transition="
                liteMotion
                    ? { duration: 0 }
                    : {
                          duration: 0.8,
                          easing: [0.16, 1, 0.3, 1],
                          delay: 0.3,
                      }
            "
            class="absolute left-1/2 hidden -translate-x-1/2 items-center gap-8 lg:flex"
        >
            <a
                v-for="item in navItems"
                :key="item.label"
                :href="`#${item.target}`"
                @click="(e) => scrollToSection(e, item.target)"
                class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                {{ item.label }}
            </a>
            <Link
                href="/about"
                class="text-sm font-medium text-muted-foreground transition-colors hover:text-foreground"
            >
                About
            </Link>
        </Motion>

        <Motion
            :initial="liteMotion ? false : { y: -10, opacity: 0 }"
            :animate="isBooted ? { y: 0, opacity: 1 } : { y: -10, opacity: 0 }"
            :transition="
                liteMotion
                    ? { duration: 0 }
                    : {
                          duration: 0.8,
                          easing: [0.16, 1, 0.3, 1],
                          delay: 0.4,
                      }
            "
            as="nav"
            class="flex items-center gap-4 lg:gap-6"
        >
            <button
                @click="toggleTheme"
                class="relative rounded-lg p-2 text-muted-foreground transition-all hover:bg-muted/40 hover:text-foreground"
                aria-label="Toggle Theme"
            >
                <Sun v-if="appearance === 'dark'" class="h-4 w-4" />
                <Moon v-else class="h-4 w-4" />
            </button>

            <Sheet v-model:open="isMobileMenuOpen">
                <!-- Mobile menu trigger -->
                <SheetTrigger as-child>
                    <button
                        class="rounded-lg p-2 text-muted-foreground transition-all hover:bg-muted/40 hover:text-foreground lg:hidden"
                        aria-label="Open Menu"
                    >
                        <Menu class="h-5 w-5" />
                    </button>
                </SheetTrigger>

                <!-- Mobile menu panel -->
                <SheetContent side="right" class="w-[280px] p-0">
                    <div class="flex h-full flex-col">
                        <!-- Header with close -->
                        <div
                            class="flex items-center justify-between border-b border-border/10 px-5 py-4"
                        >
                            <Link
                                href="/"
                                class="flex items-center gap-2.5"
                                @click="closeMobileMenu"
                            >
                                <div
                                    class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-lg bg-foreground/5"
                                >
                                    <img
                                        v-if="brandLogoUrl"
                                        :src="brandLogoUrl"
                                        :alt="`${brandName} logo`"
                                        class="h-full w-full rounded-lg object-cover"
                                    />
                                    <Command v-else class="h-4 w-4" />
                                </div>
                                <span class="text-sm font-semibold">{{
                                    brandName
                                }}</span>
                            </Link>
                            <SheetClose as-child>
                                <button
                                    class="rounded-lg p-1.5 text-muted-foreground transition-colors hover:bg-muted/40 hover:text-foreground"
                                    aria-label="Close Menu"
                                >
                                    <X class="h-4 w-4" />
                                </button>
                            </SheetClose>
                        </div>

                        <!-- Nav links -->
                        <div class="flex-1 space-y-1 px-3 py-5">
                            <a
                                v-for="item in navItems"
                                :key="item.label"
                                :href="`#${item.target}`"
                                @click="(e) => handleNavClick(e, item.target)"
                                class="group flex flex-col rounded-lg px-3 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted/40 hover:text-foreground"
                            >
                                <span>{{ item.label }}</span>
                                <span
                                    class="mt-0.5 text-xs text-muted-foreground/50 transition-colors group-hover:text-muted-foreground/70"
                                    >{{ item.preview }}</span
                                >
                            </a>
                            <Link
                                href="/about"
                                @click="closeMobileMenu"
                                class="group flex flex-col rounded-lg px-3 py-2.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-muted/40 hover:text-foreground"
                            >
                                <span>About</span>
                                <span
                                    class="mt-0.5 text-xs text-muted-foreground/50 transition-colors group-hover:text-muted-foreground/70"
                                    >Learn about the LSI platform and team</span
                                >
                            </Link>
                        </div>

                        <!-- Auth footer -->
                        <div class="border-t border-border/10 px-3 py-4">
                            <template v-if="auth.user">
                                <Link
                                    :href="dashboard()"
                                    @click="closeMobileMenu"
                                    class="flex items-center justify-center rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background transition-colors hover:bg-primary"
                                >
                                    Dashboard
                                </Link>
                            </template>
                            <template v-else>
                                <div class="flex flex-col gap-2">
                                    <Link
                                        :href="login()"
                                        @click="closeMobileMenu"
                                        class="flex items-center justify-center rounded-lg border border-border/30 px-4 py-2.5 text-sm font-medium text-foreground transition-colors hover:border-primary/60"
                                    >
                                        Login
                                    </Link>
                                    <Link
                                        v-if="canRegister"
                                        :href="register()"
                                        @click="closeMobileMenu"
                                        class="flex items-center justify-center rounded-lg bg-foreground px-4 py-2.5 text-sm font-semibold text-background transition-colors hover:bg-primary"
                                    >
                                        Start for free
                                    </Link>
                                </div>
                            </template>
                        </div>
                    </div>
                </SheetContent>
            </Sheet>

            <template v-if="auth.user">
                <Link
                    :href="dashboard()"
                    class="hidden items-center gap-2 rounded-lg bg-foreground/5 px-4 py-2 text-sm font-medium text-foreground transition-colors hover:bg-primary/10 hover:text-primary lg:inline-flex"
                >
                    Dashboard
                </Link>
            </template>
            <template v-else>
                <Link
                    :href="login()"
                    class="hidden text-sm font-medium text-muted-foreground transition-colors hover:text-foreground lg:inline-flex"
                >
                    Login
                </Link>
                <Link
                    v-if="canRegister"
                    :href="register()"
                    class="hidden rounded-lg bg-foreground px-4 py-2 text-sm font-medium text-background transition-colors hover:bg-primary lg:inline-flex"
                >
                    Start for free
                </Link>
            </template>
        </Motion>
    </header>
</template>

<style scoped>
/* Hide the built-in Sheet close button — we use our own in the menu header */
:deep([data-slot='sheet-content'] [data-dialog-close]) {
    display: none;
}
</style>
