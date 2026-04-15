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
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[350px] overflow-y-auto pr-2 custom-scrollbar">
                        <button
                            v-for="section in sections"
                            :key="section.id"
                            @click="toggleSection(section.id)"
                            type="button"
                            :class="[
                                'flex items-center justify-between w-full p-4 rounded-xl border transition-all duration-200 text-left group',
                                selectedSections.includes(section.id)
                                    ? 'bg-primary/10 border-primary shadow-[0_0_15px_rgba(var(--primary),0.1)]'
                                    : 'bg-muted/30 border-border/50 hover:border-primary/40 hover:bg-muted/50'
                            ]"
                        >
                            <span :class="[
                                'font-bold transition-colors',
                                selectedSections.includes(section.id) ? 'text-primary' : 'text-foreground/70 group-hover:text-foreground'
                            ]">
                                {{ section.name }}
                            </span>
                            <div :class="[
                                'w-6 h-6 rounded-full border-2 flex items-center justify-center transition-all',
                                selectedSections.includes(section.id)
                                    ? 'bg-primary border-primary scale-110'
                                    : 'border-muted-foreground/30'
                            ]">
                                <Check v-if="selectedSections.includes(section.id)" class="w-3.5 h-3.5 text-primary-foreground stroke-[4px]" />
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
