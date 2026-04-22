<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, nextTick, TransitionGroup } from 'vue';
import { Terminal } from 'lucide-vue-next';

const terminalLines = ref<{ id: number; time: string; module: string; message: string; displayText: string; isTyping: boolean; type: 'info' | 'success' | 'warn' }[]>([]);
const terminalPaused = ref(false);
const terminalEl = ref<HTMLElement | null>(null);
let terminalInterval: ReturnType<typeof setInterval> | null = null;
let terminalLineId = 0;
let isProcessingQueue = false;
const terminalQueue: any[] = [];

const terminalPool = [
    { module: 'EVAL_ENGINE', message: 'Assessment batch processed — 12 submissions graded', type: 'success' as const },
    { module: 'SYNC', message: 'Leaderboard XP recalculated for Section A', type: 'info' as const },
    { module: 'AI_GRADE', message: 'Essay scoring model loaded — avg confidence 94.2%', type: 'success' as const },
    { module: 'AUTH', message: 'Session token refreshed for 3 active nodes', type: 'info' as const },
    { module: 'EXAM_SVC', message: 'Timed exam #2847 finalized — results dispatched', type: 'success' as const },
    { module: 'STREAK_SVC', message: 'Daily streak bonus applied to 18 learners', type: 'success' as const },
    { module: 'WARN', message: 'Idle session detected — initiating heartbeat probe', type: 'warn' as const },
    { module: 'DB_POOL', message: 'Connection pool rebalanced — latency nominal', type: 'info' as const },
    { module: 'ASSIGN_SVC', message: 'Deadline alert dispatched — 6 pending submissions', type: 'warn' as const },
    { module: 'CACHE', message: 'Leaderboard snapshot cached — TTL 60s', type: 'info' as const },
    { module: 'QUEUE', message: 'Job #9912 completed — 0 failures in batch', type: 'success' as const },
    { module: 'SCORING', message: 'Rubric v3.1 applied to Section B exam submissions', type: 'success' as const },
    { module: 'MONITOR', message: 'System integrity check passed — all services healthy', type: 'success' as const },
    { module: 'AI_GRADE', message: 'Short-answer NLP model inference — 7ms avg latency', type: 'info' as const },
    { module: 'USER_SVC', message: 'New learner node registered — profile initialized', type: 'success' as const },
];

const getTerminalTime = () => {
    const now = new Date();
    return `${String(now.getHours()).padStart(2,'0')}:${String(now.getMinutes()).padStart(2,'0')}:${String(now.getSeconds()).padStart(2,'0')}`;
};

const processTerminalQueue = async () => {
    if (isProcessingQueue || terminalQueue.length === 0) return;
    
    if (terminalPaused.value) {
        setTimeout(processTerminalQueue, 500);
        return;
    }
    
    isProcessingQueue = true;
    const entry = terminalQueue.shift();
    
    const newLine = { 
        id: terminalLineId++, 
        time: getTerminalTime(), 
        ...entry, 
        displayText: '', 
        isTyping: true 
    };
    
    terminalLines.value.push(newLine);
    
    if (terminalLines.value.length > 7) {
        terminalLines.value.shift();
    }

    const message = entry.message;
    for (let i = 0; i < message.length; i++) {
        if (terminalLineId === 0) break; 

        const activeProxy = terminalLines.value.find(l => l.id === newLine.id);
        if (activeProxy) {
            activeProxy.displayText += message[i];
        }
        
        let delay = 20 + Math.random() * 50;
        if ([' ', ',', '.', '—'].includes(message[i])) delay += 60 + Math.random() * 40;
        
        await new Promise(resolve => setTimeout(resolve, delay));
        
        nextTick(() => {
            if (terminalEl.value) {
                terminalEl.value.scrollTop = terminalEl.value.scrollHeight;
            }
        });
    }

    const finalProxy = terminalLines.value.find(l => l.id === newLine.id);
    if (finalProxy) {
        finalProxy.isTyping = false;
    }
    isProcessingQueue = false;
    
    const nextLineDelay = 1000 + Math.random() * 1500;
    setTimeout(processTerminalQueue, nextLineDelay);
};

