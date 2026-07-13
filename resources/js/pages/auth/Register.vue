<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import {
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import AnimatedInput from '@/components/ui/input/AnimatedInput.vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineProps<{
    registrationEnabled: boolean;
    registrationDisabledMessage: string;
}>();

defineOptions({ layout: AuthBase });

const submitting = ref(false);
const showTermsModal = ref(false);

const onSubmit = () => {
    submitting.value = true;
};
</script>

<template>
    <Head title="Register" />

    <div class="space-y-3">
        <h1
            class="text-3xl leading-tight font-black tracking-tighter text-foreground uppercase sm:text-4xl"
        >
            Create an account
        </h1>
        <p
            class="max-w-xs text-sm font-medium tracking-wide text-muted-foreground/60"
        >
            Enter your details below to create your account
        </p>
    </div>

    <div
        v-if="!registrationEnabled"
        class="mt-6 rounded-lg border border-amber-500/20 bg-amber-500/10 p-4"
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
            <p class="text-sm font-semibold tracking-tight uppercase">
                Registration Closed
            </p>
        </div>
        <p class="mt-2 text-sm leading-relaxed font-medium text-amber-500/80">
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
        v-bind="store.form()"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
        class="flex flex-col gap-6"
        @submit="onSubmit"
        @error="submitting = false"
        @success="submitting = false"
    >
        <div class="grid gap-6">
            <div class="grid gap-2">
                <AnimatedInput
                    id="name"
                    type="text"
                    required
                    autofocus
                    :tabindex="1"
                    autocomplete="name"
                    name="name"
                    label="Full name"
                />
                <InputError :message="errors.name" />
            </div>

            <div class="grid gap-2">
                <AnimatedInput
                    id="email"
                    type="email"
                    required
                    :tabindex="2"
                    autocomplete="email"
                    name="email"
                    label="Email address"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <AnimatedInput
                    id="password"
                    type="password"
                    required
                    :tabindex="3"
                    autocomplete="new-password"
                    name="password"
                    label="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <AnimatedInput
                    id="password_confirmation"
                    type="password"
                    required
                    :tabindex="4"
                    autocomplete="new-password"
                    name="password_confirmation"
                    label="Confirm password"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <div class="grid gap-2">
                <div class="flex items-start gap-3">
                    <input
                        id="terms"
                        name="terms"
                        type="checkbox"
                        value="1"
                        required
                        :tabindex="5"
                        class="mt-0.5 h-4 w-4 shrink-0 rounded-[4px] border border-input bg-transparent text-primary focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 focus-visible:outline-none"
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
                            class="ml-1 inline underline decoration-border underline-offset-4 transition-colors hover:text-foreground"
                        >
                            Terms and Conditions
                        </button>
                    </div>
                </div>
                <InputError :message="errors.terms" />
            </div>

            <Button
                type="submit"
                class="mt-2 w-full"
                :tabindex="6"
                :disabled="processing || submitting"
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

        <div class="text-center text-sm text-muted-foreground">
            Already have an account?
            <TextLink
                :href="login()"
                class="underline underline-offset-4"
                :tabindex="7"
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

        <div class="max-h-[70vh] space-y-5 overflow-y-auto py-4 text-sm leading-6 text-muted-foreground">
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    1. Account Responsibility
                </h3>
                <p class="mt-1">
                    You are responsible for maintaining the confidentiality of your account
                    credentials and for activity under your account.
                </p>
            </section>
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    2. Acceptable Use
                </h3>
                <p class="mt-1">
                    You agree not to abuse, disrupt, scrape, reverse engineer, or attempt
                    unauthorized access to platform services, data, or accounts.
                </p>
            </section>
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    3. Content and Conduct
                </h3>
                <p class="mt-1">
                    You retain ownership of your submitted content, but grant LUA V6
                    permission to process and display it to provide core features.
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
                    The platform is provided on an &quot;as is&quot; basis and is not liable for
                    indirect, incidental, or consequential damages.
                </p>
            </section>
            <section>
                <h3
                    class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                >
                    6. Changes to Terms
                </h3>
                <p class="mt-1">
                    These terms may be revised from time to time. Continued use after updates
                    means you accept the revised terms.
                </p>
            </section>
        </div>
    </ResponsiveModal>
</template>
