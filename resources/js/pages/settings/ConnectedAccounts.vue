<script setup lang="ts">
import { Head, router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import Heading from '@/components/Heading.vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { BreadcrumbItem } from '@/types';

type ConnectedProvider = {
    name: string;
    label: string;
    connected: boolean;
    email: string | null;
    nickname: string | null;
    connectedAt: string | null;
};

const props = withDefaults(
    defineProps<{
        providers?: ConnectedProvider[];
        hasPassword?: boolean;
        linkedCount?: number;
        status?: string;
    }>(),
    {
        providers: () => [],
        hasPassword: true,
        linkedCount: 0,
    },
);

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Connected accounts',
        href: '/settings/connected-accounts',
    },
];

const page = usePage();
const errors = computed(
    () => (page.props.errors ?? {}) as Record<string, string>,
);

const disconnecting = ref<string | null>(null);

/** OAuth needs a real browser navigation, not an Inertia visit. */
const connectUrl = (provider: string) => `/auth/${provider}/redirect`;

/**
 * Unlinking the only sign-in method would lock the account out, so the button
 * is disabled unless a password exists or another provider is still linked.
 */
const canDisconnect = (provider: ConnectedProvider) =>
    provider.connected && (props.hasPassword || props.linkedCount > 1);

const disconnect = (provider: ConnectedProvider) => {
    disconnecting.value = provider.name;

    router.delete(`/settings/connected-accounts/${provider.name}`, {
        preserveScroll: true,
        onFinish: () => (disconnecting.value = null),
    });
};
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Connected accounts" />

        <h1 class="sr-only">Connected accounts</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Connected accounts"
                    description="Link Google or GitHub so you can sign in with one tap"
                />

                <p
                    v-if="status"
                    class="text-sm font-medium text-emerald-600 dark:text-emerald-400"
                >
                    {{ status }}
                </p>

                <InputError :message="errors.provider" />

                <p
                    v-if="!providers.length"
                    class="text-sm text-muted-foreground"
                >
                    Social login is not configured on this platform yet.
                </p>

                <div v-else class="space-y-4">
                    <div
                        v-for="provider in providers"
                        :key="provider.name"
                        class="flex flex-col gap-3 rounded-lg border border-border p-4 sm:flex-row sm:items-center sm:justify-between"
                    >
                        <div class="space-y-1">
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-foreground">
                                    {{ provider.label }}
                                </span>
                                <Badge
                                    :variant="
                                        provider.connected
                                            ? 'default'
                                            : 'secondary'
                                    "
                                >
                                    {{
                                        provider.connected
                                            ? 'Connected'
                                            : 'Not connected'
                                    }}
                                </Badge>
                            </div>

                            <p
                                v-if="provider.connected"
                                class="text-sm text-muted-foreground"
                            >
                                {{ provider.email ?? provider.nickname }}
                                <span v-if="provider.connectedAt">
                                    · linked {{ provider.connectedAt }}
                                </span>
                            </p>
                            <p v-else class="text-sm text-muted-foreground">
                                Sign in faster using your
                                {{ provider.label }} account.
                            </p>
                        </div>

                        <div class="flex items-center gap-2">
                            <Button
                                v-if="!provider.connected"
                                variant="outline"
                                as-child
                            >
                                <a
                                    :href="connectUrl(provider.name)"
                                    :data-test="`connect-${provider.name}`"
                                >
                                    Connect
                                </a>
                            </Button>

                            <Button
                                v-else
                                variant="destructive"
                                :disabled="
                                    !canDisconnect(provider) ||
                                    disconnecting === provider.name
                                "
                                :data-test="`disconnect-${provider.name}`"
                                @click="disconnect(provider)"
                            >
                                {{
                                    disconnecting === provider.name
                                        ? 'Disconnecting...'
                                        : 'Disconnect'
                                }}
                            </Button>
                        </div>
                    </div>

                    <p
                        v-if="!hasPassword && linkedCount <= 1"
                        class="text-sm text-muted-foreground"
                    >
                        This is currently your only way to sign in. Set a
                        password on the Password page before disconnecting it.
                    </p>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
