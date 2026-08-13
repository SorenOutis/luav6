<script setup lang="ts">
import { ChevronRight, Flame } from 'lucide-vue-next';
import { computed, ref } from 'vue';
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

// Last 7 days mini preview
function localDateStr(d: Date): string {
    const y = d.getFullYear();
    const m = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${y}-${m}-${day}`;
}

const last7Days = computed(() => {
    const now = new Date();
    const today = localDateStr(now);
    const loginSet = new Set(props.loginDates ?? []);
    const dayNames = ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa'];
    return Array.from({ length: 7 }, (_, i) => {
        const d = new Date(now);
        d.setDate(d.getDate() - (6 - i));
        const dateStr = localDateStr(d);
        return {
            label: dayNames[d.getDay()],
            isActive: loginSet.has(dateStr),
            isToday: dateStr === today,
        };
    });
});

const openCalendar = () => {
    showCalendar.value = true;
};
</script>

<template>
    <div
        class="surface-card group relative w-full min-w-0 cursor-pointer p-5 transition-colors focus-visible:ring-2 focus-visible:ring-[#007AFF]/40 focus-visible:outline-none active:bg-muted/30 sm:p-6"
        tabindex="0"
        role="button"
        aria-label="Open your streak calendar"
        @click="openCalendar"
        @keydown.enter.prevent="openCalendar"
        @keydown.space.prevent="openCalendar"
    >
        <div
            class="relative z-10 flex h-full w-full flex-col justify-between gap-4"
        >
            <div class="flex items-start justify-between gap-3">
                <div class="flex items-center gap-3">
                    <div class="dash-icon-well bg-[#FF9F0A]/15 text-[#FF9F0A]">
                        <Flame class="h-5 w-5" />
                    </div>
                    <div>
                        <p class="dash-label">Streak</p>
                        <div class="flex items-baseline gap-1.5">
                            <h3
                                class="dash-metric text-[34px] leading-none text-foreground sm:text-4xl"
                            >
                                {{ animStreak }}
                            </h3>
                            <span
                                class="text-[13px] font-medium text-muted-foreground"
                                >{{ animStreak === 1 ? 'day' : 'days' }}</span
                            >
                        </div>
                    </div>
                </div>
                <ChevronRight
                    class="h-5 w-5 shrink-0 text-muted-foreground/30 transition-all duration-300 group-hover:translate-x-1 group-hover:text-primary"
                />
            </div>

            <!-- Mini 7-day strip -->
            <div class="flex items-center justify-between gap-1">
                <div
                    v-for="(day, di) in last7Days"
                    :key="di"
                    class="flex flex-1 flex-col items-center gap-1"
                >
                    <div
                        class="flex h-7 w-full items-center justify-center rounded-full"
                        :class="
                            day.isActive
                                ? 'bg-orange-500/15'
                                : 'border border-border/10 bg-muted/20'
                        "
                    >
                        <Flame
                            v-if="day.isActive"
                            class="h-3 w-3"
                            :class="
                                day.isToday
                                    ? 'text-orange-400'
                                    : 'text-orange-400/60'
                            "
                        />
                    </div>
                    <span
                        class="text-[11px] leading-none font-medium"
                        :class="
                            day.isToday
                                ? 'text-foreground/70'
                                : 'text-muted-foreground/30'
                        "
                    >
                        {{ day.label }}
                    </span>
                </div>
            </div>

            <!-- Footer: best streak -->
            <div
                class="flex items-center justify-between border-t border-border/10 pt-3"
            >
                <p class="text-xs text-muted-foreground">
                    Best:
                    <span class="font-semibold text-foreground"
                        >{{ longestStreak }} days</span
                    >
                </p>
                <span
                    class="flex items-center gap-1 text-[13px] font-medium text-[#007AFF]"
                >
                    Calendar
                    <ChevronRight class="h-3 w-3" />
                </span>
            </div>
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
