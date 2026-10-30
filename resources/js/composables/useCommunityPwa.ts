import type { MaybeRefOrGetter } from 'vue';
import { computed, onBeforeUnmount, onMounted, ref, toValue } from 'vue';

/**
 * The deferred `beforeinstallprompt` event (Chromium only, not in lib.dom).
 */
export type BeforeInstallPromptEvent = Event & {
    prompt: () => Promise<void>;
    userChoice: Promise<{ outcome: 'accepted' | 'dismissed' }>;
};

declare global {
    interface Window {
        /** Stashed by app.blade.php when the prompt fires before Vue mounts. */
        __communityInstallPrompt?: BeforeInstallPromptEvent | null;
    }
}

export const COMMUNITY_MANIFEST_URL = '/community.webmanifest';
export const COMMUNITY_SW_URL = '/community-sw.js';
export const COMMUNITY_SW_SCOPE = '/';
export const COMMUNITY_BANNER_DISMISSED_KEY = 'communityPwa.bannerDismissedAt';

/** A dismissed mobile install banner stays hidden for two weeks. */
const BANNER_SNOOZE_MS = 14 * 24 * 60 * 60 * 1000;

/**
 * Whether the page runs as an installed app window. Inside the Vue portal
 * this always means the Community Lounge PWA: the only other installed app
 * (Echo AI) lives in the Filament /admin panel, not in Inertia pages.
 */
export const isStandaloneDisplay = (): boolean => {
    if (typeof window === 'undefined') return false;

    try {
        const standaloneQuery =
            window.matchMedia?.('(display-mode: standalone)')?.matches ?? false;
        const iosStandalone =
            (window.navigator as Navigator & { standalone?: boolean })
                .standalone === true;

        return standaloneQuery || iosStandalone;
    } catch {
        return false;
    }
};

/**
 * iPhone/iPad Safari never fires beforeinstallprompt, so the install button
 * opens an "Add to Home Screen" guide instead. iPadOS reports as MacIntel
 * with touch points.
 */
export const isIosDevice = (
    userAgent: string,
    platform = '',
    maxTouchPoints = 0,
): boolean =>
    /iphone|ipad|ipod/i.test(userAgent) ||
    (platform === 'MacIntel' && maxTouchPoints > 1);

const installPromptEvent = ref<BeforeInstallPromptEvent | null>(null);
const isStandalone = ref(false);
const isIos = ref(false);
const isMobileDevice = ref(false);
const isInstalled = ref(false);
const isBannerDismissed = ref(false);
const isIosGuideOpen = ref(false);
let globalListenersAttached = false;

const readBannerDismissed = (): boolean => {
    try {
        const raw = window.localStorage?.getItem(
            COMMUNITY_BANNER_DISMISSED_KEY,
        );
        const dismissedAt = raw ? Number(raw) : 0;

        return (
            Number.isFinite(dismissedAt) &&
            dismissedAt > 0 &&
            Date.now() - dismissedAt < BANNER_SNOOZE_MS
        );
    } catch {
        return false;
    }
};

const detectEnvironment = () => {
    isStandalone.value = isStandaloneDisplay();
    isIos.value = isIosDevice(
        window.navigator.userAgent ?? '',
        window.navigator.platform ?? '',
        window.navigator.maxTouchPoints ?? 0,
    );

    let coarsePointer = false;
    try {
        coarsePointer =
            window.matchMedia?.('(pointer: coarse)')?.matches ?? false;
    } catch {
        coarsePointer = false;
    }
    isMobileDevice.value =
        isIos.value || coarsePointer || window.innerWidth < 1024;
    isBannerDismissed.value = readBannerDismissed();

    if (
        typeof window !== 'undefined' &&
        'getInstalledRelatedApps' in window.navigator
    ) {
        (window.navigator as any)
            .getInstalledRelatedApps?.()
            .then((apps: any[]) => {
                if (Array.isArray(apps) && apps.length > 0) {
                    isInstalled.value = true;
                }
            })
            .catch(() => {});
    }
};

const handleBeforeInstallPrompt = (event: Event) => {
    event.preventDefault();
    installPromptEvent.value = event as BeforeInstallPromptEvent;
    if (typeof window !== 'undefined') {
        window.__communityInstallPrompt = event as BeforeInstallPromptEvent;
    }
};

const handleAppInstalled = () => {
    installPromptEvent.value = null;
    if (typeof window !== 'undefined') {
        window.__communityInstallPrompt = null;
    }
    isInstalled.value = true;
    isIosGuideOpen.value = false;
};

