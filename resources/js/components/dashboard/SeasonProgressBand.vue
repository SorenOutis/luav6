<script setup lang="ts">
import { CalendarDays, Sparkles } from 'lucide-vue-next';
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';

interface Props {
    name?: string | null;
    startDate?: string | null;
    endDate?: string | null;
}

const props = withDefaults(defineProps<Props>(), {
    name: null,
    startDate: null,
    endDate: null,
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

// Keep only the countdown: how many days remain until the season ends.
const daysRemaining = computed(() => {
    if (!props.endDate) return null;
    const end = new Date(props.endDate).getTime();
    return Math.max(0, Math.ceil((end - now.value.getTime()) / 86_400_000));
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
        v-if="name && daysRemaining !== null"
        class="surface-card relative flex h-full w-full min-w-0 flex-col justify-between gap-3 overflow-hidden p-3.5 sm:gap-4 sm:p-6"
        aria-label="Current season progress"
    >
        <div class="relative z-10 flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <div class="dash-icon-well bg-[#D97757]/15 text-[#D97757]">
                    <Sparkles class="h-5 w-5" />
                </div>
                <div class="min-w-0">
                    <p class="dash-label">Season</p>
                    <h3
                        class="truncate text-[17px] font-semibold tracking-tight text-foreground"
                    >
                        {{ name }}
                    </h3>
                </div>
            </div>
        </div>

        <div class="relative z-10">
            <p
                class="dash-metric flex items-baseline gap-1.5 text-[28px] leading-none text-foreground sm:text-[34px]"
            >
                {{ daysRemaining }}
                <span class="text-[13px] font-medium text-muted-foreground"
                    >days left</span
                >
            </p>
        </div>

        <div
            class="relative z-10 flex items-center gap-1.5 border-t border-border/10 pt-3 text-xs text-muted-foreground tabular-nums"
        >
            <CalendarDays class="h-3 w-3 shrink-0" />
            <span>{{ formatDate(startDate) }} – {{ formatDate(endDate) }}</span>
        </div>
    </section>
</template>
