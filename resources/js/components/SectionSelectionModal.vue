<script setup lang="ts">
import { ref, computed, nextTick, watch } from 'vue';
import { useForm } from '@inertiajs/vue3';
import axios from 'axios';
import gsap from 'gsap';
import { Input } from '@/components/ui/input';
import InputError from '@/components/InputError.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';
import { Check, Loader2, Lock, LockOpen, X } from 'lucide-vue-next';
import { update as updateSectionRoute } from '@/routes/profile/section';

interface Section {
    id: number;
    name: string;
    has_password?: boolean;
}

const props = defineProps<{
    sections: Section[];
    show: boolean;
}>();

const selectedSections = ref<number[]>([]);
const sectionPasswords = ref<Record<number, string>>({});
const flippedId = ref<number | null>(null);
const cardInnerRefs = ref<Record<number, HTMLElement | null>>({});
const verifyingId = ref<number | null>(null);
const passwordErrors = ref<Record<number, string>>({});

const form = useForm({
    section_ids: [] as number[],
    section_passwords: {} as Record<number, string>,
});

const setCardRef = (el: unknown, id: number) => {
    cardInnerRefs.value[id] = (el as HTMLElement) ?? null;
};

const isSelected = (id: number) => selectedSections.value.includes(id);

const flipTo = (id: number, toBack: boolean) => {
    const el = cardInnerRefs.value[id];
    if (!el) return;
    gsap.to(el, {
        rotationY: toBack ? 180 : 0,
        duration: 0.65,
        ease: 'power3.inOut',
    });
};

const handleCardClick = (section: Section) => {
    // If a different card is currently flipped, ignore to avoid mess
    if (flippedId.value !== null && flippedId.value !== section.id) return;

    if (!section.has_password) {
        const idx = selectedSections.value.indexOf(section.id);
        if (idx > -1) selectedSections.value.splice(idx, 1);
        else selectedSections.value.push(section.id);
        return;
    }

    if (isSelected(section.id)) {
        // Unlocked → lock it (deselect, clear password)
        selectedSections.value = selectedSections.value.filter((i) => i !== section.id);
        delete sectionPasswords.value[section.id];
        delete passwordErrors.value[section.id];
        return;
    }

    // Flip to password prompt
    flippedId.value = section.id;
    delete passwordErrors.value[section.id];
    flipTo(section.id, true);
    nextTick(() => {
        const input = document.getElementById(`section-password-input-${section.id}`);
        (input as HTMLInputElement | null)?.focus();
    });
};

const confirmPassword = async (section: Section) => {
    const pwd = sectionPasswords.value[section.id];
    if (!pwd) return;
    if (verifyingId.value !== null) return;

    verifyingId.value = section.id;
    delete passwordErrors.value[section.id];

    try {
        const { data } = await axios.post<{ valid: boolean }>(
            `/sections/${section.id}/verify-password`,
            { password: pwd },
        );

        if (!data.valid) {
            passwordErrors.value[section.id] = 'Incorrect password.';
            sectionPasswords.value[section.id] = '';
            nextTick(() => {
                const input = document.getElementById(`section-password-input-${section.id}`);
                (input as HTMLInputElement | null)?.focus();
            });
            return;
        }

        // Valid — mark selected and flip to front
        if (!isSelected(section.id)) selectedSections.value.push(section.id);
        flipTo(section.id, false);
        flippedId.value = null;
    } catch {
        passwordErrors.value[section.id] = 'Unable to verify password. Please try again.';
    } finally {
        verifyingId.value = null;
    }
};

const cancelPassword = (section: Section) => {
    if (!isSelected(section.id)) {
        delete sectionPasswords.value[section.id];
    }
    delete passwordErrors.value[section.id];
    flipTo(section.id, false);
    flippedId.value = null;
};

const allPasswordsFilled = computed(() =>
    props.sections
        .filter((s) => isSelected(s.id) && s.has_password)
        .every((s) => (sectionPasswords.value[s.id] || '').length > 0),
);