const attachGlobalListeners = () => {
    if (globalListenersAttached) return;
    globalListenersAttached = true;

    window.addEventListener('beforeinstallprompt', handleBeforeInstallPrompt);
    window.addEventListener('appinstalled', handleAppInstalled);
};

if (typeof window !== 'undefined') {
    attachGlobalListeners();
    if (window.__communityInstallPrompt && !installPromptEvent.value) {
        installPromptEvent.value = window.__communityInstallPrompt;
    }
}

/**
 * Lightweight standalone detection for pages outside the Lounge (login,
 * register, logout modal). Resolved on mount to keep SSR markup stable.
 */
export function useStandaloneDisplay() {
    const standalone = ref(false);

    onMounted(() => {
        standalone.value = isStandaloneDisplay();
    });

    return { isStandalone: standalone };
}

export interface UseCommunityPwaOptions {
    /** Whether the Community PWA feature is enabled by platform settings. Defaults to true. */
    enabled?: MaybeRefOrGetter<boolean> | boolean;
}

/**
 * Community Lounge PWA wiring: advertises the manifest after client-side
 * Inertia visits, registers the /community service worker and drives the
 * in-app "Install app" button, mobile banner and iOS guide.
 */
export function useCommunityPwa(options: UseCommunityPwaOptions = {}) {
    const isFeatureEnabled = computed(() =>
        options.enabled !== undefined
            ? Boolean(toValue(options.enabled))
            : true,
    );

    let addedManifestLink: HTMLLinkElement | null = null;

    onMounted(() => {
        if (typeof window === 'undefined') return;

        detectEnvironment();
        attachGlobalListeners();

        if (window.__communityInstallPrompt && !installPromptEvent.value) {
            installPromptEvent.value = window.__communityInstallPrompt;
        }

        if (!isFeatureEnabled.value) {
            return;
        }

        if (!document.querySelector('link[rel="manifest"]')) {
            addedManifestLink = document.createElement('link');
            addedManifestLink.rel = 'manifest';
            addedManifestLink.href = COMMUNITY_MANIFEST_URL;
            addedManifestLink.setAttribute('data-community-manifest', '');
            document.head.appendChild(addedManifestLink);
        }

        if ('serviceWorker' in window.navigator) {
            window.navigator.serviceWorker
                ?.register(COMMUNITY_SW_URL, { scope: COMMUNITY_SW_SCOPE })
                .catch(() => {
                    // Registration is an offline/installability nicety; the
                    // Lounge works without it.
                });
        }
    });

    onBeforeUnmount(() => {
        // Only drop the manifest we injected ourselves, so the rest of the
        // portal never offers to install itself as the Lounge.
        addedManifestLink?.remove();
        addedManifestLink = null;
    });

    const canInstall = computed(
        () =>
            isFeatureEnabled.value && !isStandalone.value && !isInstalled.value,
    );

    const showInstallBanner = computed(
        () =>
            canInstall.value &&
            isMobileDevice.value &&
            !isBannerDismissed.value,
    );

    const promptInstall = async (): Promise<void> => {
        if (!isFeatureEnabled.value) {
            return;
        }

        const deferred =
            installPromptEvent.value ||
            (typeof window !== 'undefined'
                ? window.__communityInstallPrompt
                : null);

        if (!deferred) {
            isIosGuideOpen.value = true;
            return;
        }

        installPromptEvent.value = null;
        if (typeof window !== 'undefined') {
            window.__communityInstallPrompt = null;
        }

        try {
            await deferred.prompt();
            const choice = await deferred.userChoice;
            if (choice?.outcome === 'accepted') {
                isInstalled.value = true;
            }
        } catch {
            isIosGuideOpen.value = true;
        }
    };

    const dismissInstallBanner = () => {
        isBannerDismissed.value = true;
        try {
            window.localStorage?.setItem(
                COMMUNITY_BANNER_DISMISSED_KEY,
                String(Date.now()),
            );
        } catch {
            // Storage may be unavailable (private mode); hide for this visit.
        }
    };

    const closeIosGuide = () => {
        isIosGuideOpen.value = false;
    };

    return {
        isStandalone,
        isIos,
        canInstall,
        showInstallBanner,
        isIosGuideOpen,
        promptInstall,
        dismissInstallBanner,
        closeIosGuide,
    };
}

/**
 * Reset the shared PWA state. Test-only helper.
 */
export function resetCommunityPwaState(): void {
    installPromptEvent.value = null;
    isStandalone.value = false;
    isIos.value = false;
    isMobileDevice.value = false;
    isInstalled.value = false;
    isBannerDismissed.value = false;
    isIosGuideOpen.value = false;
}
