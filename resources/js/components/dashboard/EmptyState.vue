<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { Sparkles } from 'lucide-vue-next';
import type { Component } from 'vue';

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
            'flex flex-col items-center justify-center rounded-2xl border border-dashed border-primary/20 bg-primary/[0.04] text-center backdrop-blur-sm',
            compact ? 'gap-2 px-4 py-6' : 'gap-3 px-6 py-10 sm:py-14',
        ]"
        role="status"
    >
        <div
            :class="[
                'relative flex items-center justify-center rounded-full border border-primary/20 bg-primary/10 text-primary',
                compact ? 'h-10 w-10' : 'h-14 w-14',
            ]"
        >
            <span
                class="absolute inset-0 animate-ping rounded-full bg-primary/20 opacity-20"
                aria-hidden="true"
            />
            <component
                :is="props.icon ?? Sparkles"
                :class="compact ? 'h-4 w-4' : 'h-5 w-5'"
            />
        </div>
        <h4
            :class="[
                'font-black tracking-[0.2em] uppercase',
                compact ? 'text-[10px]' : 'text-xs sm:text-sm',
            ]"
        >
            {{ title }}
        </h4>
        <p
            :class="[
                'max-w-[40ch] text-muted-foreground/80',
                compact
                    ? 'text-[10px] leading-snug'
                    : 'text-xs leading-relaxed sm:text-[13px]',
            ]"
        >
            {{ message }}
        </p>

        <Link
            v-if="ctaLabel && ctaHref"
            :href="ctaHref"
            :class="[
                'mt-1 inline-flex items-center justify-center rounded-lg border border-primary/30 bg-primary/10 px-4 py-1.5 font-black tracking-[0.2em] text-primary uppercase transition-all duration-300 hover:bg-primary hover:text-primary-foreground',
                compact ? 'text-[9px]' : 'text-[10px]',
            ]"
        >
            {{ ctaLabel }}
        </Link>
    </div>
</template>
