<script setup lang="ts">
import { router } from '@inertiajs/vue3';
import { LoaderCircle, LogOut, ShieldCheck } from 'lucide-vue-next';
import { ref } from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Button } from '@/components/ui/button';
import { logout } from '@/routes';

type Props = {
    open: boolean;
};

defineProps<Props>();

const emit = defineEmits<{
    close: [];
}>();

const loggingOut = ref(false);

const close = () => {
    if (loggingOut.value) return;

    emit('close');
};

const confirmLogout = () => {
    if (loggingOut.value) return;

    loggingOut.value = true;
    sessionStorage.setItem('logged_out', 'true');

    router.post(
        logout(),
        {},
        {
            onFinish: () => {
                loggingOut.value = false;
                emit('close');
            },
        },
    );
};
</script>

<template>
    <ResponsiveModal
        :open="open"
        title="Ready to log out?"
        description="You'll need to sign in again to access your dashboard, progress, and learning map."
        content-class="border-border/70 bg-card/95 shadow-2xl sm:max-w-md supports-[backdrop-filter]:bg-card/90"
        @close="close"
    >
        <div
            class="flex items-start gap-3 rounded-2xl border border-destructive/15 bg-destructive/[0.06] p-4"
        >
            <span
                class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-destructive/10 text-destructive ring-1 ring-destructive/15"
            >
                <ShieldCheck class="size-5" aria-hidden="true" />
            </span>
            <div class="min-w-0 pt-0.5">
                <p class="text-sm font-semibold text-foreground">
                    Your account stays safe
                </p>
                <p class="mt-1 text-xs leading-5 text-muted-foreground">
                    Logging out only ends this session. Your saved learning
                    progress will still be here when you return.
                </p>
            </div>
        </div>

        <template #footer>
            <Button
                type="button"
                variant="outline"
                :disabled="loggingOut"
                data-test="logout-cancel-button"
                @click="close"
            >
                Stay signed in
            </Button>
            <Button
                type="button"
                variant="destructive"
                :disabled="loggingOut"
                data-test="logout-confirm-button"
                @click="confirmLogout"
            >
                <LoaderCircle
                    v-if="loggingOut"
                    class="size-4 animate-spin"
                    aria-hidden="true"
                />
                <LogOut v-else class="size-4" aria-hidden="true" />
                {{ loggingOut ? 'Logging out…' : 'Yes, log me out' }}
            </Button>
        </template>
    </ResponsiveModal>
</template>
