<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import AnimatedInput from '@/components/ui/input/AnimatedInput.vue';
import { Spinner } from '@/components/ui/spinner';
import AuthCard from '@/layouts/auth/AuthCardLayout.vue';
import { withForm } from '@/lib/route-helpers';
import { login } from '@/routes';
import { email } from '@/routes/password';

defineProps<{
    status?: string;
}>();

defineOptions({ layout: AuthCard });
</script>

<template>
    <Head title="Forgot password" />

    <div class="text-center">
        <h1
            class="text-2xl leading-tight font-bold tracking-tight text-foreground"
        >
            Forgot password
        </h1>
        <p
            class="mx-auto mt-1.5 max-w-xs text-sm font-normal text-muted-foreground"
        >
            Enter your email to receive a password reset link
        </p>
    </div>

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-emerald-600 dark:text-emerald-400"
    >
        {{ status }}
    </div>

    <div class="space-y-6">
        <Form v-bind="withForm(email).form()" v-slot="{ errors, processing }">
            <div class="grid gap-2">
                <AnimatedInput
                    id="email"
                    type="email"
                    name="email"
                    autocomplete="off"
                    autofocus
                    :tabindex="1"
                    label="Email address"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="mt-6 flex items-center justify-start">
                <Button
                    type="submit"
                    class="w-full"
                    :tabindex="2"
                    :disabled="processing"
                    data-test="email-password-reset-link-button"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? 'Sending link...' : 'Email password reset link' }}
                </Button>
            </div>
        </Form>

        <div class="space-x-1 text-center text-sm text-muted-foreground">
            <span>Or, return to</span>
            <TextLink :href="login()" :tabindex="3">log in</TextLink>
        </div>
    </div>
</template>
