<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import {
    Card,
    CardContent,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { home } from '@/routes';

defineProps<{
    title?: string;
    description?: string;
}>();
</script>

<template>
    <div
        class="theme-neutral-page flex min-h-svh flex-col items-center justify-center gap-6 bg-muted p-6 md:p-10"
    >
        <div class="flex w-full max-w-md flex-col gap-6">
            <Link
                :href="home()"
                class="flex items-center gap-2 self-center font-medium"
            >
                <div class="flex h-9 w-9 items-center justify-center">
                    <AppLogoIcon
                        class="size-9 fill-current text-foreground"
                    />
                </div>
            </Link>

            <div class="flex flex-col gap-6">
                <Card class="rounded-xl py-0">
                    <CardHeader
                        v-if="title || description"
                        class="px-6 pt-8 pb-0 text-center sm:px-10"
                    >
                        <CardTitle class="text-xl">{{ title }}</CardTitle>
                        <CardDescription>
                            {{ description }}
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="px-6 py-8 sm:px-10">
                        <slot />
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
