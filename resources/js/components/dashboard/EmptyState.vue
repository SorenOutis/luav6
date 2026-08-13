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
            'flex flex-col items-center justify-center rounded-[1.25rem] border border-dashed border-border/60 bg-muted/30 text-center',
            compact ? 'gap-2 px-4 py-6' : 'gap-3 px-6 py-10 sm:py-14',
        ]"
        role="status"
    >
        <div
            :class="[
                'flex items-center justify-center rounded-full bg-muted text-muted-foreground',
                compact ? 'h-10 w-10' : 'h-14 w-14',
            ]"
        >
            <component
                :is="props.icon ?? Sparkles"
                :class="compact ? 'h-4 w-4' : 'h-5 w-5'"
            />
        </div>
        <h4
            :class="[
                'font-semibold tracking-tight text-foreground',
                compact ? 'text-[15px]' : 'text-[17px]',
            ]"
        >
            {{ title }}
        </h4>
        <p
            :class="[
                'max-w-[40ch] text-muted-foreground',
                compact
                    ? 'text-[13px] leading-snug'
                    : 'text-[15px] leading-relaxed',
            ]"
        >
            {{ message }}
        </p>

        <Link
            v-if="ctaLabel && ctaHref"
            :href="ctaHref"
            :class="[
                'dash-btn mt-1 inline-flex items-center justify-center bg-[#007AFF] px-5 text-white',
                compact ? 'text-[14px]' : 'text-[15px]',
            ]"
        >
            {{ ctaLabel }}
        </Link>
    </div>
</template>
