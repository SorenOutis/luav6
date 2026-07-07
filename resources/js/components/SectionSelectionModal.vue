<script setup lang="ts">
import axios from 'axios';
import { Check, Hash, Loader2, Sparkles, X } from 'lucide-vue-next';
import { ref, nextTick, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';

defineProps<{
    show: boolean;
}>();

// ── Code input state ───────────────────────────────────────────────
const joinCode = ref('');
const isVerifyingCode = ref(false);
const codeError = ref('');
const joinedSection = ref<{ id: number; name: string } | null>(null);
const showSuccess = ref(false);

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
                data.message || 'Invalid section code. Please check and try again.';
            return;
        }

        joinedSection.value = data.section;
        showSuccess.value = true;

        // Auto-redirect after celebration
        setTimeout(() => {
            window.location.reload();
        }, 2000);
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
    <Dialog :open="show">
        <DialogContent
            class="w-[96vw] max-w-[520px] border-primary/20 bg-background p-5 shadow-2xl sm:p-8"
            :show-close-button="false"
            @pointer-down-outside.prevent
            @escape-key-down.prevent
        >
            <!-- ═══════════════ CODE INPUT ═══════════════ -->
            <template v-if="!showSuccess">
                <DialogHeader class="sm:text-center">
                    <DialogTitle
                        class="bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-xl font-black text-transparent sm:text-3xl"
                    >
                        Welcome to the Academy
                    </DialogTitle>
                    <DialogDescription
                        class="mx-auto max-w-lg pt-2 text-xs leading-relaxed text-muted-foreground/80 sm:text-sm"
                    >
                        Enter your section code to join your class. Your
                        instructor should have provided this code.
                    </DialogDescription>
                </DialogHeader>

                <div class="py-6 sm:py-8">
                    <div class="mx-auto max-w-sm space-y-4">
                        <div class="space-y-2">
                            <label
                                class="block text-[10px] font-black tracking-widest text-muted-foreground/60 uppercase sm:text-xs"
                            >
                                Section code
                            </label>
                            <div class="relative">
                                <Hash
                                    class="pointer-events-none absolute top-1/2 left-3 z-10 h-4 w-4 -translate-y-1/2 text-muted-foreground/40"
                                />
                                <Input
                                    v-model="joinCode"
                                    placeholder="e.g. 9H84-K6B5"
                                    class="h-12 pl-10 text-center font-mono text-lg font-bold tracking-[0.3em] uppercase sm:text-xl"
                                    maxlength="9"
                                    @input="handleCodeInput"
                                    @keyup.enter="submitCode"
                                />
                            </div>
                            <InputError :message="codeError" />
                        </div>

                        <Button
                            @click="submitCode"
                            class="h-12 w-full text-sm font-black tracking-wider uppercase shadow-lg shadow-primary/20 transition-all hover:translate-y-[-2px] active:translate-y-[0] disabled:opacity-50 sm:h-14 sm:text-base"
                            :disabled="
                                joinCode.replace(/-/g, '').length !== 8 ||
                                isVerifyingCode
                            "
                        >
                            <Loader2
                                v-if="isVerifyingCode"
                                class="mr-2 h-5 w-5 animate-spin"
                            />
                            <Sparkles v-else class="mr-2 h-5 w-5" />
                            {{ isVerifyingCode ? 'Joining...' : 'Join Section' }}
                        </Button>
                    </div>
                </div>

                <DialogFooter class="flex flex-col pt-2 sm:items-center">
                    <Button
                        @click="emit('close')"
                        variant="ghost"
                        class="h-10 text-xs font-medium text-muted-foreground/60 hover:text-muted-foreground/90"
                    >
                        Skip for now — I&apos;ll join later
                    </Button>
                </DialogFooter>
            </template>

            <!-- ═══════════════ SUCCESS ═══════════════ -->
            <template v-else-if="joinedSection">
                <DialogHeader class="sm:text-center">
                    <div
                        class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-primary/10 sm:h-20 sm:w-20"
                    >
                        <Check
                            class="h-8 w-8 animate-bounce text-primary sm:h-10 sm:w-10"
                        />
                    </div>
                    <DialogTitle
                        class="text-xl font-black tracking-tight uppercase sm:text-3xl"
                    >
                        You&apos;re in!
                    </DialogTitle>
                    <DialogDescription
                        class="mx-auto max-w-md pt-2 text-sm leading-relaxed"
                    >
                        You&apos;ve successfully joined
                        <strong class="text-foreground">{{
                            joinedSection.name
                        }}</strong
                        >. Redirecting to your dashboard...
                    </DialogDescription>
                </DialogHeader>

                <div class="flex justify-center py-6">
                    <Button
                        @click="window.location.reload()"
                        class="h-12 w-full max-w-sm text-sm font-black tracking-wider uppercase shadow-lg sm:h-14 sm:text-base"
                    >
                        <Check class="mr-2 h-5 w-5" />
                        Enter Dashboard
                    </Button>
                </div>
            </template>
        </DialogContent>
    </Dialog>
</template>
