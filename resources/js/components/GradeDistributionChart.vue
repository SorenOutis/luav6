<script setup lang="ts">
import { computed } from 'vue';
import { cn } from '@/lib/utils';

interface GradeSegment {
    label: string;
    count: number;
    color: string;
    textColor: string;
}

interface Props {
    segments: GradeSegment[];
    total: number;
    class?: string;
}

const props = withDefaults(defineProps<Props>(), {
    segments: () => [],
    total: 0,
});

const RADIUS = 40;
const CIRCUMFERENCE = 2 * Math.PI * RADIUS;
const GAP = 2; // gap between segments in degrees

const chartData = computed(() => {
    if (props.total === 0) return [];

    let offset = 0;
    return props.segments
        .filter((s) => s.count > 0)
        .map((segment, index) => {
            const percentage = segment.count / props.total;
            const segmentLength = percentage * CIRCUMFERENCE;
            const gapLength = (GAP / 360) * CIRCUMFERENCE;

            const dashArray =
                index < props.segments.filter((s) => s.count > 0).length - 1
                    ? `${Math.max(segmentLength - gapLength, 0)} ${CIRCUMFERENCE - Math.max(segmentLength - gapLength, 0)}`
                    : `${Math.max(segmentLength, 0)} ${CIRCUMFERENCE - Math.max(segmentLength, 0)}`;

            const dashOffset = -offset;

            offset += segmentLength;

            return {
                ...segment,
                percentage,
                dashArray,
                dashOffset,
            };
        });
});

const isEmpty = computed(() => props.total === 0);
</script>

<template>
    <div :class="cn('flex items-center gap-4', props.class)">
        <div class="relative shrink-0">
            <svg viewBox="0 0 100 100" class="h-24 w-24 -rotate-90">
                <!-- Background circle -->
                <circle
                    cx="50"
                    cy="50"
                    :r="RADIUS"
                    fill="none"
                    stroke="hsl(var(--muted))"
                    stroke-width="8"
                />
                <!-- Segments -->
                <circle
                    v-for="(segment, i) in chartData"
                    :key="i"
                    cx="50"
                    cy="50"
                    :r="RADIUS"
                    fill="none"
                    :stroke="segment.color"
                    stroke-width="8"
                    stroke-linecap="round"
                    :stroke-dasharray="segment.dashArray"
                    :stroke-dashoffset="segment.dashOffset"
                    class="transition-all duration-700 ease-out"
                />
            </svg>
            <!-- Center text -->
            <div class="absolute inset-0 flex items-center justify-center">
                <div class="text-center">
                    <div
                        v-if="!isEmpty"
                        class="text-[17px] leading-none font-semibold tabular-nums"
                    >
                        {{ total }}
                    </div>
                    <div v-else class="text-sm text-muted-foreground">—</div>
                    <div
                        class="text-[12px] leading-tight text-muted-foreground"
                    >
                        Total
                    </div>
                </div>
            </div>
        </div>
        <!-- Legend -->
        <div class="flex flex-col gap-1.5">
            <div
                v-for="(segment, i) in segments"
                :key="i"
                class="flex items-center gap-2 text-[13px]"
            >
                <span
                    class="h-2.5 w-2.5 shrink-0 rounded-full"
                    :style="{ backgroundColor: segment.color }"
                />
                <span class="text-muted-foreground">{{ segment.label }}</span>
                <span class="ml-auto font-medium" :class="segment.textColor">
                    {{ segment.count }}
                </span>
            </div>
        </div>
    </div>
</template>
