<script setup lang="ts">
import type { Component } from 'vue';
import { Link } from '@inertiajs/vue3';
import { Sparkles } from 'lucide-vue-next';

interface Props {
    icon?: Component;
    title: string;
    message: string;
    ctaLabel?: string;
    ctaHref?: string;
    compact?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    icon: undefined,
    ctaLabel: undefined,
    ctaHref: undefined,
    compact: false,
});
</script>

<template>
    <div
        :class="[
            'flex flex-col items-center justify-center text-center rounded-2xl border border-dashed border-primary/20 bg-primary/[0.04] backdrop-blur-sm',
            compact ? 'px-4 py-6 gap-2' : 'px-6 py-10 sm:py-14 gap-3',
        ]"
        role="status"
    >
        <div
            :class="[
                'relative rounded-full border border-primary/20 bg-primary/10 flex items-center justify-center text-primary',
                compact ? 'h-10 w-10' : 'h-14 w-14',
            ]"
        >
            <span class="absolute inset-0 rounded-full bg-primary/20 animate-ping opacity-20" aria-hidden="true" />
            <component :is="props.icon ?? Sparkles" :class="compact ? 'h-4 w-4' : 'h-5 w-5'" />
        </div>
        <h4
            :class="[
                'font-black uppercase tracking-[0.2em]',
                compact ? 'text-[10px]' : 'text-xs sm:text-sm',
            ]"
        >
            {{ title }}
        </h4>
        <p
            :class="[
                'text-muted-foreground/80 max-w-[40ch]',
                compact ? 'text-[10px] leading-snug' : 'text-xs sm:text-[13px] leading-relaxed',
            ]"
        >
            {{ message }}
        </p>

        <Link
            v-if="ctaLabel && ctaHref"
            :href="ctaHref"
            :class="[
                'mt-1 inline-flex items-center justify-center rounded-lg border border-primary/30 bg-primary/10 px-4 py-1.5 font-black uppercase tracking-[0.2em] text-primary transition-all duration-300 hover:bg-primary hover:text-primary-foreground',
                compact ? 'text-[9px]' : 'text-[10px]',
            ]"
        >
            {{ ctaLabel }}
        </Link>
    </div>
</template>
