<script setup lang="ts">
import { Motion } from '@motionone/vue';
import type { WorldBiome } from '@/config/mapConfig';
import { Globe, Lock } from 'lucide-vue-next';

interface Props {
    worlds: WorldBiome[];
    currentWorldId: string;
    unlockedWorldIds: string[];
}

defineProps<Props>();
defineEmits(['select']);
</script>

<template>
    <div
        class="absolute bottom-6 left-1/2 z-40 flex -translate-x-1/2 items-center gap-3 rounded-full border border-white/20 bg-white/10 px-4 py-2.5 shadow-2xl backdrop-blur-xl"
    >
        <template v-for="(world, index) in worlds" :key="world.id">
            <Motion
                :hover="{ scale: 1.1, y: -5 }"
                :press="{ scale: 0.95 }"
                class="group relative"
            >
                <button
                    @click="$emit('select', world.id)"
                    :title="world.name"
                    :class="[
                        'flex h-12 w-12 cursor-pointer items-center justify-center rounded-full transition-all duration-500',
                        currentWorldId === world.id
                            ? 'scale-110 bg-white text-black shadow-[0_0_20px_rgba(255,255,255,0.4)]'
                            : 'bg-black/20 text-white/60 hover:bg-black/40 hover:text-white',
                        !unlockedWorldIds.includes(world.id)
                            ? 'opacity-60 hover:opacity-90'
                            : '',
                    ]"
                >
                    <component
                        :is="unlockedWorldIds.includes(world.id) ? Globe : Lock"
                        class="h-5 w-5"
                    />
                </button>

                <!-- Tooltip -->
                <div
                    class="pointer-events-none absolute -top-12 left-1/2 -translate-x-1/2 rounded bg-black px-3 py-1 text-[10px] tracking-widest whitespace-nowrap text-white uppercase opacity-0 transition-opacity group-hover:opacity-100"
                >
                    {{ world.name }}
                </div>
            </Motion>

            <!-- Connector Line -->
            <div
                v-if="index < worlds.length - 1"
                class="h-px w-4 bg-white/20"
            ></div>
        </template>
    </div>
</template>
