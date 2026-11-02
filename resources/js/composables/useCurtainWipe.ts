import { router } from '@inertiajs/vue3';
import { gsap } from 'gsap';
import { ref } from 'vue';
import { useMobile } from '@/composables/useMobile';

export const CURTAIN_STORAGE_KEY = 'curtain_wipe_direction';

export type WipeDirection = 'to_community' | 'to_dashboard';

export function useCurtainWipe() {
    const { prefersReducedMotion } = useMobile();
    const isWiping = ref(false);

    const safeStorageGet = (): string | null => {
        if (typeof window === 'undefined') return null;
        try {
            return sessionStorage.getItem(CURTAIN_STORAGE_KEY);
        } catch {
            return null;
        }
    };

    const safeStorageSet = (val: string) => {
        if (typeof window === 'undefined') return;
        try {
            sessionStorage.setItem(CURTAIN_STORAGE_KEY, val);
        } catch {
            // Ignore storage errors in private browsing / sandboxes
        }
    };

    const safeStorageRemove = () => {
        if (typeof window === 'undefined') return;
        try {
            sessionStorage.removeItem(CURTAIN_STORAGE_KEY);
        } catch {
            // Ignore
        }
    };

    const wipeTo = (
        destinationUrl: string,
        curtainElement: HTMLElement | null,
        overlayElement: HTMLElement | null,
        direction: WipeDirection = 'to_community',
        onCompleteCallback?: () => void,
    ) => {
        if (prefersReducedMotion.value || !curtainElement || !overlayElement) {
            safeStorageRemove();
            router.visit(destinationUrl, { preserveScroll: false });
            onCompleteCallback?.();
            return;
        }

        safeStorageSet(direction);
        isWiping.value = true;
        const startX = direction === 'to_community' ? -100 : 100;

        gsap.killTweensOf(curtainElement);
        gsap.set(overlayElement, { autoAlpha: 1 });
        gsap.set(curtainElement, { xPercent: startX, autoAlpha: 1 });

        const cardElement = curtainElement.querySelector<HTMLElement>(
            '[data-test="glass-card"]',
        );
        if (cardElement) {
            gsap.killTweensOf(cardElement);
            gsap.fromTo(
                cardElement,
                { scale: 0.95, opacity: 0.7 },
                { scale: 1, opacity: 1, duration: 0.45, ease: 'power2.out' },
            );
        }

        gsap.to(curtainElement, {
            xPercent: 0,
            duration: 0.45,
            ease: 'power3.inOut',
            onComplete: () => {
                isWiping.value = false;
                onCompleteCallback?.();
                router.visit(destinationUrl, {
                    preserveScroll: false,
                    onError: () => {
                        safeStorageRemove();
                        gsap.to(curtainElement, {
                            xPercent: startX,
                            duration: 0.3,
                            ease: 'power3.out',
                            onComplete: () => {
                                gsap.set(overlayElement, { autoAlpha: 0 });
                                gsap.set(curtainElement, { autoAlpha: 0 });
                            },
                        });
                    },
                    onCancel: () => {
                        safeStorageRemove();
                        gsap.set(overlayElement, { autoAlpha: 0 });
                        gsap.set(curtainElement, { autoAlpha: 0 });
                    },
                });
            },
        });
    };

    const handleMountWipe = (
        curtainElement: HTMLElement | null,
        overlayElement: HTMLElement | null,
        expectedDirection?: WipeDirection,
    ) => {
        if (
            !curtainElement ||
            !overlayElement ||
            typeof window === 'undefined'
        ) {
            return;
        }

        const direction = safeStorageGet();

        if (!direction || prefersReducedMotion.value) {
            gsap.set(overlayElement, { autoAlpha: 0 });
            gsap.set(curtainElement, { xPercent: -100, autoAlpha: 0 });
            return;
        }

        if (expectedDirection && direction !== expectedDirection) {
            return;
        }

        safeStorageRemove();

        const targetX = direction === 'to_community' ? 100 : -100;

        gsap.killTweensOf(curtainElement);
        gsap.set(overlayElement, { autoAlpha: 1 });
        gsap.set(curtainElement, { xPercent: 0, autoAlpha: 1 });

        const cardElement = curtainElement.querySelector<HTMLElement>(
            '[data-test="glass-card"]',
        );
        if (cardElement) {
            gsap.killTweensOf(cardElement);
            gsap.to(cardElement, {
                scale: 0.96,
                opacity: 0,
                duration: 0.35,
                ease: 'power2.in',
            });
        }

        gsap.to(curtainElement, {
            xPercent: targetX,
            duration: 0.45,
            ease: 'power3.inOut',
            onComplete: () => {
                gsap.set(overlayElement, { autoAlpha: 0 });
                gsap.set(curtainElement, { autoAlpha: 0 });
            },
        });
    };

    const dismissWipe = (
        curtainElement: HTMLElement | null,
        overlayElement: HTMLElement | null,
    ) => {
        safeStorageRemove();
        isWiping.value = false;
        if (curtainElement) {
            gsap.killTweensOf(curtainElement);
            const card = curtainElement.querySelector<HTMLElement>(
                '[data-test="glass-card"]',
            );
            if (card) {
                gsap.killTweensOf(card);
            }
            gsap.to(curtainElement, {
                opacity: 0,
                duration: 0.25,
                ease: 'power2.out',
                onComplete: () => {
                    gsap.set(curtainElement, {
                        xPercent: -100,
                        autoAlpha: 0,
                        opacity: 1,
                    });
                    if (overlayElement) {
                        gsap.set(overlayElement, { autoAlpha: 0 });
                    }
                },
            });
        } else if (overlayElement) {
            gsap.set(overlayElement, { autoAlpha: 0 });
        }
    };

    return {
        isWiping,
        wipeTo,
        handleMountWipe,
        dismissWipe,
    };
}
