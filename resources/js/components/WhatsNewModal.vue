<script setup lang="ts">
import {
    Award,
    ChevronDown,
    Flame,
    GraduationCap,
    Mic,
    Music,
    Newspaper,
    Rocket,
    ShoppingBag,
    Smartphone,
    Sparkles,
    Zap,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Button } from '@/components/ui/button';
import { useWhatsNew } from '@/composables/useWhatsNew';
import type { ChangelogFeature, ChangelogRelease } from '@/data/changelog';

const { isOpen, close, currentVersion, releases } = useWhatsNew();

const activeTab = ref<'latest' | 'all'>('latest');
const expandedReleases = ref<Set<string>>(new Set());

const currentRelease = computed<ChangelogRelease | undefined>(() => {
    return releases.find((r) => r.isCurrent) ?? releases[0];
});

const iconMap: Record<string, unknown> = {
    Smartphone,
    ShoppingBag,
    Mic,
    Zap,
    GraduationCap,
    Flame,
    Music,
    Rocket,
    Award,
    Sparkles,
};

const resolveIcon = (name?: string) => {
    if (!name) return Sparkles;
    return iconMap[name] ?? Sparkles;
};

const tagClass = (tag: ChangelogFeature['tag']) => {
    switch (tag) {
        case 'New':
            return 'border-emerald-500/20 bg-emerald-500/10 text-emerald-600 dark:text-emerald-400';
        case 'Improved':
            return 'border-amber-500/20 bg-amber-500/10 text-amber-600 dark:text-amber-400';
        case 'Fix':
            return 'border-sky-500/20 bg-sky-500/10 text-sky-600 dark:text-sky-400';
        default:
            return 'border-border bg-muted text-muted-foreground';
    }
};

const toggleReleaseExpand = (version: string) => {
    if (expandedReleases.value.has(version)) {
        expandedReleases.value.delete(version);
    } else {
        expandedReleases.value.add(version);
    }
};
</script>