const submit = () => {
    if (selectedSections.value.length === 0) return;
    if (!allPasswordsFilled.value) return;
    if (flippedId.value !== null) return;

    form.section_ids = selectedSections.value;
    form.section_passwords = { ...sectionPasswords.value };
    form.patch(updateSectionRoute().url, {
        preserveScroll: true,
        onError: (errors) => {
            // Collect failing section ids from keys like "section_passwords.12"
            const failedIds = Object.keys(errors)
                .map((k) => k.match(/^section_passwords\.(\d+)$/))
                .filter((m): m is RegExpMatchArray => m !== null)
                .map((m) => Number(m[1]));

            if (failedIds.length === 0) return;

            // Clear wrong passwords and deselect so user must re-enter
            failedIds.forEach((id) => {
                delete sectionPasswords.value[id];
                selectedSections.value = selectedSections.value.filter((i) => i !== id);
            });

            // Flip the first failing card to its back face to reveal the inline error
            const firstId = failedIds[0];
            flippedId.value = firstId;
            nextTick(() => {
                flipTo(firstId, true);
                const input = document.getElementById(`section-password-input-${firstId}`);
                (input as HTMLInputElement | null)?.focus();
            });
        },
    });
};

// Reset state when modal re-opens
watch(
    () => props.show,
    (isShown) => {
        if (!isShown) {
            flippedId.value = null;
        }
    },
);
</script>

