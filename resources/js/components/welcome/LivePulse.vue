<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount, computed } from 'vue';
import { Activity } from 'lucide-vue-next';
import gsap from 'gsap';

const liveSignals = ref([
    {
        label: 'Essay Review Speed',
        value: 92,
        targetValue: 92,
        valueLabel: 'Fast',
        color: 'primary',
    },
    {
        label: 'Rubric Match Confidence',
        value: 96,
        targetValue: 96,
        valueLabel: '96%',
        color: 'emerald',
    },
    {
        label: 'Feedback Availability',
        value: 100,
        targetValue: 100,
        valueLabel: 'Live',
        color: 'primary',
    },
]);

let signalInterval: ReturnType<typeof setInterval> | null = null;

const updateLiveSignals = () => {
    liveSignals.value.forEach((signal) => {
        const jitter = (Math.random() - 0.5) * 4;
        const newValue = Math.max(
            85,
            Math.min(100, signal.targetValue + jitter),
        );

        gsap.to(signal, {
            value: newValue,
            duration: 1.5,
            ease: 'sine.inOut',
        });
    });
};

const pulseBars = computed(() => {
    return Array.from({ length: 8 }, (_, i) => ({
        id: i,
        height: 40 + ((Math.sin(i * 1.2) + 1) / 2) * 60,
    }));
});

onMounted(() => {
    signalInterval = setInterval(updateLiveSignals, 3000);

    // Waveform Animation
    gsap.utils.toArray('.pulse-waveform-bar').forEach((bar: any, i: number) => {
        gsap.fromTo(
            bar,
            {
                scaleY: 0.4,
                opacity: 0.3,
                transformOrigin: 'bottom',
            },
            {
                scaleY: 1.2,
                opacity: 1,
                duration: 0.8 + Math.random() * 0.7,
                repeat: -1,
                yoyo: true,
                ease: 'sine.inOut',
                delay: i * 0.1,
            },
        );
    });
});

onBeforeUnmount(() => {
    if (signalInterval) {
        clearInterval(signalInterval);
    }
});
</script>

<template>
    <section
        class="pulse-panel gradient-border group/pulse relative overflow-hidden rounded-2xl border border-border/30 bg-card/60 p-6 shadow-[0_20px_80px_-30px_rgba(0,0,0,0.15)] backdrop-blur-2xl sm:p-8 lg:p-10 dark:border-border/15 dark:bg-background/50 dark:shadow-[0_20px_80px_-45px_rgba(0,0,0,0.45)]"
    >
        <div
            class="pointer-events-none absolute inset-0 z-10 overflow-hidden rounded-2xl"
        >
            <div
                class="absolute inset-x-0 -top-[100px] h-[100px] animate-[scan-vertical_4s_linear_infinite] bg-gradient-to-b from-primary/10 to-transparent"
            ></div>
        </div>

        <div
            class="relative z-20 flex flex-col justify-between gap-6 sm:flex-row sm:items-center"
        >
            <div>
                <div class="mb-2 flex items-center gap-2">
                    <Activity class="h-3 w-3 animate-pulse text-primary" />
                    <p
                        class="text-[10px] font-black tracking-[0.25em] text-primary/80 uppercase"
                    >
                        Essay Diagnostics
                    </p>
                </div>
                <h2
                    class="text-2xl leading-none font-black tracking-tight uppercase italic sm:text-3xl"
                >
                    AI Review Pulse
                </h2>
            </div>

            <div class="flex items-center gap-6">
                <div
                    class="hidden h-10 w-24 items-end gap-1 rounded border border-border/20 bg-muted/20 px-2 py-1 sm:flex"
                >
                    <div
                        v-for="bar in pulseBars"
                        :key="bar.id"
                        class="pulse-waveform-bar w-full origin-bottom rounded-t-sm bg-primary/40 will-change-transform"
                        :style="{
                            height: bar.height + '%',
                        }"
                    ></div>
                </div>
                <div
                    class="online-pill flex items-center gap-2 rounded-full border border-emerald-500/20 bg-emerald-500/5 px-4 py-2 text-[9px] font-black tracking-[0.22em] text-emerald-600 uppercase shadow-[0_0_15px_rgba(16,185,129,0.1)] dark:text-emerald-400"
                >
                    <span
                        class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500 shadow-[0_0_8px_rgba(16,185,129,0.8)]"
                    ></span>
                    Operational
                </div>
            </div>
        </div>

        <div class="mt-10 grid gap-4">
            <div
                v-for="signal in liveSignals"
                :key="signal.label"
                @click="updateLiveSignals"
                class="pulse-row group/row relative cursor-pointer overflow-hidden rounded-xl border border-border/20 bg-muted/20 p-4 transition-all hover:bg-muted/40 sm:p-5 dark:border-border/10 dark:bg-foreground/[0.03] dark:hover:bg-foreground/[0.06]"
            >
                <div
                    class="absolute inset-0 bg-primary/5 opacity-0 transition-opacity group-active/row:opacity-100"
                ></div>

                <div
                    class="relative z-10 mb-3 flex items-center justify-between gap-4"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="h-1.5 w-1.5 rounded-full"
                            :class="
                                signal.color === 'emerald'
                                    ? 'bg-emerald-500'
                                    : 'bg-primary'
                            "
                        ></div>
                        <span
                            class="text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase transition-colors group-hover/row:text-foreground sm:text-xs"
                            >{{ signal.label }}</span
                        >
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="font-mono text-[10px] font-black tracking-wider text-foreground sm:text-xs"
                            >{{ Math.round(signal.value) }}%</span
                        >
                        <span
                            class="rounded border border-border/30 bg-background/50 px-1.5 py-0.5 text-[8px] font-bold tracking-widest text-muted-foreground uppercase"
                            >{{ signal.valueLabel }}</span
                        >
                    </div>
                </div>

                <div
                    class="relative h-1.5 w-full overflow-hidden rounded-full bg-muted/50 dark:bg-foreground/10"
                >
                    <div
                        class="signal-fill absolute inset-y-0 left-0 rounded-full transition-all duration-700 ease-out"
                        :class="
                            signal.color === 'emerald'
                                ? 'bg-emerald-500'
                                : 'bg-primary'
                        "
                        :style="{ width: `${signal.value}%` }"
                    >
                        <div
                            class="absolute inset-0 w-20 -translate-x-full animate-[scan-horizontal_2s_linear_infinite] bg-gradient-to-r from-transparent via-white/40 to-transparent"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
