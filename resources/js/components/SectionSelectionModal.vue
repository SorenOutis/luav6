<script setup lang="ts">
import { ref } from 'vue';
import { useForm } from '@inertiajs/vue3';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Check, Loader2 } from 'lucide-vue-next';
import { update as updateSectionRoute } from '@/routes/profile/section';

interface Section {
    id: number;
    name: string;
}

const props = defineProps<{
    sections: Section[];
    show: boolean;
}>();

const selectedSections = ref<number[]>([]);

const form = useForm({
    section_ids: [] as number[],
});

const toggleSection = (id: number) => {
    const index = selectedSections.value.indexOf(id);
    if (index > -1) {
        selectedSections.value.splice(index, 1);
    } else {
        selectedSections.value.push(id);
    }
};

const submit = () => {
    if (selectedSections.value.length === 0) return;
    
    form.section_ids = selectedSections.value;
    form.patch(updateSectionRoute().url, {
        preserveScroll: true,
        onSuccess: () => {
            // Modal will close because Dashboard will re-render with sectionName
        },
    });
};
</script>

<template>
    <Dialog :open="show">
        <DialogContent 
            class="w-[95vw] sm:max-w-[700px] border-primary/20 bg-background shadow-2xl p-6 sm:p-10"
            :show-close-button="false"
            @pointer-down-outside.prevent
            @escape-key-down.prevent
        >
            <DialogHeader class="sm:text-center">
                <DialogTitle class="text-2xl sm:text-3xl font-black bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-transparent">
                    Welcome to the Academy
                </DialogTitle>
                <DialogDescription class="text-muted-foreground/80 pt-2 text-sm sm:text-base leading-relaxed max-w-2xl mx-auto">
                    To personalize your experience and track your progress on the leaderboard, please select your assigned sections.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-6 sm:py-8 relative z-50">
                <div class="space-y-4">
                    <label class="text-xs font-black uppercase tracking-widest text-muted-foreground/60 block sm:text-center">
                        Choose your sections
                    </label>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 max-h-[380px] overflow-y-auto pr-2 custom-scrollbar p-1">
                        <button
                            v-for="section in sections"
                            :key="section.id"
                            @click="toggleSection(section.id)"
                            type="button"
                            :class="[
                                'relative flex items-center justify-center min-h-[80px] p-6 rounded-2xl border transition-all duration-500 text-center overflow-hidden group/card',
                                selectedSections.includes(section.id)
                                    ? 'bg-primary border-primary shadow-[0_0_25px_rgba(var(--primary-rgb),0.3)] scale-[1.02] z-10'
                                    : 'bg-muted/30 border-border/50 hover:border-primary/40 hover:bg-muted/50'
                            ]"
                        >
                            <!-- Tech Grid Background -->
                            <div class="absolute inset-0 opacity-[0.03] pointer-events-none group-hover/card:opacity-[0.05] transition-opacity">
                                <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                                    <defs>
                                        <pattern :id="`modal-grid-${section.id}`" width="10" height="10" patternUnits="userSpaceOnUse">
                                            <path d="M 10 0 L 0 0 0 10" fill="none" stroke="currentColor" stroke-width="0.5"/>
                                        </pattern>
                                    </defs>
                                    <rect width="100%" height="100%" :fill="`url(#modal-grid-${section.id})`" />
                                </svg>
                            </div>

                            <!-- Selection Glow (When Selected) -->
                            <div v-if="selectedSections.includes(section.id)" class="absolute inset-0 bg-white/10 animate-pulse pointer-events-none"></div>

                            <div class="relative z-10 space-y-1">
                                <p class="text-[8px] font-black uppercase tracking-[0.3em] font-mono transition-colors"
                                    :class="selectedSections.includes(section.id) ? 'text-primary-foreground/60' : 'text-muted-foreground/40 group-hover/card:text-primary/60'">
                                    >_SECTION_NODE
                                </p>
                                <span :class="[
                                    'text-sm font-black transition-all duration-300 uppercase tracking-tight block',
                                    selectedSections.includes(section.id) ? 'text-primary-foreground scale-110' : 'text-foreground/70 group-hover/card:text-primary'
                                ]">
                                    {{ section.name }}
                                </span>
                            </div>

                            <!-- Status Indicator -->
                            <div v-if="selectedSections.includes(section.id)" class="absolute top-2 right-3">
                                <div class="w-1.5 h-1.5 rounded-full bg-primary-foreground animate-ping opacity-75"></div>
                            </div>
                        </button>
                    </div>

                    <p v-if="sections.length === 0" class="text-xs text-destructive mt-1 font-medium italic">
                        No sections available. Please contact your admin.
                    </p>
                </div>
            </div>

            <DialogFooter class="relative z-0 pt-4 flex flex-col sm:items-center">
                <Button 
                    @click="submit" 
                    class="w-full sm:max-w-sm h-14 text-base font-black uppercase tracking-wider shadow-lg shadow-primary/20 transition-all hover:translate-y-[-2px] active:translate-y-[0] disabled:opacity-50"
                    :disabled="selectedSections.length === 0 || form.processing"
                >
                    <template v-if="form.processing">
                        <Loader2 class="mr-2 h-5 w-5 animate-spin" />
                        Initializing...
                    </template>
                    <template v-else>
                        <Check class="mr-2 h-5 w-5" />
                        Confirm & Enter Dashboard
                    </template>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.custom-scrollbar::-webkit-scrollbar {
    width: 4px;
}
.custom-scrollbar::-webkit-scrollbar-track {
    background: transparent;
}
.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(var(--primary), 0.1);
    border-radius: 10px;
}
.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(var(--primary), 0.2);
}
</style>
