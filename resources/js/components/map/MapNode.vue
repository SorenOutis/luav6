<script setup lang="ts">
import { computed, ref } from 'vue';
import { Motion } from '@motionone/vue';
import {
    Lock,
    CheckCircle2,
    Crown,
    BookOpen,
    ScrollText,
    Zap,
    Sparkles,
} from 'lucide-vue-next';

interface Props {
    title: string;
    type: 'lesson' | 'exam' | 'boss';
    status: 'locked' | 'available' | 'completed';
    x: number;
    y: number;
    primaryColor?: string;
    /** XP reward for completing this node (0 hides the hint). */
    rewardXp?: number;
    /** Requirements met / total (for the locked-progress ring + tooltip). */
    metReqs?: number;
    totalReqs?: number;
    /** Highlight as the suggested next step for the learner. */
    isNext?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    primaryColor: '#10b981',
    rewardXp: 0,
    metReqs: 0,
    totalReqs: 0,
    isNext: false,
});
const emit = defineEmits(['click']);
const hovered = ref(false);

const containerStyle = computed(() => ({
    left: `${props.x}px`,
    top: `${props.y}px`,
    transform: 'translate(-50%, -50%)',
}));

const nodeClasses = computed(() => {
    const base =
        'relative flex items-center justify-center rounded-full transition-all duration-500 cursor-pointer group border-2';
    const size =
        props.type === 'boss'
            ? 'w-20 h-20'
            : props.type === 'exam'
              ? 'w-16 h-16'
              : 'w-14 h-14';

    if (props.status === 'locked') {
        return `${base} ${size} bg-slate-100/50 border-slate-200 grayscale opacity-60 cursor-pointer hover:opacity-80`;
    }
    if (props.status === 'completed') {
        return `${base} ${size} bg-emerald-50 border-emerald-500 shadow-[0_0_15px_rgba(16,185,129,0.2)]`;
    }
    // Available
    return `${base} ${size} bg-white hover:scale-110`;
});

const ringSize = computed(() =>
    props.type === 'boss' ? 96 : props.type === 'exam' ? 80 : 72,
);
const ringRadius = computed(() => ringSize.value / 2 - 3);
const ringCircumference = computed(() => 2 * Math.PI * ringRadius.value);
const ringDashOffset = computed(() => {
    const frac = props.totalReqs > 0 ? props.metReqs / props.totalReqs : 0;
    return ringCircumference.value * (1 - frac);
});
const showLockedRing = computed(
    () => props.status === 'locked' && props.totalReqs > 0,
);

const nodeStyle = computed(() => {
    if (props.status === 'available') {
        return {
            borderColor: props.primaryColor,
            boxShadow: `0 0 22px ${props.primaryColor}55`,
        };
    }
    return {};
});

const iconColor = computed(() => {
    if (props.status === 'locked') return undefined;
    if (props.status === 'completed') return '#10b981';
    return props.primaryColor;
});

const typeIcon = computed(() => {
    if (props.type === 'boss') return Crown;
    if (props.type === 'exam') return ScrollText;
    return BookOpen;
});

const icon = computed(() => {
    if (props.status === 'locked') return Lock;
    if (props.status === 'completed') return CheckCircle2;
    return typeIcon.value;
});

const typeLabel = computed(() =>
    props.type === 'boss'
        ? 'Boss Challenge'
        : props.type === 'exam'
          ? 'Exam'
          : 'Lesson',
);

const statusLabel = computed(() => {
    if (props.status === 'completed') return 'Completed';
    if (props.status === 'available')
        return props.isNext ? 'Recommended next' : 'Ready to start';
    if (props.totalReqs > 0)
        return `${props.metReqs} / ${props.totalReqs} requirements met`;
    return 'Locked';
});

const handleClick = () => {
    // Always emit — the parent decides whether to navigate or show
    // the unlock-requirements breakdown for locked nodes.
    emit('click');
};
</script>

