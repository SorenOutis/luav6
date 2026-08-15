<script setup lang="ts">
import { ChevronRight, Flame } from 'lucide-vue-next';
import { ref } from 'vue';
import StreakCalendarModal from '@/components/dashboard/StreakCalendarModal.vue';
import { useNumberAnimation } from '@/composables/useNumberAnimation';

interface Props {
    currentStreak: number;
    longestStreak: number;
    loginDates?: string[];
}

const props = defineProps<Props>();

const showCalendar = ref(false);

const animStreak = useNumberAnimation(() => props.currentStreak || 0);

const openCalendar = () => {
    showCalendar.value = true;
};
</script>

<template>
    <div
        class="surface-card group relative flex h-full w-full min-w-0 cursor-pointer flex-col justify-between gap-2.5 p-3 transition-colors focus-visible:ring-2 focus-visible:ring-[#D97757]/40 focus-visible:outline-none active:bg-muted/30 sm:gap-4 sm:p-5"
        tabindex="0"
        role="button"
        aria-label="Open your streak calendar"
        @click="openCalendar"
        @keydown.enter.prevent="openCalendar"
        @keydown.space.prevent="openCalendar"
    >
        <div class="relative z-10 flex items-start justify-between gap-3">
            <div class="flex items-center gap-2.5">
                <div class="dash-icon-well bg-[#D97757]/15 text-[#D97757]">
                    <Flame class="h-5 w-5" />
                </div>
                <p class="dash-label">Streak</p>
            </div>
            <ChevronRight
                class="hidden h-5 w-5 shrink-0 text-muted-foreground/30 transition-all duration-300 group-hover:translate-x-1 group-hover:text-primary sm:block"
            />
        </div>

        <div class="relative z-10">
            <p
                class="dash-metric flex items-baseline gap-1.5 text-[24px] leading-none text-foreground sm:text-4xl"
            >
                {{ animStreak }}
                <span class="text-[13px] font-medium text-muted-foreground">{{
                    animStreak === 1 ? 'day' : 'days'
                }}</span>
            </p>
        </div>

        <div
            class="relative z-10 flex items-center justify-between border-t border-border/10 pt-3"
        >
            <p class="text-xs text-muted-foreground">
                Best:
                <span class="font-semibold text-foreground"
                    >{{ longestStreak }} days</span
                >
            </p>
            <span
                class="hidden items-center gap-1 text-[13px] font-medium text-[#D97757] sm:flex"
            >
                Calendar
                <ChevronRight class="h-3 w-3" />
            </span>
        </div>

        <StreakCalendarModal
            :open="showCalendar"
            :login-dates="props.loginDates ?? []"
            :current-streak="currentStreak"
            :longest-streak="longestStreak"
            @close="showCalendar = false"
        />
    </div>
</template>
