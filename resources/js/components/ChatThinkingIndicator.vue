<script setup lang="ts">
const characters = [...'Thinking…'];
</script>

<template>
    <span class="inline-flex" role="status" aria-label="Thinking…">
        <span
            v-for="(character, index) in characters"
            :key="index"
            aria-hidden="true"
            class="inline-block motion-safe:animate-pulse"
            :style="{ animationDelay: `${index * 80 - 2000}ms` }"
            >{{ character }}</span
        >
    </span>
</template>