<template>
    <div
        class="absolute"
        :style="containerStyle"
        @click="handleClick"
        @mouseenter="hovered = true"
        @mouseleave="hovered = false"
    >
        <!-- Pulse effect for active nodes (stronger for the recommended next node) -->
        <div
            v-if="status === 'available'"
            class="absolute inset-0 scale-125 animate-ping rounded-full"
            :class="isNext ? 'bg-emerald-400/30' : 'bg-primary/20'"
        ></div>

        <!-- Locked: progress ring showing how many requirements are met -->
        <svg
            v-if="showLockedRing"
            class="pointer-events-none absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 -rotate-90"
            :width="ringSize"
            :height="ringSize"
        >
            <circle
                :cx="ringSize / 2"
                :cy="ringSize / 2"
                :r="ringRadius"
                stroke="rgba(255,255,255,0.08)"
                stroke-width="3"
                fill="none"
            />
            <circle
                :cx="ringSize / 2"
                :cy="ringSize / 2"
                :r="ringRadius"
                :stroke="primaryColor"
                stroke-width="3"
                fill="none"
                stroke-linecap="round"
                :stroke-dasharray="ringCircumference"
                :stroke-dashoffset="ringDashOffset"
                class="transition-[stroke-dashoffset] duration-700"
            />
        </svg>

        <!-- NEXT badge -->
        <div
            v-if="isNext && status === 'available'"
            class="absolute -top-3 left-1/2 z-10 flex -translate-x-1/2 items-center gap-0.5 rounded-full bg-emerald-500 px-1.5 py-0.5 text-[9px] font-bold tracking-widest whitespace-nowrap text-black uppercase shadow-lg"
        >
            <Sparkles class="h-2.5 w-2.5" /> Next
        </div>

        <Motion
            :hover="{ scale: status !== 'locked' ? 1.1 : 1.04 }"
            :press="{ scale: status !== 'locked' ? 0.95 : 1 }"
            :class="nodeClasses"
            :style="nodeStyle"
        >
            <!-- Background Glow -->
            <div
                v-if="status !== 'locked'"
                class="absolute inset-0 rounded-full bg-gradient-to-tr from-transparent via-white/30 to-white/50 opacity-0 transition-opacity group-hover:opacity-100"
            ></div>

            <component
                :is="icon"
                class="h-6 w-6 transition-colors duration-300"
                :class="status === 'locked' ? 'text-slate-400' : ''"
                :style="iconColor ? { color: iconColor } : {}"
            />

            <!-- Title Label -->
            <div
                class="absolute -bottom-8 left-1/2 -translate-x-1/2 whitespace-nowrap"
            >
                <span
                    class="text-xs font-medium tracking-wide transition-colors duration-300"
                    :class="
                        status === 'locked' ? 'text-white/30' : 'text-white/80'
                    "
                >
                    {{ title }}
                </span>
            </div>
        </Motion>

        <!-- Rich hover card -->
        <transition
            enter-active-class="transition duration-150"
            leave-active-class="transition duration-100"
            enter-from-class="opacity-0 translate-y-1"
            leave-to-class="opacity-0 translate-y-1"
        >
            <div
                v-if="hovered"
                class="pointer-events-none absolute bottom-full left-1/2 z-20 mb-12 w-56 -translate-x-1/2 rounded-xl border border-white/10 bg-[#0b0b0d]/95 p-3 shadow-2xl backdrop-blur-xl"
            >
                <div class="flex items-center gap-2">
                    <component
                        :is="typeIcon"
                        class="h-3.5 w-3.5"
                        :style="{ color: primaryColor }"
                    />
                    <span
                        class="text-[10px] font-semibold tracking-widest text-white/40 uppercase"
                        >{{ typeLabel }}</span
                    >
                </div>
                <div
                    class="mt-1 text-sm leading-snug font-semibold tracking-tight text-white"
                >
                    {{ title }}
                </div>
                <div
                    class="mt-1 text-[11px] font-medium"
                    :style="{
                        color:
                            status === 'locked'
                                ? '#94a3b8'
                                : status === 'completed'
                                  ? '#10b981'
                                  : primaryColor,
                    }"
                >
                    {{ statusLabel }}
                </div>
                <div
                    v-if="rewardXp > 0"
                    class="mt-2 flex items-center gap-1.5 text-[11px] text-white/70"
                >
                    <Zap class="h-3 w-3 text-amber-400" />
                    <span class="text-white/40"
                        >{{
                            status === 'completed' ? 'Earned' : 'Reward'
                        }}:</span
                    >
                    <span class="tabular-nums"
                        >+{{ rewardXp.toLocaleString() }} XP</span
                    >
                </div>
                <div v-if="showLockedRing" class="mt-2">
                    <div class="h-1 overflow-hidden rounded-full bg-white/10">
                        <div
                            class="h-full rounded-full transition-[width] duration-500"
                            :style="{
                                width: `${Math.round((metReqs / totalReqs) * 100)}%`,
                                backgroundColor: primaryColor,
                            }"
                        ></div>
                    </div>
                    <div class="mt-1 text-[10px] tracking-wide text-white/40">
                        {{ metReqs }} of {{ totalReqs }} unlocked · Click for
                        details
                    </div>
                </div>
                <div
                    v-else-if="status === 'locked'"
                    class="mt-2 text-[10px] text-white/40"
                >
                    Click for unlock details
                </div>
            </div>
        </transition>
    </div>
</template>

<style scoped>
.node-glow {
    filter: blur(8px);
}
</style>
