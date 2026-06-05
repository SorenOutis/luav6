<script setup lang="ts">
import { ref, onBeforeUnmount, nextTick, TransitionGroup, watch } from 'vue';
import { Terminal } from 'lucide-vue-next';

const props = withDefaults(
    defineProps<{
        active?: boolean;
    }>(),
    {
        active: true,
    },
);

const terminalLines = ref<
    {
        id: number;
        time: string;
        module: string;
        message: string;
        displayText: string;
        isTyping: boolean;
        type: 'info' | 'success' | 'warn';
    }[]
>([]);
const terminalPaused = ref(false);
const terminalEl = ref<HTMLElement | null>(null);
let terminalInterval: ReturnType<typeof setInterval> | null = null;
let queueTimeout: ReturnType<typeof setTimeout> | null = null;
let terminalLineId = 0;
let isProcessingQueue = false;
let hasStarted = false;
const terminalQueue: any[] = [];

const terminalPool = [
    {
        module: 'ASSESSMENT',
        message: 'Assessment batch processed — 12 learner submissions reviewed',
        type: 'success' as const,
    },
    {
        module: 'INSIGHTS',
        message: 'Progress trends refreshed — weak skill clusters detected',
        type: 'info' as const,
    },
    {
        module: 'AI_REVIEW',
        message:
            'Rubric-aligned scoring engine loaded — confidence within threshold',
        type: 'success' as const,
    },
    {
        module: 'SESSION',
        message: 'Active classroom session synced — learner activity updated',
        type: 'info' as const,
    },
    {
        module: 'RESULTS',
        message: 'Timed assessment finalized — results and feedback released',
        type: 'success' as const,
    },
    {
        module: 'FEEDBACK',
        message: 'Targeted feedback generated — next-step suggestions prepared',
        type: 'success' as const,
    },
    {
        module: 'NOTICE',
        message: 'Pending submissions detected — teacher review queue updated',
        type: 'warn' as const,
    },
    {
        module: 'ANALYTICS',
        message:
            'Performance dashboard recalculated — mastery indicators refreshed',
        type: 'info' as const,
    },
    {
        module: 'ASSIGNMENT',
        message: 'Assignment checkpoint reached — submission status tracked',
        type: 'warn' as const,
    },
    {
        module: 'CACHE',
        message: 'Learning snapshot cached — analytics ready for inspection',
        type: 'info' as const,
    },
    {
        module: 'QUEUE',
        message:
            'Background evaluation jobs completed — no failed tasks reported',
        type: 'success' as const,
    },
    {
        module: 'SCORING',
        message:
            'Rubric criteria applied — explanation trace attached to results',
        type: 'success' as const,
    },
    {
        module: 'MONITOR',
        message:
            'Learning services stable — assessment and feedback flows healthy',
        type: 'success' as const,
    },
    {
        module: 'AI_REVIEW',
        message:
            'Short-answer analysis completed — response intent matched successfully',
        type: 'info' as const,
    },
    {
        module: 'PROFILE',
        message:
            'New learner profile initialized — baseline progress tracking enabled',
        type: 'success' as const,
    },
];

