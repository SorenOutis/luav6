<script setup lang="ts">
import {
    Download,
    Monitor,
    Share,
    Sparkles,
    SquarePlus,
    X,
} from 'lucide-vue-next';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { useCommunityPwa } from '@/composables/useCommunityPwa';

const {
    isIos,
    canInstall,
    showInstallBanner,
    isIosGuideOpen,
    promptInstall,
    dismissInstallBanner,
    closeIosGuide,
} = useCommunityPwa();
</script>

<template>
    <div class="flex items-center" data-test="community-install-prompt">
        <!-- Subtle header pill: hidden once installed / in the app window -->
        <button
            v-if="canInstall"
            type="button"
            data-test="community-install-button"
            class="inline-flex cursor-pointer items-center gap-1.5 rounded-full border border-brand/30 bg-brand/10 px-3 py-1.5 text-xs font-semibold text-brand shadow-2xs transition hover:bg-brand/15 active:scale-95"
            title="Install the Community Lounge app"
            @click="promptInstall"
        >
            <Download class="h-3.5 w-3.5" aria-hidden="true" />
            <span>Install app</span>
        </button>

        <!-- Dismissible mobile install banner -->
        <Teleport to="body">
            <transition
                enter-active-class="transition duration-300 ease-out"
                enter-from-class="translate-y-4 opacity-0"
                leave-active-class="transition duration-200 ease-in"
                leave-to-class="translate-y-4 opacity-0"
            >
                <div
                    v-if="showInstallBanner"
                    data-test="community-install-banner"
                    role="dialog"
                    aria-label="Install the Community Lounge app"
                    class="fixed inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+0.75rem)] z-[70] mx-auto flex max-w-md items-center gap-3 rounded-2xl border border-border/70 bg-card/95 p-3 shadow-2xl backdrop-blur-xl lg:hidden"
                >
                    <img
                        :src="'/favicon.png?size=192'"
                        alt=""
                        class="h-10 w-10 shrink-0 rounded-xl border border-border/60 bg-background object-contain"
                    />
                    <div class="min-w-0 flex-1">
                        <p
                            class="truncate text-sm font-semibold text-foreground"
                        >
                            Get the Lounge app
                        </p>
                        <p class="truncate text-[11px] text-muted-foreground">
                            Full-screen feed, one tap from your home screen.
                        </p>
                    </div>
                    <button
                        type="button"
                        data-test="community-install-banner-button"
                        class="shrink-0 cursor-pointer rounded-xl bg-brand px-3 py-1.5 text-xs font-semibold text-primary-foreground shadow-xs transition active:scale-95"
                        @click="promptInstall"
                    >
                        Install
                    </button>
                    <button
                        type="button"
                        data-test="community-install-banner-dismiss"
                        aria-label="Dismiss install banner"
                        class="shrink-0 cursor-pointer rounded-full p-1 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                        @click="dismissInstallBanner"
                    >
                        <X class="h-4 w-4" />
                    </button>
                </div>
            </transition>
        </Teleport>

        <!-- Install guidance modal: iOS Safari or Desktop/Android without native prompt -->
        <ResponsiveModal
            :open="isIosGuideOpen"
            :title="
                isIos
                    ? 'Add the Lounge to your Home Screen'
                    : 'Install Community Lounge'
            "
            :description="
                isIos
                    ? 'Install the Community Lounge in three quick steps.'
                    : 'Install the Community Lounge as a standalone app on your device.'
            "
            content-class="sm:max-w-sm"
            @close="closeIosGuide"
        >
            <!-- iOS Safari guide -->
            <ol
                v-if="isIos"
                class="space-y-3 text-sm text-foreground"
                data-test="community-ios-install-guide"
            >
                <li class="flex items-start gap-3">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/15 text-brand"
                    >
                        <Share class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                    <span class="pt-1">
                        Open this page in <strong>Safari</strong> and tap the
                        <strong>Share</strong> button.
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/15 text-brand"
                    >
                        <SquarePlus class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                    <span class="pt-1">
                        Scroll down and choose
                        <strong>Add to Home Screen</strong>.
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/15 text-brand"
                    >
                        <Download class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                    <span class="pt-1">
                        Tap <strong>Add</strong>, then open
                        <strong>Lounge</strong> from your Home Screen and sign
                        in.
                    </span>
                </li>
            </ol>

            <!-- Desktop & Android guide -->
            <ol
                v-else
                class="space-y-3 text-sm text-foreground"
                data-test="community-desktop-install-guide"
            >
                <li class="flex items-start gap-3">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/15 text-brand"
                    >
                        <Monitor class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                    <span class="pt-1">
                        Click the <strong>Install</strong> icon in your
                        browser's address bar, or open the browser menu (<strong
                            >⋮</strong
                        >
                        or <strong>⋯</strong>) and choose
                        <strong>Install Community Lounge</strong>.
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/15 text-brand"
                    >
                        <Download class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                    <span class="pt-1">
                        Click <strong>Install</strong> to confirm and add the
                        app to your desktop or device.
                    </span>
                </li>
                <li class="flex items-start gap-3">
                    <span
                        class="flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-brand/15 text-brand"
                    >
                        <Sparkles class="h-3.5 w-3.5" aria-hidden="true" />
                    </span>
                    <span class="pt-1">
                        Launch directly from your desktop, taskbar, or home
                        screen for a full-screen experience.
                    </span>
                </li>
            </ol>

            <p class="mt-4 text-xs text-muted-foreground">
                Login and an internet connection are required.
            </p>
        </ResponsiveModal>
    </div>
</template>
