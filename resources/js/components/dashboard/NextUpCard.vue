<script setup lang="ts">
import { computed, onMounted, onBeforeUnmount, ref } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Zap, Clock, ArrowUpRight } from 'lucide-vue-next';

export interface NextUpItem {
    kind: 'exam' | 'assignment';
    title: string;
    dueAt: string; // ISO or parseable date
    href: string;
    meta?: string;
}

interface Props {
    item?: NextUpItem | null;
}

const props = withDefaults(defineProps<Props>(), { item: null });

const now = ref(new Date());
let tickId: number | null = null;

onMounted(() => {
    tickId = window.setInterval(() => {
        now.value = new Date();
    }, 1000);
});

onBeforeUnmount(() => {
    if (tickId !== null) window.clearInterval(tickId);
});

const countdown = computed(() => {
    if (!props.item?.dueAt) return null;
    const due = new Date(props.item.dueAt).getTime();
    if (Number.isNaN(due)) return null;

    const diffMs = due - now.value.getTime();
    const overdue = diffMs < 0;
    const abs = Math.abs(diffMs);

    const days = Math.floor(abs / 86_400_000);
    const hours = Math.floor((abs % 86_400_000) / 3_600_000);
    const minutes = Math.floor((abs % 3_600_000) / 60_000);
    const seconds = Math.floor((abs % 60_000) / 1000);

    let label: string;
    if (days >= 1) label = `${days}d ${hours}h`;
    else if (hours >= 1) label = `${hours}h ${minutes}m`;
    else if (minutes >= 1) label = `${minutes}m ${seconds}s`;
    else label = `${seconds}s`;

    let tone: 'soon' | 'now' | 'overdue' | 'ok' = 'ok';
    if (overdue) tone = 'overdue';
    else if (diffMs < 3_600_000) tone = 'now';
    else if (diffMs < 86_400_000) tone = 'soon';

    return { label, overdue, tone };
});

const toneClasses = computed(() => {
    switch (countdown.value?.tone) {
        case 'overdue':
            return {
                ring: 'border-destructive/40',
                chip: 'bg-destructive/10 text-destructive border-destructive/30',
                badge: 'Overdue',
                glow: 'from-destructive/20',
            };
        case 'now':
            return {
                ring: 'border-amber-500/40',
                chip: 'bg-amber-500/10 text-amber-500 border-amber-500/30',
                badge: 'Imminent',
                glow: 'from-amber-500/20',
            };
        case 'soon':
            return {
                ring: 'border-primary/40',
                chip: 'bg-primary/10 text-primary border-primary/30',
                badge: 'Soon',
                glow: 'from-primary/20',
            };
        default:
            return {
                ring: 'border-sidebar-border/70',
                chip: 'bg-muted/60 text-muted-foreground border-border/40',
                badge: 'Scheduled',
                glow: 'from-primary/10',
            };
    }
});
</script>

<template>
    <section
        v-if="item"
        :class="[
            'surface-card premium-hover relative overflow-hidden p-5',
            toneClasses.ring,
        ]"
        aria-label="Next up"
    >
        <div
            :class="['absolute -right-10 -top-10 w-32 h-32 rounded-full blur-3xl pointer-events-none bg-gradient-to-br to-transparent', toneClasses.glow]"
            aria-hidden="true"
        />

        <div class="relative z-10 flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="text-[10px] font-black uppercase tracking-[0.25em] text-muted-foreground/70">Next Up</p>
                <h3 class="mt-1 text-sm sm:text-base font-bold text-foreground truncate">{{ item.title }}</h3>
                <p v-if="item.meta" class="mt-0.5 text-[11px] text-muted-foreground truncate">{{ item.meta }}</p>
            </div>
            <span :class="['shrink-0 inline-flex items-center gap-1 rounded-full border px-2 py-0.5 text-[9px] font-black uppercase tracking-[0.2em]', toneClasses.chip]">
                <Zap v-if="item.kind === 'exam'" class="h-3 w-3" />
                <Clock v-else class="h-3 w-3" />
                {{ item.kind }}
            </span>
        </div>

        <div class="relative z-10 mt-4 flex items-end justify-between gap-3">
            <div>
                <p class="text-[10px] font-black uppercase tracking-[0.25em] text-muted-foreground/60">{{ toneClasses.badge }}</p>
                <p class="mt-0.5 text-xl sm:text-2xl font-black tracking-tight tabular-nums" :class="countdown?.overdue ? 'text-destructive' : 'text-foreground'">
                    {{ countdown?.label ?? '—' }}
                </p>
            </div>
            <Link
                :href="item.href"
                class="inline-flex items-center gap-1.5 rounded-lg border border-primary/30 bg-primary/10 px-3 py-1.5 text-[10px] font-black uppercase tracking-[0.2em] text-primary transition-all hover:bg-primary hover:text-primary-foreground"
            >
                Open
                <ArrowUpRight class="h-3 w-3" />
            </Link>
        </div>
    </section>
</template>
