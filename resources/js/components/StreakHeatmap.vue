<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    loginDates: string[];
}

const props = defineProps<Props>();

const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];

// Deterministic mock data to preview how a bustling heatmap looks
const getActivityLevel = (index: number) => {
    if (index === 5 || index === 12 || index === 19) return 0;
    if (index % 7 === 6) return 1;
    if (index % 5 === 0) return 4;
    if (index % 3 === 0) return 3;
    if (index % 2 === 0) return 2;
    return 1;
};

const getLevelClass = (level: number) => {
    switch (level) {
        case 0: return 'bg-muted/10 border-border/10';
        case 1: return 'bg-primary/20 border-primary/10';
        case 2: return 'bg-primary/40 border-primary/20 shadow-[0_0_10px_rgba(var(--primary-rgb),0.1)]';
        case 3: return 'bg-primary/70 border-primary/40 shadow-[0_0_15px_rgba(var(--primary-rgb),0.2)]';
        case 4: return 'bg-primary border-primary/60 shadow-[0_0_20px_rgba(var(--primary-rgb),0.4)]';
        default: return 'bg-muted/10 border-border/10';
    }
};
</script>

<template>
    <div class="flex flex-col gap-2 sm:gap-3 relative z-10">
        <!-- Labels -->
        <div class="grid grid-cols-7 gap-1 sm:gap-2 text-[8px] sm:text-[10px] font-black uppercase tracking-widest text-muted-foreground/60 text-center mb-0.5 sm:mb-1">
            <span v-for="day in days" :key="day">{{ day }}</span>
        </div>
        
        <!-- Grid -->
        <div class="grid grid-cols-7 gap-1 sm:gap-2">
            <div v-for="i in 28" :key="i"
                class="aspect-square rounded-sm sm:rounded-md border transition-all duration-300 hover:scale-110 cursor-pointer relative group/cell"
                :class="getLevelClass(getActivityLevel(i))">
                <!-- Tooltip -->
                <div class="absolute bottom-full left-1/2 -translate-x-1/2 mb-2 px-2 py-1 bg-foreground text-background text-[8px] sm:text-[9px] font-black tracking-widest uppercase rounded opacity-0 group-hover/cell:opacity-100 transition-opacity pointer-events-none whitespace-nowrap z-50 shadow-2xl">
                    Lvl {{ getActivityLevel(i) }} Output
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div class="flex items-center justify-between text-[8px] sm:text-[9px] font-black uppercase tracking-widest text-muted-foreground/40 mt-3 sm:mt-4 pt-3 sm:pt-4 border-t border-border/10">
            <span>Standby</span>
            <div class="flex items-center gap-1 sm:gap-2">
                <div class="w-2 sm:w-3.5 h-2 sm:h-3.5 rounded-[2px] sm:rounded-sm bg-muted/10 border border-border/10"></div>
                <div class="w-2 sm:w-3.5 h-2 sm:h-3.5 rounded-[2px] sm:rounded-sm bg-primary/20 border border-primary/10"></div>
                <div class="w-2 sm:w-3.5 h-2 sm:h-3.5 rounded-[2px] sm:rounded-sm bg-primary/40 border border-primary/20"></div>
                <div class="w-2 sm:w-3.5 h-2 sm:h-3.5 rounded-[2px] sm:rounded-sm bg-primary/70 border border-primary/40"></div>
                <div class="w-2 sm:w-3.5 h-2 sm:h-3.5 rounded-[2px] sm:rounded-sm bg-primary border border-primary/60"></div>
            </div>
            <span>Peak</span>
        </div>
    </div>
</template>
