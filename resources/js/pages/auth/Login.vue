<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { Eye, EyeOff } from 'lucide-vue-next';
import { ref } from 'vue';
import InputError from '@/components/InputError.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import SocialAuthButtons from '@/components/SocialAuthButtons.vue';
import TextLink from '@/components/TextLink.vue';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DialogDescription, DialogTitle } from '@/components/ui/dialog';
import AnimatedInput from '@/components/ui/input/AnimatedInput.vue';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import AuthCard from '@/layouts/auth/AuthCardLayout.vue';
import { withForm } from '@/lib/route-helpers';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type SocialProvider = {
    name: string;
    label: string;
};

const props = withDefaults(
    defineProps<{
        status?: string;
        canResetPassword: boolean;
        canRegister: boolean;
        loginEnabled: boolean;
        loginDisabledMessage: string;
        socialProviders?: SocialProvider[];
    }>(),
    {
        socialProviders: () => [],
    },
);

defineOptions({ layout: AuthCard });

const submitting = ref(false);
const showDisabledModal = ref(false);
const showPassword = ref(false);

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

    <div class="text-center">
        <h1
            class="text-2xl leading-tight font-bold tracking-tight text-foreground"
        >
            Log in to your account
        </h1>
        <p
            class="mx-auto mt-1.5 max-w-xs text-sm font-normal text-muted-foreground"
        >
            Enter your email and password below to log in
        </p>
    </div>

    <div
        v-if="status"
        class="mb-4 text-center text-sm font-medium text-emerald-600 dark:text-emerald-400"
    >
        {{ status }}
    </div>

    <Form
        v-bind="withForm(store).form()"
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
                    @keydown.enter="
                        !loginEnabled &&
                        ($event.preventDefault(), (showDisabledModal = true))
                    "
                />
                <InputError :message="errors.email" />
            </div>

            <div class="grid gap-2">
                <div class="relative">
                    <AnimatedInput
                        id="password"
                        :type="showPassword ? 'text' : 'password'"
                        name="password"
                        required
                        :tabindex="2"
                        autocomplete="current-password"
                        label="Password"
                        @keydown.enter="
                            !loginEnabled &&
                            ($event.preventDefault(),
                            (showDisabledModal = true))
                        "
                    />
                    <button
                        type="button"
                        :aria-label="
                            showPassword ? 'Hide password' : 'Show password'
                        "
                        class="absolute right-0 bottom-2.5 text-muted-foreground/50 transition-colors hover:text-foreground"
                        @click="showPassword = !showPassword"
                    >
                        <EyeOff v-if="showPassword" class="h-4 w-4" />
                        <Eye v-else class="h-4 w-4" />
                    </button>
                </div>
                <InputError :message="errors.password" />
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
                {{ processing || submitting ? 'Logging in...' : 'Log in' }}
            </Button>
        </div>

        <SocialAuthButtons
            :providers="socialProviders"
            :enabled="loginEnabled"
            action="Continue"
            @blocked="showDisabledModal = true"
        />

        <div
            class="text-center text-sm text-muted-foreground"
            v-if="canRegister"
        >
            Don't have an account?
            <TextLink :href="register()" :tabindex="5">Sign up</TextLink>
        </div>
    </Form>

    <!-- Disabled Login Modal -->
    <ResponsiveModal
        :open="showDisabledModal"
        title="Login Disabled"
        :description="loginDisabledMessage"
        custom-header
        @close="showDisabledModal = false"
    >
        <template #header>
            <div
                class="mx-auto mb-4 flex h-12 w-12 items-center justify-center rounded-full bg-amber-500/10 text-amber-500"
            >
                <svg
                    xmlns="http://www.w3.org/2000/svg"
                    width="24"
                    height="24"
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
            <DialogTitle
                class="text-center text-xl font-black tracking-tight uppercase"
                >Login Disabled</DialogTitle
            >
            <DialogDescription class="pt-2 text-center leading-relaxed">
                {{ loginDisabledMessage }}
            </DialogDescription>
        </template>
        <div class="mt-6 flex flex-col gap-3">
            <Button
                variant="outline"
                @click="showDisabledModal = false"
                class="w-full"
            >
                Close
            </Button>
        </div>
    </ResponsiveModal>
</template>
