<script setup lang="ts">
import { Form, Head } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import AnimatedInput from '@/components/ui/input/AnimatedInput.vue';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import AuthCard from '@/layouts/auth/AuthCardLayout.vue';
import { withForm } from '@/lib/route-helpers';
import { store } from '@/routes/two-factor/login';
import type { TwoFactorConfigContent } from '@/types';

const authConfigContent = computed<TwoFactorConfigContent>(() => {
    if (showRecoveryInput.value) {
        return {
            title: 'Recovery code',
            description:
                'Please confirm access to your account by entering one of your emergency recovery codes.',
            buttonText: 'login using an authentication code',
        };
    }

    return {
        title: 'Authentication code',
        description:
            'Enter the authentication code provided by your authenticator application.',
        buttonText: 'login using a recovery code',
    };
});

const showRecoveryInput = ref<boolean>(false);

const toggleRecoveryMode = (clearErrors: () => void): void => {
    showRecoveryInput.value = !showRecoveryInput.value;
    clearErrors();
    code.value = '';
};

const code = ref<string>('');

defineOptions({ layout: AuthCard });
</script>

<template>
    <Head title="Two-factor authentication" />

    <div class="text-center">
        <h1
            class="text-2xl leading-tight font-bold tracking-tight text-foreground"
        >
            {{ authConfigContent.title }}
        </h1>
        <p
            class="mx-auto mt-1.5 max-w-xs text-sm font-normal text-muted-foreground"
        >
            {{ authConfigContent.description }}
        </p>
    </div>

    <div class="space-y-6">
        <template v-if="!showRecoveryInput">
            <Form
                v-bind="withForm(store).form()"
                class="space-y-4"
                reset-on-error
                @error="code = ''"
                #default="{ errors, processing, clearErrors }"
            >
                <input type="hidden" name="code" :value="code" />
                <div
                    class="flex flex-col items-center justify-center space-y-3 text-center"
                >
                    <div class="flex w-full items-center justify-center">
                        <InputOTP
                            id="otp"
                            v-model="code"
                            :maxlength="6"
                            :disabled="processing"
                            autofocus
                        >
                            <InputOTPGroup>
                                <InputOTPSlot
                                    v-for="index in 6"
                                    :key="index"
                                    :index="index - 1"
                                />
                            </InputOTPGroup>
                        </InputOTP>
                    </div>
                    <InputError :message="errors.code" />
                </div>
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    :tabindex="1"
                    >Continue</Button
                >
                <div class="text-center text-sm text-muted-foreground">
                    <span>or you can </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>

        <template v-else>
            <Form
                v-bind="withForm(store).form()"
                class="space-y-4"
                reset-on-error
                #default="{ errors, processing, clearErrors }"
            >
                <AnimatedInput
                    name="recovery_code"
                    type="text"
                    required
                    :autofocus="showRecoveryInput"
                    :tabindex="1"
                    label="Recovery code"
                />
                <InputError :message="errors.recovery_code" />
                <Button
                    type="submit"
                    class="w-full"
                    :disabled="processing"
                    :tabindex="2"
                    >Continue</Button
                >

                <div class="text-center text-sm text-muted-foreground">
                    <span>or you can </span>
                    <button
                        type="button"
                        class="text-foreground underline decoration-border underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current"
                        @click="() => toggleRecoveryMode(clearErrors)"
                    >
                        {{ authConfigContent.buttonText }}
                    </button>
                </div>
            </Form>
        </template>
    </div>
</template>
