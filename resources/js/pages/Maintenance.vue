<script setup lang="ts">
import { Head, router } from '@inertiajs/vue3';
import { LogOut, Wrench } from 'lucide-vue-next';
import { onMounted, onUnmounted, ref } from 'vue';
import FoxCompanion from '@/components/FoxCompanion.vue';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

const props = withDefaults(
    defineProps<{
        title?: string;
        message?: string;
        image?: string;
        imageUrl?: string;
        isAuthenticated?: boolean;
    }>(),
    {
        title: "We'll be right back",
        message:
            "We're doing scheduled maintenance to improve your learning experience. Please check back soon.",
        image: 'maintenance',
        imageUrl: '/images/mascots/fox-maintenance.webp',
        isAuthenticated: false,
    },
);

// Polled until maintenance turns off, then the page reloads into the app.
// Cheap JSON check (not a full reload) so idle students don't hammer the server.
const POLL_INTERVAL_MS = 30_000;

let pollTimer: number | null = null;

const checkMaintenanceStatus = async (): Promise<void> => {
    if (document.hidden) {
        return;
    }

    try {
        const response = await fetch('/api/maintenance-status', {
            credentials: 'same-origin',
            headers: { Accept: 'application/json' },
        });

        if (!response.ok) {
            return;
        }

        const data: unknown = await response.json();

        if (
            typeof data === 'object' &&
            data !== null &&
            'enabled' in data &&
            (data as { enabled: unknown }).enabled === false
        ) {
            window.location.reload();
        }
    } catch {
        // Server may be mid-deploy — just try again on the next tick.
    }
};

onMounted(() => {
    pollTimer = window.setInterval(checkMaintenanceStatus, POLL_INTERVAL_MS);
});

onUnmounted(() => {
    if (pollTimer !== null) {
        window.clearInterval(pollTimer);
        pollTimer = null;
    }
});

const loggingOut = ref(false);

const handleLogout = (): void => {
    if (loggingOut.value) {
        return;
    }

    loggingOut.value = true;
    sessionStorage.setItem('logged_out', 'true');
    router.post(logout(), {}, { onFinish: () => (loggingOut.value = false) });
};
</script>

<template>
    <Head :title="props.title" />

    <div
        class="flex min-h-screen items-center justify-center bg-background px-6 py-16"
    >
        <section
            class="w-full max-w-xl rounded-lg border border-border bg-card p-8 text-center shadow-sm"
        >
            <div
                class="mx-auto inline-flex items-center gap-2 rounded-full bg-muted px-3 py-1 text-xs font-semibold text-muted-foreground"
            >
                <Wrench class="size-3.5" aria-hidden="true" />
                Maintenance
            </div>

            <div class="mt-6 flex justify-center">
                <FoxCompanion
                    :mascot="props.image"
                    :message="props.message"
                    :show-message="false"
                    :size="180"
                    label="Maintenance fox"
                />
            </div>

            <h1 class="mt-6 text-2xl font-bold tracking-tight text-foreground">
                {{ props.title }}
            </h1>
            <p class="mt-3 text-sm leading-6 text-muted-foreground">
                {{ props.message }}
            </p>

            <p class="mt-2 text-xs leading-5 text-muted-foreground">
                You'll be brought back automatically once we're done — no need
                to keep refreshing.
            </p>

            <div
                v-if="props.isAuthenticated"
                class="mt-6 flex flex-wrap items-center justify-center gap-3"
            >
                <Button
                    type="button"
                    variant="outline"
                    :disabled="loggingOut"
                    @click="handleLogout"
                >
                    <LogOut class="size-4" aria-hidden="true" />
                    {{ loggingOut ? 'Logging out…' : 'Log out' }}
                </Button>
            </div>
        </section>
    </div>
</template>
