<script setup lang="ts">
import axios from 'axios';
import { Check, GraduationCap, Hash, Loader2, Sparkles } from 'lucide-vue-next';
import { ref, nextTick, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
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
        content-class="w-[94vw] max-w-[460px] overflow-hidden rounded-[28px] border border-black/[0.06] bg-background/80 p-7 shadow-[0_28px_80px_-24px_rgba(0,0,0,0.45)] backdrop-blur-2xl sm:p-8 dark:border-white/[0.08]"
        @close="close"
    >
        <!-- ═══════════════ CODE INPUT ═══════════════ -->
        <template v-if="!showSuccess">
            <!--
                Extra horizontal padding on mobile: the bottom-sheet chrome
                only gives the slot px-2, so we pad here. On desktop sm:px-0
                lets the dialog's own padding apply.
            -->
            <div class="px-4 sm:px-0">
                <!-- Apple-style squircle icon with gloss -->
                <div class="flex justify-center">
                    <div
                        class="relative flex h-16 w-16 items-center justify-center overflow-hidden rounded-[22px] bg-gradient-to-b from-primary/25 to-primary/10 shadow-lg ring-1 shadow-primary/15 ring-black/5 ring-inset sm:h-[72px] sm:w-[72px] dark:ring-white/10"
                    >
                        <div
                            class="pointer-events-none absolute inset-x-0 top-0 h-1/2 bg-gradient-to-b from-white/35 to-transparent"
                            aria-hidden="true"
                        ></div>
                        <GraduationCap
                            class="relative h-8 w-8 text-primary sm:h-9 sm:w-9"
                        />
                    </div>
                </div>

                <!-- Title -->
                <div class="mt-5 text-center sm:mt-6">
                    <h2
                        class="text-[22px] font-semibold tracking-tight text-foreground sm:text-[24px]"
                    >
                        Welcome to the Academy
                    </h2>
                    <p
                        class="mx-auto mt-2 max-w-[34ch] text-[15px] leading-relaxed text-muted-foreground"
                    >
                        Enter your section code to join your class.
                    </p>
                </div>

                <!-- Code field (filled, Apple-style) -->
                <div class="mx-auto mt-7 max-w-sm sm:mt-8">
                    <div class="relative">
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
                            class="h-14 rounded-2xl border-transparent bg-muted/55 pl-11 text-center font-mono text-lg font-semibold tracking-[0.3em] uppercase shadow-none transition-all focus-visible:border-transparent focus-visible:bg-muted/75 focus-visible:ring-2 focus-visible:ring-primary/50 sm:text-xl dark:bg-white/[0.06] dark:focus-visible:bg-white/[0.09]"
                            maxlength="9"
                            @input="handleCodeInput"
                            @keyup.enter="submitCode"
                        />
                    </div>
                    <InputError :message="codeError" class="mt-2" />
                    <p
                        class="mt-2.5 text-center text-[13px] text-muted-foreground/70"
                    >
                        8-character code · dashes are added for you
                    </p>

                    <!-- Primary CTA -->
                    <button
                        type="button"
                        :disabled="
                            joinCode.replace(/-/g, '').length !== 8 ||
                            isVerifyingCode
                        "
                        class="mt-5 flex h-[52px] w-full items-center justify-center gap-2 rounded-2xl bg-primary text-[16px] font-semibold text-primary-foreground shadow-lg shadow-primary/25 transition-all hover:brightness-105 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40 sm:h-14 dark:hover:brightness-110"
                        @click="submitCode"
                    >
                        <Loader2
                            v-if="isVerifyingCode"
                            class="h-5 w-5 animate-spin"
                        />
                        <Sparkles v-else class="h-5 w-5" />
                        {{ isVerifyingCode ? 'Joining…' : 'Join Section' }}
                    </button>

                    <div class="mt-3 flex flex-col items-center gap-1">
                        <button
                            type="button"
                            class="h-10 px-4 text-[15px] font-medium text-primary transition-opacity hover:opacity-70"
                            @click="close"
                        >
                            Not now
                        </button>
                        <p class="text-[12px] text-muted-foreground/60">
                            Your code is only used to enroll you in your class.
                        </p>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════════ SUCCESS ═══════════════ -->
        <template v-else-if="joinedSection">
            <div class="px-4 text-center sm:px-0">
                <!-- Apple-style filled success circle -->
                <div
                    class="relative mx-auto flex h-20 w-20 items-center justify-center sm:h-24 sm:w-24"
                >
                    <div
                        class="absolute inset-0 rounded-full blur-2xl"
                        :class="
                            joinedSection.already_joined
                                ? 'bg-amber-500/25'
                                : 'bg-primary/25'
                        "
                        aria-hidden="true"
                    ></div>
                    <div
                        class="relative flex h-full w-full items-center justify-center rounded-full shadow-lg ring-1 ring-white/15 ring-inset"
                        :class="
                            joinedSection.already_joined
                                ? 'bg-gradient-to-b from-amber-400 to-amber-500 shadow-amber-500/30'
                                : 'bg-gradient-to-b from-primary to-primary/85 shadow-primary/30'
                        "
                    >
                        <Check
                            class="h-10 w-10 text-primary-foreground sm:h-12 sm:w-12"
                            :class="isEnteringDashboard ? '' : 'animate-bounce'"
                        />
                    </div>
                </div>

                <h2
                    class="mt-5 text-[22px] font-semibold tracking-tight text-foreground sm:text-[24px]"
                >
                    {{
                        joinedSection.already_joined
                            ? 'Already Joined'
                            : "You're in!"
                    }}
                </h2>
                <p
                    class="mx-auto mt-2 max-w-[34ch] text-[15px] leading-relaxed text-muted-foreground"
                >
                    <template v-if="joinedSection.already_joined">
                        You&apos;re already a member of this class.
                    </template>
                    <template v-else>
                        You&apos;ve joined your class. Taking you to your
                        dashboard…
                    </template>
                </p>

                <div class="mt-4 flex justify-center">
                    <span
                        class="inline-flex items-center gap-1.5 rounded-full bg-muted/60 px-3.5 py-1.5 text-[13px] font-medium text-foreground dark:bg-white/[0.06]"
                    >
                        <Hash class="h-3 w-3 text-muted-foreground" />
                        {{ joinedSection.name }}
                    </span>
                </div>

                <div class="mx-auto mt-6 max-w-sm">
                    <button
                        type="button"
                        :disabled="isEnteringDashboard"
                        class="flex h-[52px] w-full items-center justify-center gap-2 rounded-2xl bg-primary text-[16px] font-semibold text-primary-foreground shadow-lg shadow-primary/25 transition-all hover:brightness-105 active:scale-[0.98] disabled:cursor-not-allowed disabled:opacity-40 sm:h-14 dark:hover:brightness-110"
                        @click="enterDashboard"
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
                    </button>
                </div>
            </div>
        </template>
    </ResponsiveModal>
</template>
