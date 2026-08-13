<script setup lang="ts">
import axios from 'axios';
import {
    Check,
    GraduationCap,
    Hash,
    Loader2,
    ShieldCheck,
    Sparkles,
} from 'lucide-vue-next';
import { ref, nextTick, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

const props = defineProps<{
    show: boolean;
}>();

const enterDashboard = () => {
    if (isEnteringDashboard.value) return;
    isEnteringDashboard.value = true;
    window.location.href = '/dashboard';
};

// ── Code input state ───────────────────────────────────────────────
const joinCode = ref('');
const isVerifyingCode = ref(false);
const codeError = ref('');
const joinedSection = ref<{
    id: number;
    name: string;
    already_joined: boolean;
} | null>(null);
const showSuccess = ref(false);
const isEnteringDashboard = ref(false);

const formatCodeInput = (value: string) => {
    let cleaned = value.toUpperCase().replace(/[^A-Z0-9]/g, '');
    if (cleaned.length > 4) {
        cleaned = cleaned.slice(0, 4) + '-' + cleaned.slice(4, 8);
    }
    if (cleaned.length > 9) {
        cleaned = cleaned.slice(0, 9);
    }
    return cleaned;
};

const handleCodeInput = (e: Event) => {
    const target = e.target as HTMLInputElement;
    const cursorPos = target.selectionStart ?? 0;
    const prevLength = joinCode.value.length;

    joinCode.value = formatCodeInput(joinCode.value);
    codeError.value = '';

    nextTick(() => {
        const newLength = joinCode.value.length;
        if (newLength > prevLength && cursorPos > 0) {
            target.setSelectionRange(cursorPos + 1, cursorPos + 1);
        } else {
            target.setSelectionRange(cursorPos, cursorPos);
        }
    });
};

const submitCode = async () => {
    const rawCode = joinCode.value.replace(/-/g, '');
    if (rawCode.length !== 8) {
        codeError.value = 'Please enter a complete 8-character code.';
        return;
    }

    isVerifyingCode.value = true;
    codeError.value = '';

    try {
        const { data } = await axios.post<{
            valid: boolean;
            message?: string;
            section?: { id: number; name: string; already_joined: boolean };
        }>('/sections/join-by-code', { code: rawCode });

        if (!data.valid || !data.section) {
            codeError.value =
                data.message ||
                'Invalid section code. Please check and try again.';
            return;
        }

        joinedSection.value = data.section;
        showSuccess.value = true;

        // Auto-redirect after celebration (shorter for already-joined users)
        setTimeout(
            () => {
                if (!isEnteringDashboard.value) {
                    window.location.href = '/dashboard';
                }
            },
            data.section.already_joined ? 1200 : 2000,
        );
    } catch (err: unknown) {
        if (axios.isAxiosError(err) && err.response?.data?.message) {
            codeError.value = err.response.data.message;
        } else {
            codeError.value = 'Unable to verify code. Please try again.';
        }
    } finally {
        isVerifyingCode.value = false;
    }
};

// ── Emits ───────────────────────────────────────────────────────────
const emit = defineEmits<{
    close: [];
}>();

const close = () => emit('close');

// ── Reset state when modal re-opens ────────────────────────────────
watch(
    () => props.show,
    (isShown) => {
        if (!isShown) {
            joinCode.value = '';
            codeError.value = '';
            joinedSection.value = null;
            showSuccess.value = false;
        }
    },
);
</script>

<template>
    <ResponsiveModal
        :open="show"
        content-class="w-[94vw] max-w-[540px] overflow-hidden rounded-3xl border border-primary/20 bg-background p-6 shadow-2xl sm:p-9"
        @close="close"
    >
        <!-- ═══════════════ CODE INPUT ═══════════════ -->
        <template v-if="!showSuccess">
            <!--
                Extra horizontal padding on mobile: the bottom-sheet chrome
                only gives the slot px-2, so we pad here for a premium feel.
                On desktop sm:px-0 lets the dialog's own padding apply.
            -->
            <div class="px-4 sm:px-0">
                <!-- Hero badge -->
                <div class="flex justify-center">
                    <div
                        class="relative flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary/15 to-primary/5 ring-1 ring-primary/20 sm:h-20 sm:w-20"
                    >
                        <div
                            class="absolute inset-0 rounded-2xl bg-primary/10 blur-xl"
                            aria-hidden="true"
                        ></div>
                        <GraduationCap
                            class="relative h-8 w-8 text-primary sm:h-10 sm:w-10"
                        />
                    </div>
                </div>

                <!-- Title -->
                <div class="mt-5 text-center sm:mt-6">
                    <h2
                        class="bg-gradient-to-br from-foreground to-muted-foreground bg-clip-text text-2xl font-black tracking-tight text-transparent sm:text-3xl"
                    >
                        Welcome to the Academy
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted-foreground sm:text-[15px]"
                    >
                        Enter your section code to join your class. Your
                        instructor should have shared it with you.
                    </p>
                </div>

                <!-- Code field -->
                <div class="mx-auto mt-7 max-w-sm sm:mt-8">
                    <label
                        class="block text-[11px] font-bold tracking-widest text-muted-foreground/70 uppercase"
                    >
                        Section code
                    </label>
                    <div class="relative mt-2">
                        <Hash
                            class="pointer-events-none absolute top-1/2 left-4 z-10 h-4 w-4 -translate-y-1/2 text-muted-foreground/40"
                        />
                        <Input
                            v-model="joinCode"
                            placeholder="9H84-K6B5"
                            inputmode="text"
                            autocomplete="off"
                            autocapitalize="characters"
                            spellcheck="false"
                            class="h-14 border-border/60 pl-11 text-center font-mono text-lg font-bold tracking-[0.3em] uppercase shadow-inner transition-colors focus-visible:border-primary/60 sm:text-xl"
                            maxlength="9"
                            @input="handleCodeInput"
                            @keyup.enter="submitCode"
                        />
                    </div>
                    <InputError :message="codeError" class="mt-2" />
                    <p
                        class="mt-2 text-center text-xs text-muted-foreground/70"
                    >
                        8-character code · dashes are added for you
                    </p>

                    <Button
                        @click="submitCode"
                        class="mt-5 h-12 w-full gap-2 text-sm font-black tracking-wider uppercase shadow-lg shadow-primary/25 transition-all hover:-translate-y-0.5 active:translate-y-0 disabled:translate-y-0 disabled:opacity-50 sm:h-14 sm:text-base"
                        :disabled="
                            joinCode.replace(/-/g, '').length !== 8 ||
                            isVerifyingCode
                        "
                    >
                        <Loader2
                            v-if="isVerifyingCode"
                            class="h-5 w-5 animate-spin"
                        />
                        <Sparkles v-else class="h-5 w-5" />
                        {{ isVerifyingCode ? 'Joining...' : 'Join Section' }}
                    </Button>

                    <div class="mt-4 flex flex-col items-center gap-3">
                        <button
                            type="button"
                            @click="close"
                            class="text-xs font-semibold text-muted-foreground/70 underline-offset-4 transition-colors hover:text-muted-foreground hover:underline"
                        >
                            Skip for now — I&apos;ll join later
                        </button>
                        <p
                            class="flex items-center gap-1.5 text-[11px] text-muted-foreground/60"
                        >
                            <ShieldCheck class="h-3.5 w-3.5 shrink-0" />
                            Your code only enrolls you in your class.
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════════ SUCCESS ═══════════════ -->
        <template v-else-if="joinedSection">
            <div class="px-4 text-center sm:px-0">
                <!-- Celebratory ring -->
                <div
                    class="relative mx-auto flex h-20 w-20 items-center justify-center sm:h-24 sm:w-24"
                >
                    <div
                        class="absolute inset-0 rounded-full blur-xl"
                        :class="
                            joinedSection.already_joined
                                ? 'bg-amber-500/20'
                                : 'bg-primary/20'
                        "
                        aria-hidden="true"
                    ></div>
                    <div
                        class="relative flex h-full w-full items-center justify-center rounded-full ring-1"
                        :class="
                            joinedSection.already_joined
                                ? 'bg-amber-500/10 ring-amber-500/20'
                                : 'bg-primary/10 ring-primary/20'
                        "
                    >
                        <Check
                            class="h-10 w-10 sm:h-12 sm:w-12"
                            :class="[
                                joinedSection.already_joined
                                    ? 'text-amber-500'
                                    : 'text-primary',
                                isEnteringDashboard ? '' : 'animate-bounce',
                            ]"
                        />
                    </div>
                </div>

                <h2 class="mt-5 text-2xl font-black tracking-tight sm:text-3xl">
                    {{
                        joinedSection.already_joined
                            ? 'Already Joined'
                            : "You're in!"
                    }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-md text-sm leading-relaxed text-muted-foreground sm:text-[15px]"
                >
                    <template v-if="joinedSection.already_joined">
                        You&apos;re already a member of this class.
                    </template>
                    <template v-else>
                        You&apos;ve successfully joined your class. Redirecting
                        to your dashboard...
                    </template>
                </p>

                <!-- Section pill -->
                <div class="mt-4 flex justify-center">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full border border-primary/20 bg-primary/5 px-3 py-1.5 text-xs font-bold text-foreground"
                    >
                        <Hash class="h-3 w-3 text-primary" />
                        {{ joinedSection.name }}
                    </span>
                </div>

                <div class="mx-auto mt-6 max-w-sm">
                    <Button
                        @click="enterDashboard"
                        class="h-12 w-full gap-2 text-sm font-black tracking-wider uppercase shadow-lg shadow-primary/25 transition-all hover:-translate-y-0.5 active:translate-y-0 disabled:translate-y-0 disabled:opacity-50 sm:h-14 sm:text-base"
                        :disabled="isEnteringDashboard"
                    >
                        <Loader2
                            v-if="isEnteringDashboard"
                            class="h-5 w-5 animate-spin"
                        />
                        <Check v-else class="h-5 w-5" />
                        {{
                            isEnteringDashboard
                                ? 'Entering…'
                                : 'Enter Dashboard'
                        }}
                    </Button>
                </div>
            </div>
        </template>
    </ResponsiveModal>
</template>
