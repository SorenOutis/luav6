<script setup lang="ts">
import { CalendarDays, Flag, Sparkles } from 'lucide-vue-next';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';

interface Props {
    name?: string | null;
    startDate?: string | null;
    endDate?: string | null;
    xpEarned?: number;
    xpTarget?: number | null;
}

const props = withDefaults(defineProps<Props>(), {
    name: null,
    startDate: null,
    endDate: null,
    xpEarned: 0,
    xpTarget: null,
});

const now = ref(new Date());
let tickId: number | null = null;

onMounted(() => {
    tickId = window.setInterval(() => {
        now.value = new Date();
    }, 60_000);
});

onBeforeUnmount(() => {
    if (tickId !== null) window.clearInterval(tickId);
});

const seasonTimeline = computed(() => {
    if (!props.startDate || !props.endDate) return null;
    const start = new Date(props.startDate).getTime();
    const end = new Date(props.endDate).getTime();
    const current = now.value.getTime();
    const totalMs = Math.max(1, end - start);
    const elapsedMs = Math.min(totalMs, Math.max(0, current - start));
    const percentElapsed = Math.round((elapsedMs / totalMs) * 100);
    const daysRemaining = Math.max(0, Math.ceil((end - current) / 86_400_000));
    return { percentElapsed, daysRemaining, start, end };
});

const xpPercent = computed(() => {
    if (!props.xpTarget || props.xpTarget <= 0) return null;
    return Math.min(100, Math.round((props.xpEarned / props.xpTarget) * 100));
});

const pacing = computed(() => {
    const t = seasonTimeline.value;
    const xp = xpPercent.value;
    if (!t || xp === null) return null;
    const delta = xp - t.percentElapsed;
    if (delta > 5) return { label: 'Ahead of pace', tone: 'text-primary' };
    if (delta < -5) return { label: 'Behind pace', tone: 'text-destructive' };
    return { label: 'On pace', tone: 'text-muted-foreground' };
});

const formatDate = (iso?: string | null) => {
    if (!iso) return '';
    return new Date(iso).toLocaleDateString(undefined, {
        month: 'short',
        day: 'numeric',
    });
};
</script>

<template>
    <section
        v-if="name && seasonTimeline"
        class="surface-card relative flex h-full w-full min-w-0 flex-col justify-between gap-4 overflow-hidden p-5 sm:p-6"
        aria-label="Current season progress"
    >
        <div
            class="pointer-events-none absolute -top-10 -right-10 h-32 w-32 rounded-full bg-primary/10 blur-3xl"
            aria-hidden="true"
        />

        <div class="relative z-10 flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <div
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 text-primary"
                >
                    <Sparkles class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p
                        class="text-[10px] font-black tracking-[0.2em] text-muted-foreground/70 uppercase"
                    >
                        Season
                    </p>
                    <h3 class="truncate text-base font-bold text-foreground">
                        {{ name }}
                    </h3>
                </div>
            </div>
        </div>

        <div class="relative z-10 flex items-end justify-between gap-3">
            <div>
                <p
                    class="text-[10px] font-black tracking-[0.2em] text-muted-foreground/60 uppercase"
                >
                    Time left
                </p>
                <p
                    class="mt-0.5 flex items-baseline gap-1 text-3xl leading-none font-black tracking-tighter tabular-nums"
                >
                    {{ seasonTimeline.daysRemaining }}
                    <span
                        class="text-[10px] font-bold text-muted-foreground/60 uppercase"
                        >days</span
                    >
                </p>
            </div>
            <span
                v-if="pacing"
                class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[10px] font-black tracking-widest uppercase"
                :class="[
                    pacing.tone,
                    pacing.tone === 'text-primary'
                        ? 'border-primary/25 bg-primary/10'
                        : pacing.tone === 'text-destructive'
                          ? 'border-destructive/25 bg-destructive/10'
                          : 'border-border/30 bg-muted/30',
                ]"
            >
                <Flag class="h-3 w-3" />
                {{ pacing.label }}
            </span>
        </div>

        <div class="relative z-10 space-y-1.5">
            <div
                class="flex items-center justify-between text-[10px] font-bold text-muted-foreground tabular-nums"
            >
                <span class="inline-flex items-center gap-1">
                    <CalendarDays class="h-3 w-3" />
                    {{ formatDate(startDate) }} – {{ formatDate(endDate) }}
                </span>
                <span v-if="xpPercent !== null" class="text-primary"
                    >XP {{ xpPercent }}%</span
                >
            </div>
            <div class="relative h-2 overflow-hidden rounded-full bg-muted/40">
                <!-- Elapsed time -->
                <div
                    class="absolute inset-y-0 left-0 bg-muted-foreground/25 transition-[width] duration-1000 ease-out"
                    :style="{ width: `${seasonTimeline.percentElapsed}%` }"
                    aria-hidden="true"
                />
                <!-- XP progress -->
                <div
                    v-if="xpPercent !== null"
                    class="absolute inset-y-0 left-0 bg-gradient-to-r from-primary/80 to-primary transition-[width] duration-1000 ease-out"
                    :style="{ width: `${xpPercent}%` }"
                />
                <!-- Pace marker -->
                <div
                    class="absolute top-[-3px] bottom-[-3px] w-0.5 rounded-full bg-foreground/60 transition-[left] duration-1000 ease-out"
                    :style="{ left: `${seasonTimeline.percentElapsed}%` }"
                    aria-hidden="true"
                />
            </div>
        </div>
    </section>
</template>
