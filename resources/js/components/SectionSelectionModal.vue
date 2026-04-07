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

const selectedSection = ref<string | null>(null);

const form = useForm({
    section_id: null as number | null,
});

const submit = () => {
    if (!selectedSection.value) return;
    
    form.section_id = parseInt(selectedSection.value);
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
            class="w-[95vw] max-w-[425px] border-primary/20 bg-background shadow-2xl p-6 sm:p-8"
            :show-close-button="false"
            @pointer-down-outside.prevent
            @escape-key-down.prevent
        >
            <DialogHeader>
                <DialogTitle class="text-2xl font-black bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-transparent">
                    Welcome to the Academy
                </DialogTitle>
                <DialogDescription class="text-muted-foreground/80 pt-2 text-sm leading-relaxed">
                    To personalize your experience and track your progress on the leaderboard, please select your assigned section.
                </DialogDescription>
            </DialogHeader>

            <div class="grid gap-4 py-8 relative z-50">
                <div class="space-y-4">
                    <label class="text-xs font-black uppercase tracking-widest text-muted-foreground/60">
                        Choose your section
                    </label>
                    <Select v-model="selectedSection">
                        <SelectTrigger class="w-full h-14 bg-muted/30 border-border/50 focus:ring-primary/20 transition-all font-medium text-base">
                            <SelectValue placeholder="Select a section..." />
                        </SelectTrigger>
                        <SelectContent 
                            class="z-[10000] bg-white dark:bg-zinc-950 border-primary/20 shadow-2xl opacity-100" 
                            position="popper" 
                            :side-offset="8"
                        >
                            <SelectItem 
                                v-for="section in sections" 
                                :key="section.id" 
                                :value="section.id.toString()"
                                class="h-12 focus:bg-primary/10 transition-colors"
                            >
                                {{ section.name }}
                            </SelectItem>
                        </SelectContent>
                    </Select>
                    <p v-if="sections.length === 0" class="text-xs text-destructive mt-1 font-medium italic">
                        No sections available. Please contact your admin.
                    </p>
                </div>
            </div>

            <DialogFooter class="relative z-0 pt-2">
                <Button 
                    @click="submit" 
                    class="w-full h-14 text-base font-black uppercase tracking-wider shadow-lg shadow-primary/20 transition-all hover:translate-y-[-2px] active:translate-y-[0] disabled:opacity-50"
                    :disabled="!selectedSection || form.processing"
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
