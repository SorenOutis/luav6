<script setup lang="ts">
import type { HTMLAttributes } from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';

withDefaults(
    defineProps<{
        logoUrl?: string | null;
        size?: 'header' | 'mobile' | 'loader';
        className?: HTMLAttributes['class'];
    }>(),
    {
        logoUrl: null,
        size: 'header',
        className: undefined,
    },
);
</script>

<template>
    <div
        data-test="public-brand-mark"
        :class="[
            'flex items-center text-foreground',
            size === 'mobile' ? 'gap-2.5' : 'gap-3',
            size === 'loader' ? 'gap-3.5' : '',
            className,
        ]"
    >
        <img
            v-if="logoUrl"
            :src="logoUrl"
            alt="KOAMISHIN logo"
            :class="[
                'shrink-0 object-contain',
                size === 'mobile' ? 'h-7 w-7' : '',
                size === 'header' ? 'h-8 w-8' : '',
                size === 'loader' ? 'h-9 w-9' : '',
            ]"
        />
        <AppLogoIcon
            v-else
            aria-hidden="true"
            :class="[
                'shrink-0',
                size === 'mobile' ? 'h-7 w-7' : '',
                size === 'header' ? 'h-8 w-8' : '',
                size === 'loader' ? 'h-9 w-9' : '',
            ]"
        />
        <span
            :class="[
                'tracking-[-0.03em] whitespace-nowrap text-foreground',
                size === 'mobile' ? 'font-serif text-lg' : '',
                size === 'header' ? 'font-serif text-xl' : '',
                size === 'loader' ? 'font-serif text-xl sm:text-2xl' : '',
            ]"
        >
            KOAMISHIN
        </span>
    </div>
</template>
