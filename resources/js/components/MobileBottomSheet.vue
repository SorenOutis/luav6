<script setup lang="ts">
import { gsap } from 'gsap';
import { X } from 'lucide-vue-next';
import { ref, watch, onMounted, nextTick } from 'vue';

type Props = {
    open: boolean;
    title?: string;
    showCloseButton?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    title: '',
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
            class="fixed inset-0 z-[60] bg-black/40 backdrop-blur-sm"
            @click="handleBackdropClick"
        />

        <!-- Sheet -->
        <div
            v-if="open"
            ref="sheetRef"
            class="fixed right-0 bottom-0 left-0 z-[70] mx-auto max-w-lg rounded-t-3xl border-t border-border/60 bg-background/95 shadow-2xl shadow-black/20 backdrop-blur-2xl"
            style="
                padding-bottom: calc(env(safe-area-inset-bottom) + 0.5rem);
            "
        >
            <!-- Grab Handle -->
            <div
                class="mx-auto mt-2 h-1.5 w-10 rounded-full bg-muted-foreground/20"
            />

            <!-- Header -->
            <div
                v-if="title || showCloseButton"
                class="flex items-center justify-between px-6 pt-4 pb-2"
            >
                <h2
                    class="text-base font-black tracking-tight text-foreground"
                >
                    {{ title }}
                </h2>
                <button
                    v-if="showCloseButton"
                    @click="handleClose"
                    class="flex h-10 w-10 items-center justify-center rounded-full text-muted-foreground transition-all hover:bg-muted hover:text-foreground active:scale-90"
                    aria-label="Close"
                >
                    <X class="h-5 w-5" />
                </button>
            </div>

            <!-- Content -->
            <div ref="contentRef" class="px-2 pb-4">
                <slot />
            </div>
        </div>
    </Teleport>
</template>
