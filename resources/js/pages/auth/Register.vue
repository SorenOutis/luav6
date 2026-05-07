<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AnimatedInput from '@/components/ui/input/AnimatedInput.vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthBase from '@/layouts/AuthLayout.vue';
import { login } from '@/routes';
import { store } from '@/routes/register';

defineOptions({ layout: AuthBase });

const submitting = ref(false);
const onSubmit = () => {
    submitting.value = true;
};
</script>

<template>
    <Head title="Register" />

    <div class="space-y-3">
        <h1 class="text-3xl sm:text-4xl font-black uppercase tracking-tighter text-foreground leading-tight">
            Create an account
        </h1>
        <p class="text-sm text-muted-foreground/60 font-medium tracking-wide max-w-xs">
            Enter your details below to create your account
        </p>
    </div>

        <Form
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
                            <Dialog>
                                <DialogTrigger as-child>
                                    <button
                                        type="button"
                                        class="ml-1 inline underline decoration-border underline-offset-4 transition-colors hover:text-foreground"
                                    >
                                        Terms and Conditions
                                    </button>
                                </DialogTrigger>
                                <DialogContent
                                    class="w-[95vw] max-w-2xl border-border/40 bg-background/95 p-0 backdrop-blur-xl"
                                >
                                    <DialogHeader
                                        class="border-b border-border/40 px-5 py-4 sm:px-6"
                                    >
                                        <DialogTitle
                                            class="text-left text-lg font-black tracking-tight uppercase sm:text-2xl"
                                        >
                                            Terms and Conditions
                                        </DialogTitle>
                                        <DialogDescription
                                            class="text-left text-xs sm:text-sm"
                                        >
                                            Effective date: April 17, 2026.
                                        </DialogDescription>
                                    </DialogHeader>
                                    <div
                                        class="max-h-[70vh] space-y-5 overflow-y-auto px-5 py-4 text-sm leading-6 text-muted-foreground sm:px-6"
                                    >
                                        <section>
                                            <h3
                                                class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                                            >
                                                1. Account Responsibility
                                            </h3>
                                            <p class="mt-1">
                                                You are responsible for
                                                maintaining the confidentiality
                                                of your account credentials and
                                                for activity under your account.
                                            </p>
                                        </section>
                                        <section>
                                            <h3
                                                class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                                            >
                                                2. Acceptable Use
                                            </h3>
                                            <p class="mt-1">
                                                You agree not to abuse, disrupt,
                                                scrape, reverse engineer, or
                                                attempt unauthorized access to
                                                platform services, data, or
                                                accounts.
                                            </p>
                                        </section>
                                        <section>
                                            <h3
                                                class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                                            >
                                                3. Content and Conduct
                                            </h3>
                                            <p class="mt-1">
                                                You retain ownership of your
                                                submitted content, but grant LUA
                                                V6 permission to process and
                                                display it to provide core
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
                                                We may update, suspend, or
                                                discontinue parts of the service
                                                without notice, and
                                                uninterrupted uptime is not
                                                guaranteed.
                                            </p>
                                        </section>
                                        <section>
                                            <h3
                                                class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                                            >
                                                5. Limitation of Liability
                                            </h3>
                                            <p class="mt-1">
                                                The platform is provided on an
                                                "as is" basis and is not liable
                                                for indirect, incidental, or
                                                consequential damages.
                                            </p>
                                        </section>
                                        <section>
                                            <h3
                                                class="text-sm font-bold tracking-wide text-foreground uppercase sm:text-base"
                                            >
                                                6. Changes to Terms
                                            </h3>
                                            <p class="mt-1">
                                                These terms may be revised from
                                                time to time. Continued use
                                                after updates means you accept
                                                the revised terms.
                                            </p>
                                        </section>
                                    </div>
                                </DialogContent>
                            </Dialog>
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
                    {{ (processing || submitting) ? 'Creating account...' : 'Create account' }}
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
</template>
