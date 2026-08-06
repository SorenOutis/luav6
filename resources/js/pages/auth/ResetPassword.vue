<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AnimatedInput from '@/components/ui/input/AnimatedInput.vue';
import { Spinner } from '@/components/ui/spinner';
import AuthCard from '@/layouts/auth/AuthCardLayout.vue';
import { withForm } from '@/lib/route-helpers';
import { update } from '@/routes/password';

const props = defineProps<{
    token: string;
    email: string;
}>();

const inputEmail = ref(props.email);

defineOptions({ layout: AuthCard });
</script>

<template>
    <Head title="Reset password" />

    <div class="text-center">
        <h1
            class="text-2xl leading-tight font-bold tracking-tight text-foreground"
        >
            Reset password
        </h1>
        <p
            class="mx-auto mt-1.5 max-w-xs text-sm font-normal text-muted-foreground"
        >
            Please enter your new password below
        </p>
    </div>

    <Form
        v-bind="withForm(update).form()"
        :transform="(data) => ({ ...data, token, email })"
        :reset-on-success="['password', 'password_confirmation']"
        v-slot="{ errors, processing }"
    >
        <div class="grid gap-5">
            <div class="grid gap-2">
                <AnimatedInput
                    id="email"
                    v-model="inputEmail"
                    type="email"
                    name="email"
                    autocomplete="email"
                    readonly
                    :tabindex="1"
                    label="Email"
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <AnimatedInput
                    id="password"
                    type="password"
                    name="password"
                    autocomplete="new-password"
                    autofocus
                    :tabindex="2"
                    label="Password"
                />
                <InputError :message="errors.password" />
            </div>

            <div class="grid gap-2">
                <AnimatedInput
                    id="password_confirmation"
                    type="password"
                    name="password_confirmation"
                    autocomplete="new-password"
                    :tabindex="3"
                    label="Confirm password"
                />
                <InputError :message="errors.password_confirmation" />
            </div>

            <Button
                type="submit"
                class="mt-4 w-full"
                :tabindex="4"
                :disabled="processing"
                data-test="reset-password-button"
            >
                <Spinner v-if="processing" class="mr-2" />
                {{ processing ? 'Resetting...' : 'Reset password' }}
            </Button>
        </div>
    </Form>
</template>
