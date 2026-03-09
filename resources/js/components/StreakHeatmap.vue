<script setup lang="ts">
import { computed } from 'vue';

interface Props {
    loginDates: string[];
}

const props = defineProps<Props>();

// Simple heatmap visualization (7 columns for days of week, 4-5 rows for weeks)
const days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
</script>

<template>
    <div class="flex flex-col gap-2">
        <div class="grid grid-cols-7 gap-1">
            <div v-for="day in days" :key="day" class="text-[10px] text-muted-foreground text-center">
                {{ day }}
            </div>
        </div>
        <div class="grid grid-cols-7 gap-1">
            <div v-for="i in 28" :key="i" 
                :class="['h-3 w-full rounded-[2px]', i % 4 === 0 ? 'bg-primary/60' : i % 7 === 0 ? 'bg-primary/20' : 'bg-muted']">
            </div>
        </div>
        <div class="flex justify-between items-center mt-2">
            <span class="text-[10px] text-muted-foreground">Less</span>
            <div class="flex gap-1">
                <div class="h-2 w-2 rounded-[2px] bg-muted"></div>
                <div class="h-2 w-2 rounded-[2px] bg-primary/20"></div>
                <div class="h-2 w-2 rounded-[2px] bg-primary/40"></div>
                <div class="h-2 w-2 rounded-[2px] bg-primary/60"></div>
                <div class="h-2 w-2 rounded-[2px] bg-primary"></div>
            </div>
            <span class="text-[10px] text-muted-foreground">More</span>
        </div>
    </div>
</template>
