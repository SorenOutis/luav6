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
    <div class="absolute bottom-6 left-1/2 -translate-x-1/2 z-40 flex items-center gap-3 px-4 py-2.5 bg-white/10 backdrop-blur-xl border border-white/20 rounded-full shadow-2xl">
        <template v-for="(world, index) in worlds" :key="world.id">
            <Motion
                :hover="{ scale: 1.1, y: -5 }"
                :press="{ scale: 0.95 }"
                class="relative group"
            >
                <button
                    @click="$emit('select', world.id)"
                    :title="world.name"
                    :class="[
                        'w-12 h-12 rounded-full flex items-center justify-center transition-all duration-500 cursor-pointer',
                        currentWorldId === world.id 
                            ? 'bg-white text-black scale-110 shadow-[0_0_20px_rgba(255,255,255,0.4)]' 
                            : 'bg-black/20 text-white/60 hover:bg-black/40 hover:text-white',
                        !unlockedWorldIds.includes(world.id) ? 'opacity-60 hover:opacity-90' : ''
                    ]"
                >
                    <component :is="unlockedWorldIds.includes(world.id) ? Globe : Lock" class="w-5 h-5" />
                </button>

                <!-- Tooltip -->
                <div class="absolute -top-12 left-1/2 -translate-x-1/2 px-3 py-1 bg-black text-white text-[10px] uppercase tracking-widest rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                    {{ world.name }}
                </div>
            </Motion>

            <!-- Connector Line -->
            <div 
                v-if="index < worlds.length - 1" 
                class="w-4 h-px bg-white/20"
            ></div>
        </template>
    </div>
</template>
