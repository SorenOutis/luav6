<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineProps<{
    status?: string;
}>();

defineOptions({ layout: AuthLayout });
</script>

<template>
    <Head title="Email verification" />

    <div class="space-y-3">
        <h1
            class="text-3xl leading-tight font-black tracking-tighter text-foreground uppercase sm:text-4xl"
        >
            Verify email
        </h1>
        <p
            class="max-w-xs text-sm font-medium tracking-wide text-muted-foreground/60"
        >
            Please verify your email address by clicking on the link we just
            emailed to you.
        </p>
    </div>

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-green-600"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </div>

    <Form
        v-bind="send.form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button :disabled="processing" variant="secondary">
            <Spinner v-if="processing" />
            Resend verification email
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
