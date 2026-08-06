<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AnimatedInput from '@/components/ui/input/AnimatedInput.vue';
import { Spinner } from '@/components/ui/spinner';
import AuthCard from '@/layouts/auth/AuthCardLayout.vue';
import { withForm } from '@/lib/route-helpers';
import { store } from '@/routes/password/confirm';

defineOptions({ layout: AuthCard });
</script>

<template>
    <Head title="Confirm password" />

    <div class="text-center">
        <h1
            class="text-2xl leading-tight font-bold tracking-tight text-foreground"
        >
            Confirm your password
        </h1>
        <p
            class="mx-auto mt-1.5 max-w-xs text-sm font-normal text-muted-foreground"
        >
            This is a secure area of the application. Please confirm your
            password before continuing.
        </p>
    </div>

    <Form
        v-bind="withForm(store).form()"
        reset-on-success
        v-slot="{ errors, processing }"
    >
        <div class="space-y-6">
            <div class="grid gap-2">
                <AnimatedInput
                    id="password"
                    type="password"
                    name="password"
                    required
                    autocomplete="current-password"
                    autofocus
                    :tabindex="1"
                    label="Password"
                />

                <InputError :message="errors.password" />
            </div>

            <div class="flex items-center">
                <Button
                    type="submit"
                    class="w-full"
                    :tabindex="2"
                    :disabled="processing"
                    data-test="confirm-password-button"
                >
                    <Spinner v-if="processing" class="mr-2" />
                    {{ processing ? 'Confirming...' : 'Confirm password' }}
                </Button>
            </div>
        </div>
    </Form>
</template>
