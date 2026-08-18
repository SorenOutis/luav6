<script setup lang="ts">
import { gsap } from 'gsap';
import { X } from 'lucide-vue-next';
import { ref, watch, nextTick } from 'vue';

type Props = {
    open: boolean;
    title?: string;
    description?: string;
    showCloseButton?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    title: '',
    description: '',
    showCloseButton: true,
});

const emit = defineEmits<{
    close: [];
}>();

const sheetRef = ref<HTMLElement | null>(null);
const backdropRef = ref<HTMLElement | null>(null);
const contentRef = ref<HTMLElement | null>(null);

function animateIn() {
    if (!sheetRef.value || !backdropRef.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'power4.out' },
    });

    tl.fromTo(
        backdropRef.value,
        { opacity: 0 },
        { opacity: 1, duration: 0.3 },
    ).fromTo(
        sheetRef.value,
        { y: '100%' },
        { y: '0%', duration: 0.5, ease: 'power4.out' },
        '-=0.2',
    );

    // Stagger children
    if (contentRef.value) {
        const children = contentRef.value.querySelectorAll(
            '.sheet-item, .sheet-section',
        );
        if (children.length) {
            tl.fromTo(
                children,
                { opacity: 0, y: 20 },
                {
                    opacity: 1,
                    y: 0,
                    duration: 0.4,
                    stagger: 0.04,
                    ease: 'power3.out',
                },
                '-=0.3',
            );
        }
    }
}

function animateOut(callback?: () => void) {
    if (!sheetRef.value || !backdropRef.value) return;

    const tl = gsap.timeline({
        defaults: { ease: 'power3.in' },
        onComplete: () => {
            callback?.();
        },
    });

    tl.to(sheetRef.value, { y: '100%', duration: 0.35 }).to(
        backdropRef.value,
        { opacity: 0, duration: 0.2 },
        '-=0.3',
    );
}

function handleBackdropClick() {
    animateOut(() => emit('close'));
}

watch(
    () => props.open,
    async (val) => {
        if (val) {
            await nextTick();
            animateIn();
        }
    },
);

function handleClose() {
    animateOut(() => emit('close'));
}
</script>

<template>
    <Teleport to="body">
        <!-- Backdrop -->
        <div
            v-if="open"
            ref="backdropRef"
            class="fixed inset-0 z-[100] bg-black/55 backdrop-blur-sm"
            @click="handleBackdropClick"
        />

        <!-- Sheet -->
        <div
            v-if="open"
            ref="sheetRef"
            data-test="mobile-bottom-sheet"
            class="fixed right-0 bottom-0 left-0 z-[110] mx-auto flex max-h-[min(85dvh,85vh)] max-w-lg flex-col rounded-t-3xl border-t border-border/60 bg-background/95 shadow-2xl shadow-black/20 backdrop-blur-2xl"
            style="padding-bottom: calc(env(safe-area-inset-bottom) + 0.5rem)"
        >
            <!-- Grab Handle -->
            <div
                class="mx-auto mt-2 h-1.5 w-10 shrink-0 rounded-full bg-muted-foreground/20"
            />

            <!-- Header: pinned above the scrollable content so titles and the
                 close button stay visible while the body scrolls -->
            <div
                v-if="$slots.header || title || description || showCloseButton"
                class="flex items-start gap-4 px-6 pt-4 pb-3"
                :class="
                    $slots.header || title || description
                        ? 'justify-between'
                        : 'justify-end'
                "
            >
                <!-- Custom header slot (pinned, mirrors the desktop DialogHeader) -->
                <div v-if="$slots.header" class="min-w-0 flex-1">
                    <slot name="header" />
                </div>
                <div v-else-if="title || description" class="min-w-0 pt-1">
                    <h2
                        v-if="title"
                        class="text-lg font-bold tracking-tight text-foreground"
                    >
                        {{ title }}
                    </h2>
                    <p
                        v-if="description"
                        class="mt-1 text-sm leading-5 text-muted-foreground"
                    >
                        {{ description }}
                    </p>
                </div>
                <button
                    v-if="showCloseButton"
                    type="button"
                    class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-all hover:bg-muted hover:text-foreground active:scale-90"
                    aria-label="Close"
                    @click="handleClose"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Content (scrollable) -->
            <div
                ref="contentRef"
                data-test="mobile-bottom-sheet-content"
                data-lenis-prevent
                class="min-h-0 grow overflow-y-auto overscroll-contain px-2 pb-4"
            >
                <slot />
            </div>

            <!-- Footer: pinned below the scrollable content so action buttons
                 (e.g. Close) are always reachable, mirroring DialogFooter -->
            <div
                v-if="$slots.footer"
                class="flex shrink-0 flex-col-reverse gap-2 border-t border-border/60 px-4 pt-3 pb-2"
            >
                <slot name="footer" />
            </div>
        </div>
    </Teleport>
</template>
