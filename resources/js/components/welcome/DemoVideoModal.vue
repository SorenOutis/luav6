<script setup lang="ts">
import { computed } from 'vue';
import {
    BarChart3,
    CheckCircle2,
    ClipboardCheck,
    FileText,
    Film,
    Gamepad2,
    GraduationCap,
    Layers3,
    Play,
    ShieldCheck,
    Sparkles,
    X,
} from 'lucide-vue-next';

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

const demoChapters = [
    {
        time: '01',
        title: 'Class Setup',
        detail: 'Sections, learners, and school levels',
        icon: Layers3,
    },
    {
        time: '02',
        title: 'Assessment Flow',
        detail: 'Activities, exams, checking, and feedback',
        icon: ClipboardCheck,
    },
    {
        time: '03',
        title: 'Student Experience',
        detail: 'Maps, badges, games, and motivation',
        icon: Gamepad2,
    },
    {
        time: '04',
        title: 'Reports & Admin',
        detail: 'Grades, progress views, and controls',
        icon: FileText,
    },
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
            <div
                v-if="open"
                class="fixed inset-0 z-[120] flex items-center justify-center bg-background/85 p-3 backdrop-blur-xl sm:p-5"
                @click.self="emit('close')"
            >
                <div
                    class="relative max-h-[92vh] w-full max-w-6xl overflow-y-auto border border-border/40 bg-card shadow-2xl"
                >
                    <div
                        class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-primary/60 to-transparent"
                    ></div>

                    <div
                        class="flex items-start justify-between gap-4 border-b border-border/25 px-5 py-4 sm:px-6"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center border border-primary/25 bg-primary/10"
                            >
                                <Film class="h-5 w-5 text-primary" />
                            </div>
                            <div>
                                <p
                                    class="text-[8px] font-black tracking-[0.3em] text-primary uppercase"
                                >
                                    System Demo
                                </p>
                                <h2
                                    class="mt-1 text-lg font-black tracking-tight uppercase sm:text-2xl"
                                >
                                    Watch LSI Learning Engine
                                </h2>
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

                    <div class="grid gap-0 xl:grid-cols-[1.48fr_0.52fr]">
                        <div class="border-b border-border/25 xl:border-b-0">
                            <div class="bg-black">
                                <video
                                    v-if="hasVideo"
                                    :src="videoUrl || undefined"
                                    class="aspect-video h-full w-full bg-black"
                                    controls
                                    controlsList="nodownload noplaybackrate"
                                    disablePictureInPicture
                                    playsinline
                                    preload="metadata"
                                    @contextmenu.prevent
                                ></video>

                                <div
                                    v-else
                                    class="relative flex aspect-video min-h-[300px] flex-col items-center justify-center overflow-hidden bg-[radial-gradient(circle_at_center,rgba(245,158,11,0.18),transparent_44%),linear-gradient(135deg,#111827,#020617)] p-8 text-center text-white"
                                >
                                    <div
                                        class="absolute inset-x-8 top-8 flex items-center justify-between opacity-40"
                                    >
                                        <span
                                            class="h-2 w-2 rounded-full bg-emerald-400"
                                        ></span>
                                        <span
                                            class="text-[8px] font-black tracking-[0.4em] uppercase"
                                            >Preview Mode</span
                                        >
                                        <span
                                            class="h-2 w-2 rounded-full bg-amber-400"
                                        ></span>
                                    </div>
                                    <div
                                        class="absolute right-8 bottom-8 left-8 hidden h-16 items-end gap-2 opacity-35 sm:flex"
                                    >
                                        <span
                                            v-for="i in 28"
                                            :key="i"
                                            class="flex-1 rounded-t-sm bg-white/40"
                                            :style="{
                                                height: `${22 + ((i * 19) % 58)}%`,
                                            }"
                                        ></span>
                                    </div>
                                    <div
                                        class="relative z-10 flex h-24 w-24 items-center justify-center rounded-full border border-white/20 bg-white/10 shadow-[0_0_80px_rgba(245,158,11,0.3)]"
                                    >
                                        <div
                                            class="absolute inset-3 rounded-full border border-white/10"
                                        ></div>
                                        <Play class="ml-1 h-10 w-10" />
                                    </div>
                                    <div class="relative z-10 mt-7">
                                        <p
                                            class="mb-2 text-[9px] font-black tracking-[0.35em] text-white/60 uppercase"
                                        >
                                            Guided Preview
                                        </p>
                                        <h3
                                            class="text-2xl font-black tracking-tight uppercase sm:text-4xl"
                                        >
                                            Demo video coming soon
                                        </h3>
                                    </div>
                                    <p
                                        class="relative z-10 mt-4 max-w-lg text-sm leading-relaxed text-white/65"
                                    >
                                        A guided walkthrough will appear here
                                        once the demo video is available.
                                    </p>
                                </div>
                            </div>

                            <div
                                class="grid gap-px bg-border/20 sm:grid-cols-4"
                            >
                                <div
                                    v-for="item in demoChapters"
                                    :key="item.title"
                                    class="bg-background p-4"
                                >
                                    <div
                                        class="mb-3 flex items-center justify-between gap-3"
                                    >
                                        <span
                                            class="text-[9px] font-black tracking-[0.25em] text-primary uppercase"
                                            >{{ item.time }}</span
                                        >
                                        <component
                                            :is="item.icon"
                                            class="h-4 w-4 text-muted-foreground"
                                        />
                                    </div>
                                    <h3
                                        class="text-xs font-black tracking-widest uppercase"
                                    >
                                        {{ item.title }}
                                    </h3>
                                    <p
                                        class="mt-1 text-xs leading-relaxed text-muted-foreground"
                                    >
                                        {{ item.detail }}
                                    </p>
                                </div>
                            </div>
                        </div>

                        <aside
                            class="p-5 xl:border-l xl:border-border/25 xl:p-6"
                        >
                            <div
                                class="mb-5 border border-primary/20 bg-primary/5 p-4"
                            >
                                <div class="mb-3 flex items-center gap-2">
                                    <Sparkles class="h-4 w-4 text-primary" />
                                    <p
                                        class="text-[9px] font-black tracking-[0.3em] text-primary uppercase"
                                    >
                                        What you can explore
                                    </p>
                                </div>
                                <p
                                    class="text-sm leading-relaxed text-muted-foreground"
                                >
                                    Follow the walkthrough from classroom setup
                                    to learner outcomes, feedback, grades,
                                    reports, and operational controls.
                                </p>
                            </div>

                            <div class="space-y-3">
                                <div
                                    v-for="item in demoHighlights"
                                    :key="item.label"
                                    class="flex items-center gap-3 border border-border/25 bg-background/50 p-3"
                                >
                                    <component
                                        :is="item.icon"
                                        class="h-4 w-4 text-primary"
                                    />
                                    <span
                                        class="text-xs font-black tracking-widest text-muted-foreground uppercase"
                                        >{{ item.label }}</span
                                    >
                                </div>
                            </div>

                            <div
                                class="mt-5 space-y-3 border-t border-border/20 pt-5"
                            >
                                <div class="flex items-start gap-3">
                                    <CheckCircle2
                                        class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500"
                                    />
                                    <p
                                        class="text-sm leading-relaxed text-muted-foreground"
                                    >
                                        See the same core flow a teacher or
                                        school admin would use daily.
                                    </p>
                                </div>
                                <div class="flex items-start gap-3">
                                    <CheckCircle2
                                        class="mt-0.5 h-4 w-4 shrink-0 text-emerald-500"
                                    />
                                    <p
                                        class="text-sm leading-relaxed text-muted-foreground"
                                    >
                                        Uploaded admin videos replace this
                                        preview automatically.
                                    </p>
                                </div>
                            </div>
                        </aside>
                    </div>

                    <div
                        class="flex flex-col gap-3 border-t border-border/25 bg-muted/20 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6"
                    >
                        <p
                            class="text-xs font-bold tracking-[0.2em] text-muted-foreground uppercase"
                        >
                            Want to try it live? Login or create an account from
                            the welcome page.
                        </p>
                        <button
                            type="button"
                            class="inline-flex items-center justify-center gap-2 border border-border/40 bg-background px-4 py-2.5 text-[10px] font-black tracking-[0.25em] uppercase transition-colors hover:border-primary/40 hover:text-primary"
                            @click="emit('close')"
                        >
                            Close Preview
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>
