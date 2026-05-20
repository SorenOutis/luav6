<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

const props = defineProps<{
    status?: string;
    canResetPassword: boolean;
    canRegister: boolean;
    loginEnabled: boolean;
    loginDisabledMessage: string;
}>();

defineOptions({ layout: AuthBase });

const submitting = ref(false);
const showDisabledModal = ref(false);

const onSubmit = (event: Event) => {
    // If login is disabled, we should have already intercepted this with the button type="button"
    // or keydown listener. This is a fallback.
    if (!props.loginEnabled) {
        event.preventDefault();
        event.stopImmediatePropagation();
        showDisabledModal.value = true;
        return;
    }
    submitting.value = true;
};
</script>

<template>
    <Head title="Log in" />

    <div class="space-y-3">
        <h1 class="text-3xl sm:text-4xl font-black uppercase tracking-tighter text-foreground leading-tight">
            Log in to your account
        </h1>
        <p class="text-sm text-muted-foreground/60 font-medium tracking-wide max-w-xs">
            Enter your email and password below to log in
        </p>
    </div>

        <div
            v-if="status"
            class="mb-4 text-center text-sm font-medium text-green-600"
        >
            {{ status }}
        </div>

        <Form
            v-bind="store.form()"
            :reset-on-success="['password']"
            v-slot="{ errors, processing }"
            class="flex flex-col gap-6"
            @submit="onSubmit"
            @error="submitting = false"
            @success="submitting = false"
        >
            <div class="grid gap-6">
                <div class="grid gap-2">
                    <AnimatedInput
                        id="email"
                        type="email"
                        name="email"
                        required
                        autofocus
                        :tabindex="1"
                        autocomplete="email"
                        label="Email address"
                        @keydown.enter="!loginEnabled && ($event.preventDefault(), showDisabledModal = true)"
                    />
                    <InputError :message="errors.email" />
                </div>

                <div class="grid gap-2">
                    <div class="flex items-center justify-end">
                        <TextLink
                            v-if="canResetPassword"
                            :href="request()"
                            class="text-sm"
                            :tabindex="5"
                        >
                            Forgot password?
                        </TextLink>
                    </div>
                    <AnimatedInput
                        id="password"
                        type="password"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        label="Password"
                        @keydown.enter="!loginEnabled && ($event.preventDefault(), showDisabledModal = true)"
                    />
                    <InputError :message="errors.password" />
                </div>

                <div class="flex items-center justify-between">
                    <Label for="remember" class="flex items-center space-x-3">
                        <Checkbox id="remember" name="remember" :tabindex="3" />
                        <span>Remember me</span>
                    </Label>
                </div>

                <Button
                    v-if="!loginEnabled"
                    type="button"
                    class="mt-4 w-full"
                    :tabindex="4"
                    @click="showDisabledModal = true"
                    data-test="login-button-disabled"
                >
                    Log in
                </Button>

                <Button
                    v-else
                    type="submit"
                    class="mt-4 w-full"
                    :tabindex="4"
                    :disabled="processing || submitting"
                    data-test="login-button"
                >
                    <Spinner v-if="processing || submitting" class="mr-2" />
                    {{ (processing || submitting) ? 'Logging in...' : 'Log in' }}
                </Button>
            </div>

            <div
                class="text-center text-sm text-muted-foreground"
                v-if="canRegister"
            >
                Don't have an account?
                <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
            </div>
        </Form>

        <Dialog v-model:open="showDisabledModal">
            <DialogContent class="sm:max-w-md border-border/40 bg-background/95 backdrop-blur-xl">
                <DialogHeader>
                    <div class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-500/10 text-amber-500">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-alert-triangle"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><path d="M12 9v4"/><path d="M12 17h.01"/></svg>
                    </div>
                    <DialogTitle class="text-center text-xl font-black uppercase tracking-tight">Login Disabled</DialogTitle>
                    <DialogDescription class="text-center pt-2 leading-relaxed">
                        {{ loginDisabledMessage }}
                    </DialogDescription>
                </DialogHeader>
                <div class="mt-6 flex flex-col gap-3">
                    <Button variant="outline" @click="showDisabledModal = false" class="w-full">
                        Close
                    </Button>
                </div>
            </DialogContent>
        </Dialog>
</template>
