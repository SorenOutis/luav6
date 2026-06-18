<script setup lang="ts">
interface Props {
    loginDates: string[];
}

defineProps<Props>();

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
        case 0:
            return 'bg-muted/10 border-border/10';
        case 1:
            return 'bg-primary/20 border-primary/10';
        case 2:
            return 'bg-primary/40 border-primary/20 shadow-[0_0_10px_rgba(var(--primary-rgb),0.1)]';
        case 3:
            return 'bg-primary/70 border-primary/40 shadow-[0_0_15px_rgba(var(--primary-rgb),0.2)]';
        case 4:
            return 'bg-primary border-primary/60 shadow-[0_0_20px_rgba(var(--primary-rgb),0.4)]';
        default:
            return 'bg-muted/10 border-border/10';
    }
};
</script>

<template>
    <div class="relative z-10 flex flex-col gap-2 sm:gap-3">
        <!-- Labels -->
        <div
            class="mb-0.5 grid grid-cols-7 gap-1 text-center text-[8px] font-black tracking-widest text-muted-foreground/60 uppercase sm:mb-1 sm:gap-2 sm:text-[10px]"
        >
            <span v-for="day in days" :key="day">{{ day }}</span>
        </div>

        <!-- Grid -->
        <div class="grid grid-cols-7 gap-1 sm:gap-2">
            <div
                v-for="i in 28"
                :key="i"
                class="group/cell relative aspect-square cursor-pointer rounded-sm border transition-all duration-300 hover:scale-110 sm:rounded-md"
                :class="getLevelClass(getActivityLevel(i))"
            >
                <!-- Tooltip -->
                <div
                    class="pointer-events-none absolute bottom-full left-1/2 z-50 mb-2 -translate-x-1/2 rounded bg-foreground px-2 py-1 text-[8px] font-black tracking-widest whitespace-nowrap text-background uppercase opacity-0 shadow-2xl transition-opacity group-hover/cell:opacity-100 sm:text-[9px]"
                >
                    Lvl {{ getActivityLevel(i) }} Output
                </div>
            </div>
        </div>

        <!-- Legend -->
        <div
            class="mt-3 flex items-center justify-between border-t border-border/10 pt-3 text-[8px] font-black tracking-widest text-muted-foreground/40 uppercase sm:mt-4 sm:pt-4 sm:text-[9px]"
        >
            <span>Standby</span>
            <div class="flex items-center gap-1 sm:gap-2">
                <div
                    class="h-2 w-2 rounded-[2px] border border-border/10 bg-muted/10 sm:h-3.5 sm:w-3.5 sm:rounded-sm"
                ></div>
                <div
                    class="h-2 w-2 rounded-[2px] border border-primary/10 bg-primary/20 sm:h-3.5 sm:w-3.5 sm:rounded-sm"
                ></div>
                <div
                    class="h-2 w-2 rounded-[2px] border border-primary/20 bg-primary/40 sm:h-3.5 sm:w-3.5 sm:rounded-sm"
                ></div>
                <div
                    class="h-2 w-2 rounded-[2px] border border-primary/40 bg-primary/70 sm:h-3.5 sm:w-3.5 sm:rounded-sm"
                ></div>
                <div
                    class="h-2 w-2 rounded-[2px] border border-primary/60 bg-primary sm:h-3.5 sm:w-3.5 sm:rounded-sm"
                ></div>
            </div>
            <span>Peak</span>
        </div>
    </div>
</template>
