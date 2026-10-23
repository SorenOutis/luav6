<script setup lang="ts">
import { Head, Link, router } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    CheckCircle2,
    KeyRound,
    Loader2,
    Lock,
    ShieldAlert,
    Sparkles,
} from 'lucide-vue-next';
import { ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface Props {
    workspaceName?: string | null;
    studentName?: string | null;
    studentEmail?: string | null;
    hasPendingInvite?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    workspaceName: 'Your School',
    studentName: 'Student',
    studentEmail: '',
    hasPendingInvite: false,
});

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Community Lounge',
        href: '/community',
    },
];

const inviteCode = ref('');
const isSubmitting = ref(false);
const errorMessage = ref<string | null>(null);
const successMessage = ref<string | null>(null);

const handleUnlock = async () => {
    if (!inviteCode.value.trim() || isSubmitting.value) return;

    isSubmitting.value = true;
    errorMessage.value = null;

    try {
        const response = await axios.post('/community/join-by-code', {
            code: inviteCode.value.trim(),
        });

        if (response.data?.status === 'unlocked') {
            successMessage.value = 'Lounge unlocked! Redirecting...';
            setTimeout(() => {
                router.visit('/community');
            }, 600);
        } else {
            router.visit('/community');
        }
    } catch (err: any) {
        errorMessage.value =
            err.response?.data?.message ||
            err.response?.data?.errors?.code?.[0] ||
            'Invalid or expired invite link. Please ask your administrator for a fresh link.';
    } finally {
        isSubmitting.value = false;
    }
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Community Lounge - Invite Only" />

        <div
            class="relative flex min-h-[calc(100vh-8rem)] items-center justify-center p-4 sm:p-6 lg:p-8"
        >
            <div
                class="relative w-full max-w-[420px] overflow-hidden rounded-[28px] border border-border/60 bg-card/95 p-6 shadow-2xl ring-1 ring-border/20 backdrop-blur-2xl sm:p-8 dark:border-white/10 dark:bg-zinc-900/95"
            >
                <!-- Top specular light highlight -->
                <div
                    class="pointer-events-none absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-white/25 to-transparent"
                />

                <!-- Lock Icon Badge (Apple squircle) -->
                <div class="flex justify-center pt-1">
                    <div
                        class="flex h-13 w-13 items-center justify-center rounded-2xl bg-muted/80 text-foreground shadow-sm ring-1 ring-border/60"
                    >
                        <Lock class="h-6 w-6 stroke-[1.8]" />
                    </div>
                </div>

                <!-- Title & Description -->
                <div class="mt-4 text-center">
                    <div
                        class="inline-flex items-center gap-1.5 rounded-full border border-border/50 bg-muted/60 px-2.5 py-0.5 text-[11px] font-semibold text-muted-foreground"
                    >
                        <Sparkles class="h-3 w-3 text-brand" />
                        <span>Invite Only</span>
                    </div>

                    <h1
                        class="mt-3 text-xl font-bold tracking-tight text-foreground sm:text-2xl"
                    >
                        Community Lounge is Invite-Only
                    </h1>

                    <p
                        class="mt-2 text-xs leading-relaxed text-muted-foreground sm:text-sm"
                    >
                        Access to the lounge for
                        <span class="font-medium text-foreground">{{
                            props.workspaceName || 'your workspace'
                        }}</span>
                        is reserved for students with an active invitation link.
                    </p>
                </div>

                <!-- Current User Identity Pill (Apple grouped cell) -->
                <div
                    class="mt-5 flex items-center justify-between rounded-xl border border-border/40 bg-muted/40 px-3.5 py-2.5 text-xs text-muted-foreground"
                >
                    <span class="text-muted-foreground">Signed in as</span>
                    <span class="truncate font-medium text-foreground">
                        {{ props.studentName }}
                        <span
                            v-if="props.studentEmail"
                            class="font-normal text-muted-foreground"
                        >
                            ({{ props.studentEmail }})
                        </span>
                    </span>
                </div>

                <!-- Input form -->
                <form class="mt-5 space-y-3" @submit.prevent="handleUnlock">
                    <label
                        for="invite-code-input"
                        class="block text-xs font-semibold text-foreground"
                    >
                        Enter Invite Code or Link
                    </label>

                    <div class="relative">
                        <input
                            id="invite-code-input"
                            v-model="inviteCode"
                            type="text"
                            placeholder="Paste invite link or token here..."
                            class="h-11 w-full rounded-xl border border-input bg-background/80 px-3.5 text-sm text-foreground shadow-xs transition-all outline-none placeholder:text-muted-foreground focus:border-ring focus:ring-2 focus:ring-ring/30"
                            autocomplete="off"
                            spellcheck="false"
                        />
                    </div>

                    <!-- Error state -->
                    <div
                        v-if="errorMessage"
                        class="flex items-start gap-2 rounded-xl border border-destructive/30 bg-destructive/10 p-3 text-xs text-destructive"
                    >
                        <ShieldAlert class="mt-0.5 h-4 w-4 shrink-0" />
                        <span>{{ errorMessage }}</span>
                    </div>

                    <!-- Success state -->
                    <div
                        v-if="successMessage"
                        class="flex items-center gap-2 rounded-xl border border-emerald-500/30 bg-emerald-500/10 p-3 text-xs text-emerald-600 dark:text-emerald-400"
                    >
                        <CheckCircle2 class="h-4 w-4 shrink-0" />
                        <span>{{ successMessage }}</span>
                    </div>

                    <button
                        type="submit"
                        :disabled="!inviteCode.trim() || isSubmitting"
                        class="flex h-11 w-full cursor-pointer items-center justify-center gap-2 rounded-full bg-foreground text-xs font-semibold text-background shadow-sm transition-all duration-150 hover:opacity-90 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40 sm:text-sm dark:bg-white dark:text-zinc-950"
                    >
                        <Loader2
                            v-if="isSubmitting"
                            class="h-4 w-4 animate-spin text-background dark:text-zinc-950"
                        />
                        <KeyRound v-else class="h-4 w-4" />
                        <span>{{
                            isSubmitting ? 'Verifying...' : 'Unlock Lounge'
                        }}</span>
                    </button>
                </form>

                <!-- Footer guidance -->
                <div class="mt-6 border-t border-border/50 pt-4 text-center">
                    <p
                        class="text-[11px] leading-relaxed text-muted-foreground sm:text-xs"
                    >
                        Don't have an invitation yet? Reach out to your
                        instructor or administrator to generate your personal
                        invite link.
                    </p>

                    <div class="mt-3.5 flex justify-center">
                        <Link
                            href="/dashboard"
                            class="inline-flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground active:scale-95"
                        >
                            <ArrowLeft class="h-3.5 w-3.5" />
                            <span>Return to Dashboard</span>
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>
