<script setup lang="ts">
import { MessagesSquare } from 'lucide-vue-next';
</script>

<template>
    <div class="flex justify-center" data-test="community-app-badge">
        <span
            class="inline-flex items-center gap-1.5 rounded-full border border-brand/30 bg-brand/10 px-3 py-1 text-[11px] font-semibold tracking-wide text-brand uppercase"
        >
            <MessagesSquare class="h-3.5 w-3.5" aria-hidden="true" />
            Community Lounge
        </span>
    </div>
</template>
