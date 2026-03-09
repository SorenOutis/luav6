<script setup lang="ts">
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import Button from '@/components/ui/button/Button.vue';

interface Props {
    unreadNotificationCount: number;
    weeklyXP?: number;
    weeklyGoal?: number;
}

const props = defineProps<Props>();
const emit = defineEmits(['quick-action']);
</script>

<template>
    <div class="space-y-4">
        <Card class="border-sidebar-border/70 dark:border-sidebar-border bg-gradient-to-br from-primary/5 to-transparent">
            <CardHeader class="pb-2">
                <CardTitle class="text-sm font-bold">Quick Actions</CardTitle>
            </CardHeader>
            <CardContent class="grid grid-cols-2 gap-2">
                <Button variant="outline" size="sm" class="flex items-center gap-2 justify-start h-10 glass-morphism border-none" @click="emit('quick-action', 'resume')">
                    <span class="text-xs">Resume</span>
                </Button>
                <Button variant="outline" size="sm" class="flex items-center gap-2 justify-start h-10 glass-morphism border-none" @click="emit('quick-action', 'assignments')">
                    <span class="text-xs">Tasks</span>
                </Button>
                <Button variant="outline" size="sm" class="flex items-center gap-2 justify-start h-10 glass-morphism border-none" @click="emit('quick-action', 'leaderboard')">
                    <span class="text-xs">Ranks</span>
                </Button>
                <Button variant="outline" size="sm" class="flex items-center gap-2 justify-start h-10 glass-morphism border-none" @click="emit('quick-action', 'settings')">
                    <span class="text-xs">Profile</span>
                </Button>
            </CardContent>
        </Card>

        <Card v-if="weeklyGoal" class="border-sidebar-border/70 dark:border-sidebar-border overflow-hidden relative">
            <div class="absolute -right-10 -top-10 w-24 h-24 bg-primary/10 rounded-full blur-2xl"></div>
            <CardHeader class="pb-2">
                <CardTitle class="text-sm font-bold">Weekly Goal</CardTitle>
            </CardHeader>
            <CardContent>
                <div class="space-y-3">
                    <div class="flex justify-between items-end">
                        <div class="text-2xl font-bold">{{ weeklyXP }} <span class="text-xs text-muted-foreground font-normal">/ {{ weeklyGoal }} XP</span></div>
                        <div class="text-xs font-bold text-primary">{{ Math.round((weeklyXP || 0) / weeklyGoal * 100) }}%</div>
                    </div>
                    <div class="h-2 bg-muted rounded-full overflow-hidden">
                        <div class="h-full bg-primary transition-all duration-1000" :style="{ width: `${Math.min(100, (weeklyXP || 0) / weeklyGoal * 100)}%` }"></div>
                    </div>
                    <p class="text-[10px] text-muted-foreground">Keep it up! You're almost at your weekly target.</p>
                </div>
            </CardContent>
        </Card>
    </div>
</template>