const getTerminalTime = () => {
    const now = new Date();
    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}:${String(now.getSeconds()).padStart(2, '0')}`;
};

const processTerminalQueue = async () => {
    if (isProcessingQueue || terminalQueue.length === 0) return;

    if (terminalPaused.value) {
        queueTimeout = setTimeout(processTerminalQueue, 500);
        return;
    }

    isProcessingQueue = true;
    const entry = terminalQueue.shift();

    const newLine = {
        id: terminalLineId++,
        time: getTerminalTime(),
        ...entry,
        displayText: '',
        isTyping: true,
    };

    terminalLines.value.push(newLine);

    if (terminalLines.value.length > 7) {
        terminalLines.value.shift();
    }

    const message = entry.message;
    for (let i = 0; i < message.length; i++) {
        if (terminalLineId === 0) break;

        const activeProxy = terminalLines.value.find(
            (l) => l.id === newLine.id,
        );
        if (activeProxy) {
            activeProxy.displayText += message[i];
        }

        let delay = 20 + Math.random() * 50;
        if ([' ', ',', '.', '—'].includes(message[i]))
            delay += 60 + Math.random() * 40;

        await new Promise((resolve) => setTimeout(resolve, delay));

        nextTick(() => {
            if (terminalEl.value) {
                terminalEl.value.scrollTop = terminalEl.value.scrollHeight;
            }
        });
    }

    const finalProxy = terminalLines.value.find((l) => l.id === newLine.id);
    if (finalProxy) {
        finalProxy.isTyping = false;
    }
    isProcessingQueue = false;

    const nextLineDelay = 1000 + Math.random() * 1500;
    queueTimeout = setTimeout(processTerminalQueue, nextLineDelay);
};

const pushTerminalLine = () => {
    const entry = terminalPool[Math.floor(Math.random() * terminalPool.length)];
    terminalQueue.push(entry);
    if (!isProcessingQueue) processTerminalQueue();
};

const startTerminal = () => {
    if (hasStarted) return;
    hasStarted = true;
    terminalQueue.push(terminalPool[0]);
    terminalQueue.push(terminalPool[1]);
    terminalQueue.push(terminalPool[2]);
    processTerminalQueue();
    terminalInterval = setInterval(() => {
        if (terminalQueue.length < 5) {
            pushTerminalLine();
        }
    }, 6000);
};

watch(
    () => props.active,
    (active) => {
        if (active) startTerminal();
    },
    { immediate: true },
);

onBeforeUnmount(() => {
    if (terminalInterval) {
        clearInterval(terminalInterval);
        terminalInterval = null;
    }
    if (queueTimeout) {
        clearTimeout(queueTimeout);
        queueTimeout = null;
    }
    terminalLineId = 0;
    isProcessingQueue = false;
    terminalQueue.length = 0;
});
</script>

<template>
    <div class="reveal-section relative mt-8 lg:mt-12">
        <div
            class="terminal-panel relative overflow-hidden rounded-xl border border-border/30 bg-card backdrop-blur-xl dark:border-border/20 dark:bg-[#050507]"
            @mouseenter="terminalPaused = true"
            @mouseleave="terminalPaused = false"
        >
            <div
                class="scanline pointer-events-none absolute inset-0 z-10 opacity-[0.04]"
            ></div>

            <div
                class="flex items-center justify-between border-b border-border/10 px-4 py-2.5 dark:border-white/5"
            >
                <div class="flex items-center gap-2">
                    <div class="flex gap-1.5">
                        <div
                            class="h-2.5 w-2.5 rounded-full bg-red-500/70"
                        ></div>
                        <div
                            class="h-2.5 w-2.5 rounded-full bg-yellow-500/70"
                        ></div>
                        <div
                            class="h-2.5 w-2.5 rounded-full bg-emerald-500/70"
                        ></div>
                    </div>
                    <div class="ml-3 flex items-center gap-2">
                        <Terminal
                            class="h-3 w-3 text-primary/60 dark:text-emerald-400/60"
                        />
                        <span
                            class="text-[9px] font-black tracking-[0.3em] text-muted-foreground/60 uppercase dark:text-white/30"
                            >LEARNING_INTEL — LIVE STREAM</span
                        >
                    </div>
                </div>
                <div class="flex items-center gap-2">
                    <Transition
                        enter-active-class="transition-opacity duration-300"
                        enter-from-class="opacity-0"
                        enter-to-class="opacity-100"
                        leave-active-class="transition-opacity duration-200"
                        leave-from-class="opacity-100"
                        leave-to-class="opacity-0"
                    >
                        <span
                            v-if="terminalPaused"
                            class="mr-1 text-[8px] font-black tracking-widest text-yellow-500/70 uppercase dark:text-yellow-400/70"
                            >STREAM PAUSED</span
                        >
                    </Transition>
                    <div
                        class="h-1.5 w-1.5 animate-pulse rounded-full"
                        :class="
                            terminalPaused
                                ? 'bg-yellow-500/70 dark:bg-yellow-400/70'
                                : 'bg-emerald-500 dark:bg-emerald-400'
                        "
                    ></div>
                </div>
            </div>

            <div
                ref="terminalEl"
                class="terminal-body max-h-[160px] space-y-1.5 overflow-hidden px-4 py-3 lg:max-h-[140px]"
            >
                <TransitionGroup
                    enter-active-class="transition-[opacity,transform] duration-500 ease-out"
                    enter-from-class="opacity-0 -translate-y-2"
                    enter-to-class="opacity-100 translate-y-0"
                    leave-active-class="transition-[opacity,transform] duration-300 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                    tag="div"
                    class="space-y-1.5"
                >
                    <div
                        v-for="(line, idx) in terminalLines"
                        :key="line.id"
                        class="flex items-start gap-2 font-mono text-[10px] leading-relaxed sm:gap-3 sm:text-xs"
                        :class="{
                            'opacity-40': idx < terminalLines.length - 5,
                        }"
                    >
                        <span
                            class="shrink-0 text-muted-foreground/40 tabular-nums dark:text-white/25"
                            >{{ line.time }}</span
                        >
                        <span
                            class="shrink-0 rounded px-1.5 py-0.5 text-[8px] font-black tracking-widest uppercase"
                            :class="{
                                'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400':
                                    line.type === 'success',
                                'bg-blue-500/15 text-blue-600 dark:text-blue-400':
                                    line.type === 'info',
                                'bg-yellow-500/15 text-yellow-600 dark:text-yellow-400':
                                    line.type === 'warn',
                            }"
                            >{{ line.module }}</span
                        >
                        <span
                            class="leading-relaxed break-all text-foreground/70 sm:break-normal dark:text-white/50"
                        >
                            {{ line.displayText }}
                            <span
                                v-if="line.isTyping"
                                class="ml-0.5 inline-block h-3 w-1 animate-pulse bg-emerald-500/70 dark:bg-emerald-400/70"
                            ></span>
                        </span>
                    </div>
                </TransitionGroup>
                <div class="flex items-center gap-2 font-mono text-xs">
                    <span class="text-primary/40 dark:text-emerald-400/60"
                        >$</span
                    >
                    <span
                        class="h-3.5 w-1.5 animate-[pulse_1s_ease-in-out_infinite] bg-primary/40 dark:bg-emerald-400/70"
                    ></span>
                </div>
            </div>
        </div>
    </div>
</template>
