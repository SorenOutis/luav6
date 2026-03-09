<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Progress from '@/components/ui/progress/Progress.vue';

interface Props {
    currentXP: number;
    goalXP: number;
}

const props = defineProps<Props>();

const progressPercentage = (props.currentXP / props.goalXP) * 100;
</script>

<template>
    <Card class="border-sidebar-border/70 dark:border-sidebar-border bg-gradient-to-br from-accent/5 to-transparent transition-all duration-200 hover:shadow-md">
        <CardHeader class="pb-2">
            <CardTitle class="text-sm font-semibold flex items-center gap-2">
                Weekly Goal
            </CardTitle>
        </CardHeader>
        <CardContent>
            <div class="space-y-3">
                <div class="flex items-center justify-between text-xs">
                    <span class="text-muted-foreground">XP earned this week</span>
                    <span class="font-bold text-primary">{{ currentXP.toLocaleString() }} / {{ goalXP.toLocaleString() }}</span>
                </div>
                <Progress :value="progressPercentage" class="h-2" />
                <p class="text-[10px] text-muted-foreground text-center">
                    {{ progressPercentage >= 100 ? 'Goal reached! Amazing work! 🎉' : `${Math.round(100 - progressPercentage)}% left to reach your goal` }}
                </p>
            </div>
        </CardContent>
    </Card>
</template>
