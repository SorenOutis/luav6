<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const STORAGE_KEY = 'lsi-cookie-consent';

type ConsentChoice = 'all' | 'essential';

const visible = ref(false);

const readChoice = (): ConsentChoice | null => {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw === 'all' || raw === 'essential') return raw;
        if (!raw) return null;
        const parsed = JSON.parse(raw) as { choice?: unknown };
        if (parsed.choice === 'all' || parsed.choice === 'essential') {
            return parsed.choice;
        }
        return null;
    } catch {
        return null;
    }
};

const saveChoice = (choice: ConsentChoice): void => {
    try {
        localStorage.setItem(
            STORAGE_KEY,
            JSON.stringify({ choice, at: new Date().toISOString() }),
        );
    } catch {
        // Storage may be blocked. The banner simply hides for this visit.
    }
    visible.value = false;
};

const openSettings = (): void => {
    visible.value = true;
};

onMounted(() => {
    if (!readChoice()) {
        visible.value = true;
    }
    window.addEventListener('lsi:open-cookie-settings', openSettings);
});

onBeforeUnmount(() => {
    window.removeEventListener('lsi:open-cookie-settings', openSettings);
});
</script>

<template>
    <div
        v-if="visible"
        role="dialog"
        aria-live="polite"
        aria-label="Cookie consent"
        class="fixed inset-x-0 bottom-0 z-[80] px-4 pb-4 sm:px-6 sm:pb-6"
    >
        <div
            class="mx-auto flex max-w-3xl flex-col gap-4 rounded-2xl border border-border bg-card p-5 shadow-2xl sm:p-6"
        >
            <div>
                <p class="text-sm font-semibold text-foreground">
                    We use cookies
                </p>
                <p class="mt-1.5 text-sm leading-relaxed text-muted-foreground">
                    LSI uses strictly necessary cookies for sign in and
                    security, plus functional storage for preferences such as
                    theme. There is no advertising tracking. Read the
                    <Link
                        href="/cookies"
                        class="font-medium text-primary underline underline-offset-4 hover:text-foreground"
                    >
                        Cookie Policy
                    </Link>
                    for details.
                </p>
            </div>
            <div class="flex flex-col gap-2 sm:flex-row sm:justify-end">
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg border border-border px-4 text-sm font-medium text-foreground transition-colors hover:bg-muted"
                    @click="saveChoice('essential')"
                >
                    Essential only
                </button>
                <button
                    type="button"
                    class="inline-flex min-h-10 items-center justify-center rounded-lg bg-primary px-4 text-sm font-semibold text-primary-foreground transition-opacity hover:opacity-90"
                    @click="saveChoice('all')"
                >
                    Accept all
                </button>
            </div>
        </div>
    </div>
</template>
