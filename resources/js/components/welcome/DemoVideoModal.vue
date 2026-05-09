<script setup lang="ts">
import { computed } from 'vue';
import { BarChart3, Film, GraduationCap, Play, ShieldCheck, X } from 'lucide-vue-next';

const props = defineProps<{
    open: boolean;
    videoUrl?: string | null;
}>();

const emit = defineEmits<{
    close: [];
}>();

const hasVideo = computed(() => Boolean(props.videoUrl));

const demoHighlights = [
    { label: 'Create classes', icon: GraduationCap },
    { label: 'Guide students', icon: Play },
    { label: 'Track progress', icon: BarChart3 },
    { label: 'Manage controls', icon: ShieldCheck },
];
</script>

<template>
    <Teleport to="body">
        <Transition
            enter-active-class="transition duration-200 ease-out"
            enter-from-class="opacity-0"
            enter-to-class="opacity-100"
            leave-active-class="transition duration-150 ease-in"
            leave-from-class="opacity-100"
            leave-to-class="opacity-0"
        >
            <div v-if="open" class="fixed inset-0 z-[120] flex items-center justify-center bg-background/85 p-4 backdrop-blur-xl" @click.self="emit('close')">
                <div class="relative w-full max-w-5xl overflow-hidden border border-border/40 bg-card shadow-2xl">
                    <div class="flex items-center justify-between border-b border-border/25 px-5 py-4 sm:px-6">
                        <div class="flex items-center gap-3">
                            <div class="flex h-10 w-10 items-center justify-center border border-primary/25 bg-primary/10">
                                <Film class="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p class="text-[8px] font-black uppercase tracking-[0.3em] text-primary">System Demo</p>
                                <h2 class="text-base font-black uppercase tracking-tight sm:text-lg">Watch LUAV Learning Engine</h2>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="flex h-10 w-10 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                            aria-label="Close demo video"
                            @click="emit('close')"
                        >
                            <X class="h-5 w-5" />
                        </button>
                    </div>

                    <div class="grid gap-0 lg:grid-cols-[1.45fr_0.55fr]">
                        <div class="bg-black">
                            <video
                                v-if="hasVideo"
                                :src="videoUrl || undefined"
                                class="aspect-video h-full w-full bg-black"
                                controls
                                playsinline
                                preload="metadata"
                            ></video>

                            <div v-else class="flex aspect-video min-h-[260px] flex-col items-center justify-center gap-6 bg-[radial-gradient(circle_at_center,rgba(255,255,255,0.12),transparent_55%),linear-gradient(135deg,#0f172a,#020617)] p-8 text-center text-white">
                                <div class="flex h-20 w-20 items-center justify-center rounded-full border border-white/20 bg-white/10">
                                    <Play class="ml-1 h-9 w-9" />
                                </div>
                                <div>
                                    <p class="mb-2 text-[9px] font-black uppercase tracking-[0.35em] text-white/60">Video Placeholder</p>
                                    <h3 class="text-2xl font-black uppercase tracking-tight sm:text-4xl">Demo video coming soon</h3>
                                </div>
                                <p class="max-w-lg text-sm leading-relaxed text-white/65">
                                    A guided walkthrough will appear here once the demo video is available.
                                </p>
                            </div>
                        </div>

                        <aside class="border-t border-border/25 p-5 lg:border-l lg:border-t-0 lg:p-6">
                            <p class="mb-4 text-[9px] font-black uppercase tracking-[0.3em] text-primary">What you can explore</p>
                            <div class="space-y-3">
                                <div v-for="item in demoHighlights" :key="item.label" class="flex items-center gap-3 border border-border/25 bg-background/50 p-3">
                                    <component :is="item.icon" class="h-4 w-4 text-primary" />
                                    <span class="text-xs font-black uppercase tracking-widest text-muted-foreground">{{ item.label }}</span>
                                </div>
                            </div>
                            <p class="mt-5 text-sm leading-relaxed text-muted-foreground">
                                Follow the walkthrough from class setup to student progress, assessment feedback, grades, reports, and admin monitoring.
                            </p>
                        </aside>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
