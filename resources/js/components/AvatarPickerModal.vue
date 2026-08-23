<script setup lang="ts">
import { Check } from 'lucide-vue-next';
import { ref, watch } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export type AvatarGalleryItem = {
    path: string;
    name: string;
    url: string;
};

type Props = {
    open: boolean;
    avatars: AvatarGalleryItem[];
    selectedPath: string | null;
};

const props = defineProps<Props>();

const emit = defineEmits<{
    close: [];
    apply: [path: string];
}>();

const pendingPath = ref<string | null>(props.selectedPath);

watch(
    () => [props.open, props.selectedPath] as const,
    ([open, selectedPath]) => {
        if (open) {
            pendingPath.value = selectedPath;
        }
    },
);

const handleOpenChange = (open: boolean) => {
    if (!open) {
        emit('close');
    }
};

const applySelection = () => {
    if (pendingPath.value) {
        emit('apply', pendingPath.value);
    }
};
</script>

<template>
    <Dialog :open="open" @update:open="handleOpenChange">
        <DialogContent class="max-w-2xl gap-5">
            <DialogHeader>
                <DialogTitle>Choose an avatar</DialogTitle>
                <DialogDescription>
                    Pick a profile picture from the LuaV6 collection. Your
                    choice will be applied when you save your profile.
                </DialogDescription>
            </DialogHeader>

            <div
                v-if="avatars.length"
                class="grid max-h-[min(55vh,30rem)] grid-cols-3 gap-3 overflow-y-auto p-1 sm:grid-cols-4 md:grid-cols-5"
                role="listbox"
                aria-label="Available avatars"
            >
                <button
                    v-for="avatar in avatars"
                    :key="avatar.path"
                    type="button"
                    role="option"
                    :aria-label="`Choose ${avatar.name}`"
                    :aria-selected="pendingPath === avatar.path"
                    class="group relative aspect-square overflow-hidden rounded-full border-2 border-border/70 bg-muted/30 p-0.5 transition hover:-translate-y-0.5 hover:border-primary/70 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                    :class="{
                        'border-primary p-1 shadow-lg':
                            pendingPath === avatar.path,
                    }"
                    @click="pendingPath = avatar.path"
                >
                    <img
                        :src="avatar.url"
                        :alt="avatar.name"
                        loading="lazy"
                        class="h-full w-full rounded-full object-cover"
                    />
                    <span
                        v-if="pendingPath === avatar.path"
                        class="absolute right-1 bottom-1 flex size-6 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-sm"
                    >
                        <Check class="size-4" aria-hidden="true" />
                        <span class="sr-only">Selected</span>
                    </span>
                </button>
            </div>

            <p
                v-else
                class="rounded-lg border border-dashed border-border p-8 text-center text-sm text-muted-foreground"
            >
                The avatar collection is not available right now.
            </p>

            <DialogFooter>
                <Button type="button" variant="outline" @click="emit('close')">
                    Cancel
                </Button>
                <Button
                    type="button"
                    :disabled="!pendingPath"
                    @click="applySelection"
                >
                    Use this avatar
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>
</template>