const pushTerminalLine = () => {
    const entry = terminalPool[Math.floor(Math.random() * terminalPool.length)];
    terminalQueue.push(entry);
    if (!isProcessingQueue) processTerminalQueue();
};

const startTerminal = () => {
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

onMounted(() => {
    startTerminal();
});

onBeforeUnmount(() => {
    if (terminalInterval) {
        clearInterval(terminalInterval);
        terminalInterval = null;
    }
    terminalLineId = 0;
    isProcessingQueue = false;
    terminalQueue.length = 0;
});
</script>

<template>
    <div class="reveal-section mt-8 lg:mt-12 relative">
        <div
            class="terminal-panel relative overflow-hidden rounded-xl border border-border/30 dark:border-border/20 bg-card dark:bg-[#050507] backdrop-blur-xl"
            @mouseenter="terminalPaused = true"
            @mouseleave="terminalPaused = false"
        >
            <div class="scanline absolute inset-0 pointer-events-none opacity-[0.04] z-10"></div>

            <div class="flex items-center justify-between px-4 py-2.5 border-b border-border/10 dark:border-white/5">
                <div class="flex items-center gap-2">
                    <div class="flex gap-1.5">
                        <div class="h-2.5 w-2.5 rounded-full bg-red-500/70"></div>
                        <div class="h-2.5 w-2.5 rounded-full bg-yellow-500/70"></div>
                        <div class="h-2.5 w-2.5 rounded-full bg-emerald-500/70"></div>
                    </div>
                    <div class="flex items-center gap-2 ml-3">
                        <Terminal class="h-3 w-3 text-primary/60 dark:text-emerald-400/60" />
                        <span class="text-[9px] font-black uppercase tracking-[0.3em] text-muted-foreground/60 dark:text-white/30">LSI_SYSLOG — LIVE STREAM</span>
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
                        <span v-if="terminalPaused" class="text-[8px] font-black uppercase tracking-widest text-yellow-500/70 dark:text-yellow-400/70 mr-1">STREAM PAUSED</span>
                    </Transition>
                    <div class="h-1.5 w-1.5 rounded-full animate-pulse" :class="terminalPaused ? 'bg-yellow-500/70 dark:bg-yellow-400/70' : 'bg-emerald-500 dark:bg-emerald-400'"></div>
                </div>
            </div>

            <div ref="terminalEl" class="terminal-body overflow-hidden px-4 py-3 space-y-1.5 max-h-[160px] lg:max-h-[140px]">
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
                        class="flex items-start gap-2 sm:gap-3 font-mono text-[10px] sm:text-xs leading-relaxed"
                        :class="{ 'opacity-40': idx < terminalLines.length - 5 }"
                    >
                        <span class="text-muted-foreground/40 dark:text-white/25 shrink-0 tabular-nums">{{ line.time }}</span>
                        <span class="shrink-0 px-1.5 py-0.5 rounded text-[8px] font-black uppercase tracking-widest"
                            :class="{
                                'bg-emerald-500/15 text-emerald-600 dark:text-emerald-400': line.type === 'success',
                                'bg-blue-500/15 text-blue-600 dark:text-blue-400': line.type === 'info',
                                'bg-yellow-500/15 text-yellow-600 dark:text-yellow-400': line.type === 'warn',
                            }">{{ line.module }}</span>
                        <span class="text-foreground/70 dark:text-white/50 leading-relaxed break-all sm:break-normal">
                            {{ line.displayText }}
                            <span v-if="line.isTyping" class="inline-block w-1 h-3 bg-emerald-500/70 dark:bg-emerald-400/70 ml-0.5 animate-pulse"></span>
                        </span>
                    </div>
                </TransitionGroup>
                <div class="flex items-center gap-2 font-mono text-xs">
                    <span class="text-primary/40 dark:text-emerald-400/60">$</span>
                    <span class="h-3.5 w-1.5 bg-primary/40 dark:bg-emerald-400/70 animate-[pulse_1s_ease-in-out_infinite]"></span>
                </div>
            </div>
        </div>
    </div>
</template>
