<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ArrowUp } from 'lucide-vue-next';
import { isLowEndDeviceSignal } from '@/lib/device';

const year = new Date().getFullYear();
const isLowEndDevice = isLowEndDeviceSignal();

const platformLinks = [
    { label: 'How it works', href: '/#how-it-works' },
    { label: 'Features', href: '/#features' },
    { label: 'Pricing', href: '/#pricing' },
];

const scrollTop = () => {
    window.scrollTo({ top: 0, behavior: 'smooth' });
};
</script>

<template>
    <footer
        class="welcome-footer border-t border-[#17201f]/10 bg-[#f8f7f2] dark:border-border/70 dark:bg-background"
        :data-low-end="isLowEndDevice ? 'true' : undefined"
    >
        <div
            class="mx-auto max-w-[1500px] px-4 py-12 sm:px-6 sm:py-16 lg:px-16"
        >
            <div
                class="grid gap-10 md:grid-cols-[1.2fr_1fr] md:items-start lg:grid-cols-[1.5fr_1fr_1fr]"
            >
                <div class="max-w-sm">
                    <Link
                        href="/"
                        class="font-serif text-2xl tracking-[-0.03em] text-foreground"
                    >
                        LSI — KOAMISHIN
                    </Link>
                    <p
                        class="mt-4 text-sm leading-relaxed text-muted-foreground"
                    >
                        Tools that help teachers see what learners need and
                        decide what to teach next.
                    </p>
                </div>

                <nav aria-label="Platform" class="flex flex-col gap-3">
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        Platform
                    </p>
                    <a
                        v-for="link in platformLinks"
                        :key="link.label"
                        :href="link.href"
                        class="w-fit text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        {{ link.label }}
                    </a>
                </nav>

                <div class="flex flex-col gap-3">
                    <p
                        class="text-xs font-semibold tracking-[0.16em] text-muted-foreground uppercase"
                    >
                        Company
                    </p>
                    <Link
                        href="/about"
                        class="w-fit text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        About LSI
                    </Link>
                    <a
                        href="mailto:hello@koamishin.dev"
                        class="w-fit text-sm text-muted-foreground transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                    >
                        hello@koamishin.dev
                    </a>
                </div>
            </div>

            <div
                class="mt-12 flex flex-col gap-4 border-t border-border/70 pt-6 text-xs text-muted-foreground sm:flex-row sm:items-center sm:justify-between"
            >
                <p>© {{ year }} Koamishin. All rights reserved.</p>
                <button
                    type="button"
                    @click="scrollTop"
                    class="inline-flex w-fit items-center gap-2 transition-colors hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                >
                    Back to top
                    <ArrowUp class="h-3.5 w-3.5" aria-hidden="true" />
                </button>
            </div>
        </div>
    </footer>
</template>