<template>
    <ResponsiveModal
        :open="isOpen"
        content-class="max-w-xl sm:rounded-2xl p-0 overflow-hidden"
        @close="close"
    >
        <!-- Modal Header -->
        <div class="border-b border-border/70 bg-card/60 p-4 sm:p-6">
            <div class="flex items-center justify-between gap-3">
                <div class="flex items-center gap-2.5">
                    <div
                        class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-amber-500/10 text-amber-500 ring-1 ring-amber-500/20 dark:bg-amber-400/10 dark:text-amber-400"
                    >
                        <Newspaper class="h-4.5 w-4.5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="flex flex-wrap items-center gap-2">
                            <h2
                                class="truncate text-base font-bold tracking-tight text-foreground sm:text-lg"
                            >
                                What's New in LSI
                            </h2>
                            <span
                                class="inline-flex items-center rounded-full border border-primary/20 bg-primary/10 px-2 py-0.5 font-mono text-[10px] font-semibold text-primary sm:text-[11px]"
                            >
                                {{
                                    currentRelease?.version ||
                                    `v${currentVersion}`
                                }}
                            </span>
                        </div>
                        <p class="text-[11px] text-muted-foreground sm:text-xs">
                            Latest student features, classroom tools, and study
                            updates.
                        </p>
                    </div>
                </div>
            </div>

            <!-- Tabs: Latest vs All History -->
            <div
                class="mt-3.5 flex rounded-lg bg-muted/60 p-0.5 text-xs sm:mt-4"
            >
                <button
                    type="button"
                    class="flex-1 rounded-md py-1.5 font-medium transition-all"
                    :class="
                        activeTab === 'latest'
                            ? 'bg-background text-foreground shadow-2xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'latest'"
                >
                    Latest Features
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-md py-1.5 font-medium transition-all"
                    :class="
                        activeTab === 'all'
                            ? 'bg-background text-foreground shadow-2xs'
                            : 'text-muted-foreground hover:text-foreground'
                    "
                    @click="activeTab = 'all'"
                >
                    Release History ({{ releases.length }})
                </button>
            </div>
        </div>

        <!-- Scrollable Content -->
        <div class="max-h-[62vh] overflow-y-auto p-4 sm:p-6" data-lenis-prevent>
            <!-- TAB 1: Latest Features -->
            <div v-if="activeTab === 'latest'" class="space-y-3.5 sm:space-y-4">
                <div
                    v-if="currentRelease"
                    class="rounded-xl border border-border/80 bg-card p-3 sm:p-3.5"
                >
                    <div class="flex items-center justify-between gap-2">
                        <span class="text-xs font-semibold text-foreground">
                            {{ currentRelease.title }}
                        </span>
                        <span
                            class="text-[11px] font-medium text-muted-foreground"
                        >
                            {{ currentRelease.date }}
                        </span>
                    </div>
                    <p
                        class="mt-1 text-xs leading-relaxed text-muted-foreground"
                    >
                        {{ currentRelease.summary }}
                    </p>
                </div>

                <div class="space-y-3">
                    <div
                        v-for="feature in currentRelease?.features ?? []"
                        :key="feature.title"
                        class="group flex items-start gap-3 rounded-xl border border-border/60 bg-card/40 p-3.5 transition-colors hover:border-border hover:bg-card"
                    >
                        <div
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-muted text-muted-foreground transition-colors group-hover:bg-primary/10 group-hover:text-primary"
                        >
                            <component
                                :is="resolveIcon(feature.icon)"
                                class="h-4 w-4"
                            />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <h4
                                    class="text-xs font-semibold text-foreground"
                                >
                                    {{ feature.title }}
                                </h4>
                                <span
                                    class="rounded-full border px-1.5 py-0.5 font-mono text-[9px] font-semibold tracking-wider uppercase"
                                    :class="tagClass(feature.tag)"
                                >
                                    {{ feature.tag }}
                                </span>
                            </div>
                            <p
                                class="mt-1 text-[11px] leading-relaxed text-muted-foreground"
                            >
                                {{ feature.description }}
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TAB 2: All Releases -->
            <div v-else class="space-y-4">
                <div
                    v-for="release in releases"
                    :key="release.version"
                    class="rounded-xl border border-border/80 bg-card transition-all"
                >
                    <button
                        type="button"
                        class="flex w-full items-center justify-between p-3.5 text-left"
                        @click="toggleReleaseExpand(release.version)"
                    >
                        <div>
                            <div class="flex items-center gap-2">
                                <span
                                    class="font-mono text-xs font-bold text-foreground"
                                >
                                    {{ release.version }}
                                </span>
                                <span
                                    v-if="release.isCurrent"
                                    class="rounded-full bg-emerald-500/10 px-1.5 py-0.5 text-[10px] font-semibold text-emerald-600 dark:text-emerald-400"
                                >
                                    Current
                                </span>
                                <span
                                    class="text-xs font-medium text-foreground"
                                >
                                    {{ release.title }}
                                </span>
                            </div>
                            <p class="mt-0.5 text-[11px] text-muted-foreground">
                                {{ release.date }} ·
                                {{ release.features.length }} updates
                            </p>
                        </div>
                        <ChevronDown
                            class="h-4 w-4 text-muted-foreground transition-transform"
                            :class="
                                expandedReleases.has(release.version) ||
                                release.isCurrent
                                    ? 'rotate-180'
                                    : ''
                            "
                        />
                    </button>

                    <div
                        v-show="
                            expandedReleases.has(release.version) ||
                            release.isCurrent
                        "
                        class="border-t border-border/60 px-3.5 pt-2 pb-3.5"
                    >
                        <p class="mb-3 text-[11px] text-muted-foreground">
                            {{ release.summary }}
                        </p>
                        <div class="space-y-2">
                            <div
                                v-for="feature in release.features"
                                :key="feature.title"
                                class="flex items-start gap-2.5 rounded-lg bg-muted/40 p-2 text-xs"
                            >
                                <span
                                    class="shrink-0 rounded-full border px-1.5 py-0.5 font-mono text-[9px] font-semibold uppercase"
                                    :class="tagClass(feature.tag)"
                                >
                                    {{ feature.tag }}
                                </span>
                                <div class="min-w-0 flex-1">
                                    <p
                                        class="text-[11px] font-medium text-foreground"
                                    >
                                        {{ feature.title }}
                                    </p>
                                    <p
                                        class="mt-0.5 text-[10px] text-muted-foreground"
                                    >
                                        {{ feature.description }}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal Footer -->
        <div
            class="flex items-center justify-between border-t border-border/70 bg-card/60 px-4 py-3 sm:px-6 sm:py-3.5"
        >
            <span
                class="font-mono text-[10px] text-muted-foreground sm:text-[11px]"
            >
                LSI Platform
                {{ currentRelease?.version || `v${currentVersion}` }}
            </span>
            <Button
                size="sm"
                variant="default"
                class="h-8 px-4 text-xs"
                @click="close"
            >
                Got it
            </Button>
        </div>
    </ResponsiveModal>
</template>
