<script setup lang="ts">
import { computed } from 'vue';
import MobileBottomSheet from '@/components/MobileBottomSheet.vue';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { useMobile } from '@/composables/useMobile';

type Props = {
    modelValue?: boolean;
    open?: boolean;
    title?: string;
    description?: string;
    contentClass?: string;
    /**
     * When true, consumers provide their own DialogHeader / DialogTitle
     * via the `header` slot (desktop) or inline in the default slot (mobile).
     */
    customHeader?: boolean;
    /**
     * Show the close button on the bottom sheet (mobile only).
     */
    showSheetClose?: boolean;
    /**
     * When true, prevents closing via backdrop click or escape key.
     * Useful for forced onboarding modals like SectionSelectionModal.
     */
    preventClose?: boolean;
};

const props = withDefaults(defineProps<Props>(), {
    modelValue: undefined,
    open: undefined,
    title: '',
    description: '',
    contentClass: '',
    customHeader: false,
    showSheetClose: true,
    preventClose: false,
});

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    close: [];
}>();

const { isMobile } = useMobile();

const isOpen = computed(() => props.modelValue ?? props.open ?? false);

function handleClose() {
    if (props.preventClose) return;
    emit('update:modelValue', false);
    emit('close');
}

function handleDesktopOpenChange(v: boolean) {
    if (!v) handleClose();
}
</script>

<template>
    <!-- ════════════════════ MOBILE: Bottom Sheet ════════════════════ -->
    <template v-if="isMobile">
        <MobileBottomSheet
            :open="isOpen"
            :title="title"
            :show-close-button="showSheetClose"
            @close="handleClose"
        >
            <!-- Custom header content (renders inline in sheet) -->
            <slot name="header" />
            <!-- Main content -->
            <slot />
            <!-- Footer buttons -->
            <slot name="footer" />
        </MobileBottomSheet>
    </template>

    <!-- ════════════════════ DESKTOP: Centered Dialog ════════════════════ -->
    <template v-else>
        <Dialog
            :open="isOpen"
            @update:open="handleDesktopOpenChange"
        >
            <!-- Trigger button slot (for DialogTrigger pattern) -->
            <DialogTrigger v-if="$slots.trigger" as-child>
                <slot name="trigger" />
            </DialogTrigger>

            <DialogContent :class="contentClass">
                <!-- Custom header -->
                <template v-if="customHeader && $slots.header">
                    <DialogHeader>
                        <slot name="header" />
                    </DialogHeader>
                </template>
                <!-- Default header from props -->
                <template v-else-if="title || description">
                    <DialogHeader>
                        <DialogTitle v-if="title">{{ title }}</DialogTitle>
                        <DialogDescription v-if="description">
                            {{ description }}
                        </DialogDescription>
                    </DialogHeader>
                </template>

                <!-- Default slot (main content) -->
                <slot />

                <!-- Footer -->
                <template v-if="$slots.footer">
                    <DialogFooter>
                        <slot name="footer" />
                    </DialogFooter>
                </template>
            </DialogContent>
        </Dialog>
    </template>
</template>
