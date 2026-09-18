<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AuthCard from '@/layouts/auth/AuthCardLayout.vue';
import { withForm } from '@/lib/route-helpers';
import { logout } from '@/routes';
import { send } from '@/routes/verification';

defineProps<{
    status?: string;
}>();

defineOptions({ layout: AuthCard });
</script>

<template>
    <Head title="Email verification" />

    <div class="text-center">
        <h1
            class="text-2xl leading-tight font-bold tracking-tight text-foreground"
        >
            Verify email
        </h1>
        <p
            class="mx-auto mt-1.5 max-w-xs text-sm font-normal text-muted-foreground"
        >
            Please verify your email address by clicking on the link we just
            emailed to you.
        </p>
    </div>

    <div
        v-if="status === 'verification-link-sent'"
        class="mb-4 text-center text-sm font-medium text-emerald-600 dark:text-emerald-400"
    >
        A new verification link has been sent to the email address you provided
        during registration.
    </div>

    <Form
        v-bind="withForm(send).form()"
        class="space-y-6 text-center"
        v-slot="{ processing }"
    >
        <Button
            type="submit"
            :disabled="processing"
            variant="secondary"
            :tabindex="1"
        >
            <Spinner v-if="processing" class="mr-2" />
            {{ processing ? 'Sending...' : 'Resend verification email' }}
        </Button>

        <TextLink :href="logout()" as="button" class="mx-auto block text-sm">
            Log out
        </TextLink>
    </Form>
</template>
