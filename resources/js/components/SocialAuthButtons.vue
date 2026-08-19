<script setup lang="ts">
import { Button } from '@/components/ui/button';

type SocialProvider = {
    name: string;
    label: string;
};

const props = withDefaults(
    defineProps<{
        providers?: SocialProvider[];
        /** When false the buttons are inert and emit `blocked` instead. */
        enabled?: boolean;
        /** Verb shown on the button, e.g. "Continue" or "Sign up". */
        action?: string;
        dividerLabel?: string;
    }>(),
    {
        providers: () => [],
        enabled: true,
        action: 'Continue',
        dividerLabel: 'or continue with',
    },
);

const emit = defineEmits<{ blocked: [] }>();

/**
 * OAuth requires a real browser navigation, so these are plain links rather
 * than Inertia visits.
 */
const redirectUrl = (provider: string) => `/auth/${provider}/redirect`;

const onClick = (event: MouseEvent) => {
    if (!props.enabled) {
        event.preventDefault();
        emit('blocked');
    }
};
</script>

<template>
    <div v-if="providers.length" class="flex flex-col gap-4">
        <div class="relative">
            <div class="absolute inset-0 flex items-center" aria-hidden="true">
                <span class="w-full border-t border-border" />
            </div>
            <div class="relative flex justify-center text-xs uppercase">
                <span class="bg-background px-2 text-muted-foreground">
                    {{ dividerLabel }}
                </span>
            </div>
        </div>

        <div
            class="grid gap-3"
            :class="providers.length > 1 ? 'sm:grid-cols-2' : ''"
        >
            <Button
                v-for="provider in providers"
                :key="provider.name"
                variant="outline"
                class="w-full"
                as-child
            >
                <a
                    :href="redirectUrl(provider.name)"
                    :data-test="`social-${provider.name}`"
                    :aria-label="`${action} with ${provider.label}`"
                    @click="onClick"
                >
                    <svg
                        v-if="provider.name === 'google'"
                        class="mr-2 h-4 w-4"
                        viewBox="0 0 24 24"
                        aria-hidden="true"
                    >
                        <path
                            fill="#EA4335"
                            d="M12 10.2v3.9h5.5a4.7 4.7 0 0 1-2 3.1l3.2 2.5c1.9-1.7 3-4.3 3-7.3 0-.7-.1-1.4-.2-2H12Z"
                        />
                        <path
                            fill="#34A853"
                            d="M6.6 14.3 5.9 15l-2.6 2A9 9 0 0 0 12 21c2.4 0 4.5-.8 6-2.2l-3.2-2.5c-.8.6-1.9.9-2.8.9-2.3 0-4.3-1.5-5-3.6l-.4-.3Z"
                        />
                        <path
                            fill="#4285F4"
                            d="M12 21c2.4 0 4.5-.8 6-2.2l-3.2-2.5c-.8.6-1.9.9-2.8.9-2.3 0-4.3-1.5-5-3.6l-3.3 2.5A9 9 0 0 0 12 21Z"
                        />
                        <path
                            fill="#FBBC05"
                            d="M7 13.6a5.4 5.4 0 0 1 0-3.4L3.7 7.7a9 9 0 0 0 0 8.1L7 13.6Z"
                        />
                        <path
                            fill="#EA4335"
                            d="M12 6.6c1.3 0 2.5.5 3.4 1.4l2.6-2.6A9 9 0 0 0 3.7 7.7L7 10.2c.8-2.1 2.7-3.6 5-3.6Z"
                        />
                    </svg>

                    <svg
                        v-else-if="provider.name === 'github'"
                        class="mr-2 h-4 w-4"
                        viewBox="0 0 24 24"
                        fill="currentColor"
                        aria-hidden="true"
                    >
                        <path
                            d="M12 .5a11.5 11.5 0 0 0-3.6 22.4c.6.1.8-.2.8-.6v-2c-3.2.7-3.9-1.4-3.9-1.4-.5-1.4-1.3-1.7-1.3-1.7-1-.7.1-.7.1-.7 1.1.1 1.7 1.2 1.7 1.2 1 1.7 2.7 1.2 3.4.9.1-.7.4-1.2.7-1.5-2.6-.3-5.3-1.3-5.3-5.7 0-1.3.5-2.3 1.2-3.1-.1-.3-.5-1.5.1-3.1 0 0 1-.3 3.2 1.2a11 11 0 0 1 5.8 0c2.2-1.5 3.2-1.2 3.2-1.2.6 1.6.2 2.8.1 3.1.8.8 1.2 1.8 1.2 3.1 0 4.4-2.7 5.4-5.3 5.7.4.4.8 1.1.8 2.2v3.3c0 .4.2.7.8.6A11.5 11.5 0 0 0 12 .5Z"
                        />
                    </svg>

                    {{ action }} with {{ provider.label }}
                </a>
            </Button>
        </div>
    </div>
</template>
