<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import gsap from 'gsap';
import {
    Check,
    ChevronLeft,
    ChevronRight,
    Eye,
    EyeOff,
    X,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import InputError from '@/components/InputError.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import SocialAuthButtons from '@/components/SocialAuthButtons.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { DialogDescription, DialogTitle } from '@/components/ui/dialog';
import AnimatedInput from '@/components/ui/input/AnimatedInput.vue';
import { Label } from '@/components/ui/label';
import { Progress } from '@/components/ui/progress';
import { Spinner } from '@/components/ui/spinner';
import AuthCard from '@/layouts/auth/AuthCardLayout.vue';
import { isLowEndDeviceSignal } from '@/lib/device';
import { withForm } from '@/lib/route-helpers';
import { login } from '@/routes';
import { store } from '@/routes/register';

type SocialProvider = {
    name: string;
    label: string;
};

withDefaults(
    defineProps<{
        registrationEnabled: boolean;
        registrationDisabledMessage: string;
        socialProviders?: SocialProvider[];
    }>(),
    {
        socialProviders: () => [],
    },
);

defineOptions({ layout: AuthCard });

// ─────────────────────────────────────────────────────────────
// Wizard state
// ─────────────────────────────────────────────────────────────
const steps = [
    { key: 'details', label: 'Details', title: 'Your details and password' },
    { key: 'confirm', label: 'Confirm', title: 'Review & confirm' },
] as const;

const currentStep = ref(0);
const stepDirection = ref<'forward' | 'backward'>('forward');
const showTermsModal = ref(false);
const showPassword = ref(false);
const submitting = ref(false);

// Every field lives here so values survive step changes and the
// single final POST submits the complete form (the Inertia <Form>
// reads FormData from the DOM, so all steps stay mounted — hidden
// via CSS, never unmounted).
const formData = reactive({
    first_name: '',
    middle_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
    terms: false,
});

// "Touched" gates when validation messages appear. Once a field has
// been interacted with (blurred, typed into, or a step was submitted),
// its errors stay visible until the field becomes valid again.
const touched = reactive<Record<string, boolean>>({
    first_name: false,
    middle_name: false,
    last_name: false,
    email: false,
    password: false,
    password_confirmation: false,
    terms: false,
});

const stepFields: Record<
    number,
    Array<
        | 'first_name'
        | 'middle_name'
        | 'last_name'
        | 'email'
        | 'password'
        | 'password_confirmation'
        | 'terms'
    >
> = {
    0: [
        'first_name',
        'middle_name',
        'last_name',
        'email',
        'password',
        'password_confirmation',
    ],
    1: ['terms'],
};

// ─────────────────────────────────────────────────────────────
// Validation — mirrors app/Concerns/*ValidationRules.php
// ─────────────────────────────────────────────────────────────
const EMAIL_PATTERN = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

const firstNameValid = computed(() => {
    const first = formData.first_name.trim();
    return first.length >= 2 && first.length <= 255;
});

// Middle name is optional — valid when empty or within the length limit.
const middleNameValid = computed(
    () => formData.middle_name.trim().length <= 255,
);

const lastNameValid = computed(() => {
    const last = formData.last_name.trim();
    return last.length >= 2 && last.length <= 255;
});

const emailValid = computed(() => EMAIL_PATTERN.test(formData.email.trim()));

// Mirrors Laravel's default Password rule: 8+ chars with letters & numbers.
const passwordValid = computed(
    () =>
        formData.password.length >= 8 &&
        /[a-zA-Z]/.test(formData.password) &&
        /\d/.test(formData.password),
);

const confirmValid = computed(
    () =>
        formData.password_confirmation.length > 0 &&
        formData.password_confirmation === formData.password,
);

const consentValid = computed(() => formData.terms);

const canProceed = computed(() => {
    if (currentStep.value === 0)
        return (
            firstNameValid.value &&
            middleNameValid.value &&
            lastNameValid.value &&
            emailValid.value &&
            passwordValid.value &&
            confirmValid.value
        );
    if (currentStep.value === 1) return consentValid.value;
    return true;
});

// Password strength meter — segment criteria mirror Laravel's default rule
// (length, letter, number) plus a symbol bonus, so a valid password always
// scores at least 3/4.
const passwordScore = computed(() => {
    const value = formData.password;
    let score = 0;
    if (value.length >= 8) score++;
    if (/[a-zA-Z]/.test(value)) score++;
    if (/\d/.test(value)) score++;
    if (/[^A-Za-z0-9]/.test(value)) score++;
    return score;
});

const meterLabel = computed(() => {
    const score = passwordScore.value;
    if (score <= 1) return 'Weak';
    if (score === 2) return 'Fair';
    if (score === 3) return 'Good';
    return 'Strong';
});

const meterLabelClass = computed(() => {
    const score = passwordScore.value;
    if (score <= 1) return 'text-red-500';
    if (score === 2) return 'text-amber-500';
    return 'text-emerald-500';
});

const meterSegmentClass = (segment: number): string => {
    const score = passwordScore.value;
    if (segment > score) return 'bg-muted-foreground/20';
    if (score <= 1) return 'bg-red-500';
    if (score === 2) return 'bg-amber-500';
    if (score >= 3) return 'bg-emerald-500';
    return 'bg-emerald-500';
};

// Live per-field errors — derived from touched state + current validity.
const liveErrors = computed<Record<string, string>>(() => {
    const errors: Record<string, string> = {};
    if (touched.first_name && !firstNameValid.value) {
        errors.first_name = 'Please enter your first name (2-255 characters).';
    }
    if (touched.last_name && !lastNameValid.value) {
        errors.last_name = 'Please enter your last name (2-255 characters).';
    }
    if (touched.middle_name && !middleNameValid.value) {
        errors.middle_name = 'Middle name must be 255 characters or less.';
    }
    if (touched.email) {
        if (!formData.email.trim()) {
            errors.email = 'Please enter your email address.';
        } else if (!emailValid.value) {
            errors.email = 'Please enter a valid email address.';
        }
    }
    if (touched.password && !passwordValid.value) {
        errors.password =
            'Password must be at least 8 characters and contain letters and numbers.';
    }
    if (touched.password_confirmation && !confirmValid.value) {
        errors.password_confirmation =
            formData.password_confirmation.length === 0
                ? 'Please confirm your password.'
                : 'Passwords do not match.';
    }
    if (touched.terms && !consentValid.value) {
        errors.terms =
            'You must accept the Terms and Conditions to create an account.';
    }
    return errors;
});

// ─────────────────────────────────────────────────────────────
// Step rendering + navigation
// ─────────────────────────────────────────────────────────────
const stepsContainer = ref<HTMLElement | null>(null);
const stepSections = ref<Record<number, HTMLElement | null>>({});

const progressValue = computed(
    () => (currentStep.value / (steps.length - 1)) * 100,
);

/**
 * All sections stay mounted (FormData is read from the DOM on submit),
 * so inactive steps are hidden with CSS: absolutely positioned, faded,
 * and invisible. The active step is `relative` and defines the height.
 */
const stepClass = (index: number): string => {
    const base =
        'transition-all duration-500 ease-[cubic-bezier(0.16,1,0.3,1)] will-change-transform';
    if (index === currentStep.value) {
        return `${base} relative z-10 visible translate-x-0 opacity-100`;
    }
    const isPast = index < currentStep.value;
    const exitsLeft = isPast === (stepDirection.value === 'forward');
    return `${base} pointer-events-none invisible absolute inset-x-0 top-0 z-0 opacity-0 ${
        exitsLeft ? '-translate-x-4' : 'translate-x-4'
    }`;
};

const focusFirstInput = (step: number): void => {
    if (step >= steps.length - 1) return;
    nextTick(() => {
        stepSections.value[step]
            ?.querySelector<HTMLInputElement>('input')
            ?.focus();
    });
};

const markTouched = (step: number): void => {
    stepFields[step].forEach((field) => (touched[field] = true));
};

const goNext = (): void => {
    if (currentStep.value >= steps.length - 1) return;
    markTouched(currentStep.value);
    if (!canProceed.value) return;
    stepDirection.value = 'forward';
    currentStep.value++;
    focusFirstInput(currentStep.value);
};

const goBack = (): void => {
    if (currentStep.value === 0) return;
    stepDirection.value = 'backward';
    currentStep.value--;
    focusFirstInput(currentStep.value);
};

const jumpTo = (step: number): void => {
    if (step === currentStep.value) return;
    stepDirection.value = step < currentStep.value ? 'backward' : 'forward';
    currentStep.value = step;
    focusFirstInput(step);
};

// When the server rejects the final submission, jump back to the step
// that owns the offending field so the error is visible in context.
const handleSubmitError = (payload: unknown): void => {
    submitting.value = false;
    const errs = (payload ?? {}) as Record<string, unknown>;
    if (
        errs.first_name ||
        errs.middle_name ||
        errs.last_name ||
        errs.email ||
        errs.password ||
        errs.password_confirmation
    )
        jumpTo(0);
    else if (errs.terms) jumpTo(1);
};

const onFormSuccess = (): void => {
    submitting.value = false;
};

// ─────────────────────────────────────────────────────────────
// Animations (GSAP — subtle, respecting reduced motion)
// ─────────────────────────────────────────────────────────────
let gsapCtx: gsap.Context | null = null;

const prefersReducedMotion = (): boolean =>
    typeof window !== 'undefined' &&
    (window.matchMedia('(prefers-reduced-motion: reduce)').matches ||
        isLowEndDeviceSignal());

onMounted(() => {
    focusFirstInput(0);
    if (prefersReducedMotion()) return;

    gsapCtx = gsap.context(() => {
        const tl = gsap.timeline({ defaults: { ease: 'expo.out' } });

        tl.from(
            '.rs-node',
            { y: 12, opacity: 0, stagger: 0.06, duration: 0.5 },
            0.1,
        );
        tl.from(
            '.rs-connector',
            {
                scaleX: 0,
                opacity: 0,
                transformOrigin: 'left center',
                duration: 0.4,
                stagger: 0.06,
            },
            0.25,
        );
        tl.from('.rs-progress', { opacity: 0, y: 4, duration: 0.35 }, 0.45);
    }, stepsContainer.value ?? undefined);
});

watch(currentStep, (step) => {
    if (prefersReducedMotion()) return;
    gsap.from(`[data-rs-node="${step}"]`, {
        scale: 1.2,
        duration: 0.4,
        ease: 'back.out(2)',
    });
});

onBeforeUnmount(() => {
    gsapCtx?.revert();
    gsapCtx = null;
});
</script>

<template>
    <Head title="Register" />

    <div
        v-if="!registrationEnabled"
        class="rounded-lg border border-amber-500/20 bg-amber-500/10 p-4"
    >
        <div class="flex items-center gap-3 text-amber-500">
            <div class="rounded-md bg-amber-500/20 p-1">
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="16"
                    height="16"
                    viewBox="0 0 24 24"
                    fill="none"
                    stroke="currentColor"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"
                    class="lucide lucide-alert-triangle"
                >
                    <path
                        d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"
                    />
                    <path d="M12 9v4" />
                    <path d="M12 17h.01" />
                </svg>
            </div>
            <p class="text-sm font-semibold text-amber-500">
                Registration is currently closed
            </p>
        </div>
        <p class="mt-2 text-sm leading-relaxed text-muted-foreground">
            {{ registrationDisabledMessage }}
        </p>
        <div class="mt-4 border-t border-amber-500/10 pt-4">
            <p class="text-sm text-muted-foreground">
                Already have an account?
                <TextLink
                    :href="login()"
                    class="underline underline-offset-4"
                    :tabindex="7"
                    >Log in</TextLink
                >
            </p>
        </div>
    </div>

    <Form
        v-if="registrationEnabled"
        v-bind="withForm(store).form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        @submit="submitting = true"
        @error="handleSubmitError"
        @success="onFormSuccess"
    >
        <div class="text-center">
            <h1
                class="text-2xl leading-tight font-bold tracking-tight text-foreground"
            >
                Create your account
            </h1>
            <p
                class="mx-auto mt-1.5 max-w-xs text-sm font-normal text-muted-foreground"
            >
                Complete the steps below to get started
            </p>
        </div>

        <div
            v-if="errors.registration"
            class="rounded-lg border border-destructive/20 bg-destructive/10 p-3 text-sm font-medium text-destructive"
        >
            {{ errors.registration }}
        </div>

        <!-- ══════════════ Stepper ══════════════ -->
        <div ref="stepsContainer" class="space-y-3">
            <div
                class="flex items-center justify-between text-xs font-medium text-muted-foreground"
            >
                <span>Step {{ currentStep + 1 }} of {{ steps.length }}</span>
                <span class="text-foreground/80">
                    {{ steps[currentStep].title }}
                </span>
            </div>

            <div
                class="flex items-center gap-1.5"
                aria-label="Registration progress"
            >
                <template v-for="(step, index) in steps" :key="step.key">
                    <span
                        v-if="index > 0"
                        aria-hidden="true"
                        class="rs-connector h-px flex-1 transition-colors duration-500"
                        :class="
                            index <= currentStep ? 'bg-primary' : 'bg-border'
                        "
                    />
                    <button
                        type="button"
                        :data-rs-node="index"
                        class="rs-node group flex flex-col items-center gap-1.5"
                        :disabled="index >= currentStep"
                        :aria-current="
                            index === currentStep ? 'step' : undefined
                        "
                        @click="jumpTo(index)"
                    >
                        <span
                            class="flex h-8 w-8 items-center justify-center rounded-full border text-xs font-semibold transition-all duration-500"
                            :class="[
                                index < currentStep &&
                                    'border-primary bg-primary text-primary-foreground',
                                index === currentStep &&
                                    'border-primary bg-primary/10 text-primary',
                                index > currentStep &&
                                    'border-border text-muted-foreground/50',
                            ]"
                        >
                            <Check
                                v-if="index < currentStep"
                                class="h-3.5 w-3.5"
                            />
                            <span v-else>{{ index + 1 }}</span>
                        </span>
                        <span
                            class="hidden text-[10px] font-medium text-muted-foreground sm:block"
                            :class="
                                index === currentStep
                                    ? 'text-foreground'
                                    : index < currentStep
                                      ? 'text-primary'
                                      : ''
                            "
                        >
                            {{ step.label }}
                        </span>
                    </button>
                </template>
            </div>

            <Progress :value="progressValue" class="rs-progress h-1.5" />
        </div>

        <!-- ══════════════ Steps ══════════════ -->
        <div class="relative">
            <!-- Step 1 — Details & password -->
            <section
                :ref="(el) => (stepSections[0] = el as HTMLElement | null)"
                :aria-hidden="currentStep !== 0"
                :class="stepClass(0)"
            >
                <div class="grid gap-5">
                    <div class="grid gap-5 sm:grid-cols-2">
                        <div class="grid gap-2">
                            <AnimatedInput
                                id="first_name"
                                v-model="formData.first_name"
                                type="text"
                                required
                                :tabindex="1"
                                autocomplete="given-name"
                                name="first_name"
                                label="First name"
                                @blur="touched.first_name = true"
                                @keydown.enter.prevent="goNext"
                            />
                            <InputError
                                :message="
                                    liveErrors.first_name || errors.first_name
                                "
                            />
                        </div>

                        <div class="grid gap-2">
                            <AnimatedInput
                                id="last_name"
                                v-model="formData.last_name"
                                type="text"
                                required
                                :tabindex="2"
                                autocomplete="family-name"
                                name="last_name"
                                label="Last name"
                                @blur="touched.last_name = true"
                                @keydown.enter.prevent="goNext"
                            />
                            <InputError
                                :message="
                                    liveErrors.last_name || errors.last_name
                                "
                            />
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <AnimatedInput
                            id="middle_name"
                            v-model="formData.middle_name"
                            type="text"
                            :tabindex="3"
                            autocomplete="additional-name"
                            name="middle_name"
                            label="Middle name (optional)"
                            @blur="touched.middle_name = true"
                            @keydown.enter.prevent="goNext"
                        />
                        <InputError
                            :message="
                                liveErrors.middle_name || errors.middle_name
                            "
                        />
                    </div>

                    <div class="grid gap-2">
                        <AnimatedInput
                            id="email"
                            v-model="formData.email"
                            type="email"
                            required
                            :tabindex="4"
                            autocomplete="email"
                            name="email"
                            label="Email address"
                            @blur="touched.email = true"
                            @keydown.enter.prevent="goNext"
                        />
                        <InputError
                            :message="liveErrors.email || errors.email"
                        />
                    </div>

                    <div class="grid gap-2">
                        <div class="relative">
                            <AnimatedInput
                                id="password"
                                v-model="formData.password"
                                :type="showPassword ? 'text' : 'password'"
                                required
                                :tabindex="5"
                                autocomplete="new-password"
                                name="password"
                                label="Password"
                                @blur="touched.password = true"
                                @keydown.enter.prevent="goNext"
                            />
                            <button
                                type="button"
                                :aria-label="
                                    showPassword
                                        ? 'Hide password'
                                        : 'Show password'
                                "
                                class="absolute right-0 bottom-2.5 text-muted-foreground/50 transition-colors hover:text-foreground"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOff v-if="showPassword" class="h-4 w-4" />
                                <Eye v-else class="h-4 w-4" />
                            </button>
                        </div>
                        <InputError
                            :message="liveErrors.password || errors.password"
                        />

                        <div
                            v-if="formData.password.length > 0"
                            class="mt-1.5 space-y-1.5"
                        >
                            <div class="flex gap-1.5">
                                <div
                                    v-for="segment in 4"
                                    :key="segment"
                                    class="h-1 flex-1 rounded-full transition-all duration-300"
                                    :class="meterSegmentClass(segment)"
                                />
                            </div>
                            <p
                                class="text-xs font-medium"
                                :class="meterLabelClass"
                            >
                                {{ meterLabel }} password
                            </p>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <AnimatedInput
                            id="password_confirmation"
                            v-model="formData.password_confirmation"
                            :type="showPassword ? 'text' : 'password'"
                            required
                            :tabindex="6"
                            autocomplete="new-password"
                            name="password_confirmation"
                            label="Confirm password"
                            @blur="touched.password_confirmation = true"
                            @keydown.enter.prevent="goNext"
                        />
                        <InputError
                            :message="
                                liveErrors.password_confirmation ||
                                errors.password_confirmation
                            "
                        />
                        <p
                            v-if="formData.password_confirmation.length > 0"
                            class="flex items-center gap-1.5 text-xs font-medium"
                            :class="
                                confirmValid
                                    ? 'text-emerald-600 dark:text-emerald-400'
                                    : 'text-red-600 dark:text-red-400'
                            "
                        >
                            <Check v-if="confirmValid" class="h-3.5 w-3.5" />
                            <X v-else class="h-3.5 w-3.5" />
                            {{
                                confirmValid
                                    ? 'Passwords match'
                                    : "Passwords don't match yet"
                            }}
                        </p>
                    </div>

                    <p class="text-xs text-muted-foreground">
                        Use at least 8 characters with letters and numbers.
                    </p>
                </div>
            </section>

            <!-- Step 2 — Review & confirm -->
            <section
                :ref="(el) => (stepSections[1] = el as HTMLElement | null)"
                :aria-hidden="currentStep !== 1"
                :class="stepClass(1)"
            >
                <div class="grid gap-5">
                    <div
                        class="overflow-hidden rounded-lg border border-border/60"
                    >
                        <div
                            class="border-b border-border/60 bg-muted/30 px-4 py-2.5"
                        >
                            <p class="text-sm font-semibold text-foreground">
                                Review your details
                            </p>
                        </div>
                        <div class="divide-y divide-border/60">
                            <div
                                class="flex items-center justify-between gap-4 px-4 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="text-xs text-muted-foreground">
                                        Full name
                                    </p>
                                    <p
                                        class="truncate text-sm font-semibold text-foreground"
                                    >
                                        {{
                                            [
                                                formData.first_name,
                                                formData.middle_name,
                                                formData.last_name,
                                            ]
                                                .filter(Boolean)
                                                .join(' ') || '—'
                                        }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-xs font-medium text-primary transition-opacity hover:opacity-70"
                                    @click="jumpTo(0)"
                                >
                                    Edit
                                </button>
                            </div>
                            <div
                                class="flex items-center justify-between gap-4 px-4 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="text-xs text-muted-foreground">
                                        Email address
                                    </p>
                                    <p
                                        class="truncate text-sm font-semibold text-foreground"
                                    >
                                        {{ formData.email || '—' }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-xs font-medium text-primary transition-opacity hover:opacity-70"
                                    @click="jumpTo(0)"
                                >
                                    Edit
                                </button>
                            </div>
                            <div
                                class="flex items-center justify-between gap-4 px-4 py-3"
                            >
                                <div class="min-w-0">
                                    <p class="text-xs text-muted-foreground">
                                        Password
                                    </p>
                                    <p
                                        class="text-sm font-semibold tracking-widest text-foreground"
                                    >
                                        ••••••••••
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="shrink-0 text-xs font-medium text-primary transition-opacity hover:opacity-70"
                                    @click="jumpTo(0)"
                                >
                                    Edit
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="grid gap-2">
                        <div class="flex items-start gap-3">
                            <input
                                id="terms"
                                v-model="formData.terms"
                                name="terms"
                                type="checkbox"
                                value="1"
                                required
                                :tabindex="1"
                                class="mt-0.5 h-4 w-4 shrink-0 rounded-[4px] border border-input bg-transparent text-primary focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
                                @change="touched.terms = true"
                            />
                            <div class="text-sm text-muted-foreground">
                                <Label
                                    for="terms"
                                    class="inline cursor-pointer text-sm text-muted-foreground"
                                >
                                    I accept the
                                </Label>
                                <button
                                    type="button"
                                    @click="showTermsModal = true"
                                    class="ml-1 inline underline underline-offset-4 transition-colors hover:text-foreground"
                                >
                                    Terms and Conditions
                                </button>
                            </div>
                        </div>
                        <InputError
                            :message="liveErrors.terms || errors.terms"
                        />
                    </div>

                    <p class="text-center text-xs text-muted-foreground">
                        Clicking "Create account" will finalize your
                        registration.
                    </p>
                </div>
            </section>
        </div>

        <!-- ══════════════ Navigation ══════════════ -->
        <!-- Three-column grid keeps the primary action centered on every step,
             with the optional Back button pinned to the left. -->
        <div class="grid grid-cols-[1fr_auto_1fr] items-center pt-1">
            <div class="justify-self-start">
                <Button
                    v-if="currentStep > 0"
                    type="button"
                    variant="outline"
                    class="px-4"
                    @click="goBack"
                >
                    <ChevronLeft class="h-4 w-4" />
                    Back
                </Button>
            </div>

            <div>
                <Button
                    v-if="currentStep < steps.length - 1"
                    type="button"
                    class="px-6"
                    @click="goNext"
                >
                    Continue
                    <ChevronRight class="h-4 w-4" />
                </Button>
                <Button
                    v-else
                    type="submit"
                    :disabled="processing || submitting"
                    class="px-6"
                    data-test="register-user-button"
                >
                    <Spinner v-if="processing || submitting" class="mr-2" />
                    {{
                        processing || submitting
                            ? 'Creating account...'
                            : 'Create account'
                    }}
                </Button>
            </div>

            <div></div>
        </div>

        <SocialAuthButtons
            :providers="socialProviders"
            action="Sign up"
            divider-label="or sign up with"
            class="mt-2"
        />

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink :href="login()" class="underline underline-offset-4"
                >Log in</TextLink
            >
        </div>
    </Form>

    <!-- Terms and Conditions Modal -->
    <ResponsiveModal
        :open="showTermsModal"
        custom-header
        title="Terms and Conditions"
        description="Effective date: April 17, 2026."
        content-class="w-[95vw] max-w-2xl border-border/40 bg-background/95 backdrop-blur-xl"
        @close="showTermsModal = false"
    >
        <template #header>
            <DialogTitle
                class="text-left text-lg font-black tracking-tight uppercase sm:text-2xl"
            >
                Terms and Conditions
            </DialogTitle>
            <DialogDescription class="text-left text-xs sm:text-sm">
                Effective date: April 17, 2026.
            </DialogDescription>
        </template>

        <div
            class="max-h-[70vh] space-y-5 overflow-y-auto py-4 text-sm leading-6 text-muted-foreground"
        >
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    1. Account Responsibility
                </h3>
                <p class="mt-1">
                    You are responsible for maintaining the confidentiality of
                    your account credentials and for activity under your
                    account.
                </p>
            </section>
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    2. Acceptable Use
                </h3>
                <p class="mt-1">
                    You agree not to abuse, disrupt, scrape, reverse engineer,
                    or attempt unauthorized access to platform services, data,
                    or accounts.
                </p>
            </section>
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    3. Content and Conduct
                </h3>
                <p class="mt-1">
                    You retain ownership of your submitted content, but grant
                    LUA V6 permission to process and display it to provide core
                    features.
                </p>
            </section>
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    4. Availability
                </h3>
                <p class="mt-1">
                    We may update, suspend, or discontinue parts of the service
                    without notice, and uninterrupted uptime is not guaranteed.
                </p>
            </section>
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    5. Limitation of Liability
                </h3>
                <p class="mt-1">
                    The platform is provided on an "as is" basis and is not
                    liable for indirect, incidental, or consequential damages.
                </p>
            </section>
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    6. Changes to Terms
                </h3>
                <p class="mt-1">
                    These terms may be revised from time to time. Continued use
                    after updates means you accept the revised terms.
                </p>
            </section>
        </div>
    </ResponsiveModal>
</template>