<template>
    <Dialog :open="show">
        <DialogContent
            class="w-[96vw] sm:max-w-[1040px] max-h-[94vh] overflow-y-auto border-primary/20 bg-background shadow-2xl p-5 sm:p-8"
            :show-close-button="false"
            @pointer-down-outside.prevent
            @escape-key-down.prevent
        >
            <DialogHeader class="sm:text-center">
                <DialogTitle
                    class="text-xl sm:text-3xl font-black bg-gradient-to-br from-foreground to-foreground/60 bg-clip-text text-transparent"
                >
                    Welcome to the Academy
                </DialogTitle>
                <DialogDescription
                    class="text-muted-foreground/80 pt-2 text-xs sm:text-base leading-relaxed max-w-2xl mx-auto"
                >
                    Select your assigned sections. Sections with a lock will ask for a password on click.
                </DialogDescription>
            </DialogHeader>

            <div class="py-4 sm:py-6 relative z-50">
                <label
                    class="text-[10px] sm:text-xs font-black uppercase tracking-widest text-muted-foreground/60 block sm:text-center mb-4"
                >
                    Choose your sections
                </label>

                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
                    <div v-for="section in sections" :key="section.id" class="card-perspective">
                        <div
                            :ref="(el) => setCardRef(el, section.id)"
                            class="card-inner relative w-full h-[150px] sm:h-[160px]"
                        >
                            <!-- FRONT FACE -->
                            <button
                                type="button"
                                @click="handleCardClick(section)"
                                :class="[
                                    'card-face absolute inset-0 flex items-center justify-center p-5 rounded-2xl border text-center overflow-hidden group/card transition-colors duration-300',
                                    isSelected(section.id)
                                        ? 'bg-primary border-primary shadow-[0_0_25px_rgba(var(--primary-rgb),0.3)]'
                                        : 'bg-muted/30 border-border/50 hover:border-primary/40 hover:bg-muted/50',
                                ]"
                            >
                                <!-- Tech Grid Background -->
                                <div
                                    class="absolute inset-0 opacity-[0.03] pointer-events-none group-hover/card:opacity-[0.05] transition-opacity"
                                >
                                    <svg class="w-full h-full" xmlns="http://www.w3.org/2000/svg">
                                        <defs>
                                            <pattern
                                                :id="`modal-grid-${section.id}`"
                                                width="10"
                                                height="10"
                                                patternUnits="userSpaceOnUse"
                                            >
                                                <path
                                                    d="M 10 0 L 0 0 0 10"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="0.5"
                                                />
                                            </pattern>
                                        </defs>
                                        <rect
                                            width="100%"
                                            height="100%"
                                            :fill="`url(#modal-grid-${section.id})`"
                                        />
                                    </svg>
                                </div>

                                <div
                                    v-if="isSelected(section.id)"
                                    class="absolute inset-0 bg-white/10 animate-pulse pointer-events-none"
                                ></div>

                                <div class="relative z-10 space-y-1.5">
                                    <p
                                        class="text-[8px] font-black uppercase tracking-[0.3em] font-mono"
                                        :class="
                                            isSelected(section.id)
                                                ? 'text-primary-foreground/60'
                                                : 'text-muted-foreground/40 group-hover/card:text-primary/60'
                                        "
                                    >
                                        &gt;_SECTION_NODE
                                    </p>
                                    <span
                                        :class="[
                                            'text-sm font-black uppercase tracking-tight block',
                                            isSelected(section.id)
                                                ? 'text-primary-foreground'
                                                : 'text-foreground/70 group-hover/card:text-primary',
                                        ]"
                                    >
                                        {{ section.name }}
                                    </span>
                                    <div
                                        v-if="section.has_password"
                                        class="flex items-center justify-center gap-1 pt-1"
                                    >
                                        <component
                                            :is="isSelected(section.id) ? LockOpen : Lock"
                                            class="w-3 h-3"
                                            :class="
                                                isSelected(section.id)
                                                    ? 'text-primary-foreground/80'
                                                    : 'text-muted-foreground/50'
                                            "
                                        />
                                        <span
                                            class="text-[9px] font-bold uppercase tracking-wider"
                                            :class="
                                                isSelected(section.id)
                                                    ? 'text-primary-foreground/80'
                                                    : 'text-muted-foreground/50'
                                            "
                                        >
                                            {{ isSelected(section.id) ? 'Unlocked' : 'Locked — tap to enter password' }}
                                        </span>
                                    </div>
                                </div>

                                <div v-if="isSelected(section.id)" class="absolute top-2 right-3">
                                    <div
                                        class="w-1.5 h-1.5 rounded-full bg-primary-foreground animate-ping opacity-75"
                                    ></div>
                                </div>
                            </button>

                            <!-- BACK FACE -->
                            <div
                                class="card-face card-back absolute inset-0 flex flex-col justify-center gap-2 p-4 rounded-2xl border border-primary/50 bg-muted/50 overflow-hidden"
                            >
                                <div class="flex items-center justify-center gap-1.5">
                                    <Lock class="w-3 h-3 text-primary/70" />
                                    <p
                                        class="text-[9px] font-black uppercase tracking-[0.25em] text-primary/70 font-mono truncate"
                                    >
                                        {{ section.name }}
                                    </p>
                                </div>
                                <Input
                                    :id="`section-password-input-${section.id}`"
                                    v-model="sectionPasswords[section.id]"
                                    :tabindex="flippedId === section.id ? 0 : -1"
                                    type="password"
                                    autocomplete="off"
                                    placeholder="Section password"
                                    class="h-9 text-sm"
                                    @keyup.enter="confirmPassword(section)"
                                    @keyup.esc="cancelPassword(section)"
                                />
                                <InputError
                                    :message="
                                        passwordErrors[section.id] ||
                                        (form.errors as Record<string, string>)[`section_passwords.${section.id}`]
                                    "
                                />
                                <div class="flex gap-2 pt-1">
                                    <Button
                                        type="button"
                                        size="sm"
                                        variant="outline"
                                        class="flex-1 h-8 text-[11px]"
                                        :tabindex="flippedId === section.id ? 0 : -1"
                                        @click="cancelPassword(section)"
                                    >
                                        <X class="w-3 h-3 mr-1" /> Cancel
                                    </Button>
                                    <Button
                                        type="button"
                                        size="sm"
                                        class="flex-1 h-8 text-[11px]"
                                        :disabled="
                                            !sectionPasswords[section.id] ||
                                            verifyingId === section.id
                                        "
                                        :tabindex="flippedId === section.id ? 0 : -1"
                                        @click="confirmPassword(section)"
                                    >
                                        <Loader2
                                            v-if="verifyingId === section.id"
                                            class="w-3 h-3 mr-1 animate-spin"
                                        />
                                        <Check v-else class="w-3 h-3 mr-1" />
                                        {{ verifyingId === section.id ? 'Checking' : 'Unlock' }}
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <p
                    v-if="sections.length === 0"
                    class="text-xs text-destructive mt-4 font-medium italic text-center"
                >
                    No sections available. Please contact your admin.
                </p>
            </div>

            <DialogFooter class="relative z-0 pt-2 flex flex-col sm:items-center">
                <Button
                    @click="submit"
                    class="w-full sm:max-w-sm h-12 sm:h-14 text-sm sm:text-base font-black uppercase tracking-wider shadow-lg shadow-primary/20 transition-all hover:translate-y-[-2px] active:translate-y-[0] disabled:opacity-50"
                    :disabled="
                        selectedSections.length === 0 ||
                        !allPasswordsFilled ||
                        flippedId !== null ||
                        form.processing
                    "
                >
                    <template v-if="form.processing">
                        <Loader2 class="mr-2 h-5 w-5 animate-spin" />
                        Initializing...
                    </template>
                    <template v-else>
                        <Check class="mr-2 h-5 w-5" />
                        Confirm &amp; Enter Dashboard
                    </template>
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>

<style scoped>
.card-perspective {
    perspective: 1200px;
}
.card-inner {
    transform-style: preserve-3d;
    will-change: transform;
}
.card-face {
    backface-visibility: hidden;
    -webkit-backface-visibility: hidden;
}
.card-back {
    transform: rotateY(180deg);
}
</style>
