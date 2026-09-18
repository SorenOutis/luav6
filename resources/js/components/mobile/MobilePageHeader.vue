<script setup lang="ts">
interface Props {
    title: string;
    subtitle?: string;
    eyebrow?: string;
}

withDefaults(defineProps<Props>(), {
    subtitle: '',
    eyebrow: '',
});
</script>

<template>
    <header class="mobile-page-header md:hidden">
        <div v-if="eyebrow" class="mobile-page-header__eyebrow">
            {{ eyebrow }}
        </div>
        <h1 class="mobile-page-header__title">{{ title }}</h1>
        <p v-if="subtitle" class="mobile-page-header__subtitle">
            {{ subtitle }}
        </p>
    </header>
</template>
