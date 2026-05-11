<script setup lang="ts">
import { Head, Link } from '@inertiajs/vue3';
import { LockKeyhole, ArrowLeft } from 'lucide-vue-next';
import AppLayout from '@/layouts/AppLayout.vue';

defineProps<{
    pageTitle: string;
    message: string;
}>();
</script>

<template>
    <Head :title="`${pageTitle} Unavailable`" />

    <AppLayout :breadcrumbs="[{ title: pageTitle, href: '#' }]">
        <div class="flex min-h-[70vh] items-center justify-center px-6 py-16">
            <section class="w-full max-w-xl rounded-lg border border-border bg-card p-8 text-center shadow-sm">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-muted text-muted-foreground">
                    <LockKeyhole class="h-7 w-7" />
                </div>

                <h1 class="mt-6 text-2xl font-bold tracking-tight">{{ pageTitle }} is off right now</h1>
                <p class="mt-3 text-sm leading-6 text-muted-foreground">{{ message }}</p>

                <Link
                    href="/"
                    class="mt-6 inline-flex items-center gap-2 rounded-md bg-primary px-4 py-2 text-sm font-semibold text-primary-foreground transition hover:bg-primary/90"
                >
                    <ArrowLeft class="h-4 w-4" />
                    Back to Home
                </Link>
            </section>
        </div>
    </AppLayout>
</template>
