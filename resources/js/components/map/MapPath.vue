<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    id?: string;
    startX: number;
    startY: number;
    endX: number;
    endY: number;
    status: 'locked' | 'available' | 'completed';
    availableColor?: string;
}

const props = withDefaults(defineProps<Props>(), { availableColor: '#3b82f6', id: 'p' });

const pathData = computed(() => {
    // Create a smooth curve between points
    const midX = (props.startX + props.endX) / 2;
    // We can add a slight vertical offset to the midpoint for a "curved" look
    const curveOffset = 20; 
    
    return `M ${props.startX} ${props.startY} Q ${midX} ${props.startY - curveOffset} ${props.endX} ${props.endY}`;
});

const strokeColor = computed(() => {
    if (props.status === 'completed') return '#10b981'; // Emerald 500
    if (props.status === 'available') return props.availableColor;
    return '#475569'; // Slate 600 — visible on dark bg
});

const strokeDasharray = computed(() => {
    return props.status === 'locked' ? '8, 8' : 'none';
});
</script>

<template>
    <svg class="absolute inset-0 pointer-events-none w-full h-full overflow-visible">
        <defs>
            <filter :id="`glow-${id}`" x="-20%" y="-20%" width="140%" height="140%">
                <feGaussianBlur stdDeviation="2" result="blur" />
                <feComposite in="SourceGraphic" in2="blur" operator="over" />
            </filter>
        </defs>

        <path
            :d="pathData"
            fill="none"
            :stroke="strokeColor"
            stroke-width="3"
            :stroke-dasharray="strokeDasharray"
            stroke-linecap="round"
            class="transition-all duration-1000"
            :filter="status !== 'locked' ? `url(#glow-${id})` : ''"
        />
        
        <!-- Animated flow effect for completed / available paths -->
        <path
            v-if="status !== 'locked'"
            :d="pathData"
            fill="none"
            :stroke="status === 'completed' ? 'white' : availableColor"
            stroke-width="1.25"
            :stroke-opacity="status === 'completed' ? 0.45 : 0.7"
            class="path-flow"
        />
    </svg>
</template>

<style scoped>
.path-flow {
    stroke-dasharray: 10, 100;
    animation: flow 3s linear infinite;
}

@keyframes flow {
    from {
        stroke-dashoffset: 110;
    }
    to {
        stroke-dashoffset: 0;
    }
}
</style>
