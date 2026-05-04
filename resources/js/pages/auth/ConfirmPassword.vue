<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/AuthLayout.vue';
import { store } from '@/routes/password/confirm';

defineOptions({ layout: AuthLayout });
</script>

<template>
    <Head title="Confirm password" />

    <div class="space-y-3">
        <h1 class="text-3xl sm:text-4xl font-black uppercase tracking-tighter text-foreground leading-tight">
            Confirm your password
        </h1>
        <p class="text-sm text-muted-foreground/60 font-medium tracking-wide max-w-xs">
            This is a secure area of the application. Please confirm your password before continuing.
        </p>
    </div>

        <Form
            v-bind="store.form()"
            reset-on-success
            v-slot="{ errors, processing }"
        >
            <div class="space-y-6">
                <div class="grid gap-2">
                    <Label htmlFor="password">Password</Label>
                    <Input
                        id="password"
                        type="password"
                        name="password"
                        class="mt-1 block w-full"
                        required
                        autocomplete="current-password"
                        autofocus
                    />

                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center">
                    <Button
                        class="w-full"
                        :disabled="processing"
                        data-test="confirm-password-button"
                    >
                        <Spinner v-if="processing" />
                        Confirm password
                    </Button>
                </div>
            </div>
        </Form>
</template>
