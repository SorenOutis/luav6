<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import AppearanceTabs from '@/components/AppearanceTabs.vue';
import Heading from '@/components/Heading.vue';
import OnboardingTour from '@/components/OnboardingTour.vue';
import AppLayout from '@/layouts/AppLayout.vue';
import SettingsLayout from '@/layouts/settings/Layout.vue';
import type { TourStep } from '@/lib/onboarding';
import { edit } from '@/routes/appearance';
import type { BreadcrumbItem } from '@/types';

const breadcrumbItems: BreadcrumbItem[] = [
    {
        title: 'Appearance settings',
        href: edit(),
    },
];

// Per user + per device (localStorage) — replays on a new device.
const appearanceTourSteps: TourStep[] = [
    {
        id: 'welcome',
        title: 'Make it yours',
        body: 'This is where you control how the app looks. Your choice is saved on this device and follows you across pages.',
    },
    {
        id: 'themes',
        target: 'appearance-tabs',
        title: 'Light, dark, or system',
        body: 'Pick Light or Dark, or choose System to follow your device automatically. Every page — dashboard, activities, grades and chats — adapts instantly.',
    },
];
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbItems">
        <Head title="Appearance settings" />

        <h1 class="sr-only">Appearance settings</h1>

        <SettingsLayout>
            <div class="space-y-6">
                <Heading
                    variant="small"
                    title="Appearance settings"
                    description="Update your account's appearance settings"
                />
                <div data-tour="appearance-tabs" class="inline-block">
                    <AppearanceTabs />
                </div>
            </div>
        </SettingsLayout>

        <!-- First-visit walkthrough (per user, per device) -->
        <OnboardingTour
            tour-id="appearance"
            :steps="appearanceTourSteps"
            :start-delay="700"
        />
    </AppLayout>
</template>
