<script setup lang="ts">
import axios from 'axios';
import {
    Check,
    CheckCircle2,
    Clock3,
    LoaderCircle,
    ShieldCheck,
    TriangleAlert,
    X,
    XCircle,
} from 'lucide-vue-next';
import { computed, ref } from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Button } from '@/components/ui/button';
import type { PendingAiAction } from '@/types/aiActions';

const props = defineProps<{
    action: PendingAiAction;
    compact?: boolean;
}>();

const emit = defineEmits<{
    updated: [action: PendingAiAction];
}>();

const approvalOpen = ref(false);
const isApproving = ref(false);
const isRejecting = ref(false);
const requestError = ref<string | null>(null);

const isPending = computed(
    () => props.action.status === 'pending' && Boolean(props.action.nonce),
);

const statusLabel = computed(() => {
    switch (props.action.status) {
        case 'pending':
            return 'Needs your approval';
        case 'executing':
            return 'Executing';
        case 'executed':
            return 'Executed';
        case 'rejected':
            return 'Rejected';
        case 'failed':
            return 'Failed';
        case 'expired':
            return 'Expired';
        default:
            return props.action.status;
    }
});

const statusClass = computed(() => {
    switch (props.action.status) {
        case 'pending':
            return 'border-amber-500/30 bg-amber-500/10 text-amber-700 dark:text-amber-300';
        case 'executing':
            return 'border-blue-500/30 bg-blue-500/10 text-blue-700 dark:text-blue-300';
        case 'executed':
            return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-700 dark:text-emerald-300';
        case 'failed':
            return 'border-destructive/30 bg-destructive/10 text-destructive';
        default:
            return 'border-border bg-muted/50 text-muted-foreground';
    }
});

const expiresLabel = computed(() => {
    if (!props.action.expiresAt) return null;
    const date = new Date(props.action.expiresAt);
    return Number.isNaN(date.getTime())
        ? null
        : date.toLocaleTimeString([], { hour: 'numeric', minute: '2-digit' });
});

const formatValue = (value: unknown): string => {
    if (value === null || value === undefined || value === '') return 'Not set';
    if (typeof value === 'boolean') return value ? 'Yes' : 'No';
    if (typeof value === 'object') return JSON.stringify(value, null, 2);
    return String(value);
};

const errorPayload = (
    error: unknown,
): { message?: string; data?: PendingAiAction } | null => {
    if (!axios.isAxiosError(error)) return null;
    return (
        (error.response?.data as {
            message?: string;
            data?: PendingAiAction;
        }) ?? null
    );
};

const errorMessage = (error: unknown): string =>
    errorPayload(error)?.message ??
    'The action could not be updated. Refresh and try again.';

const approve = async () => {
    if (!props.action.nonce || isApproving.value) return;
    isApproving.value = true;
    requestError.value = null;

    try {
        const response = await axios.post(
            `/api/ai-actions/${props.action.id}/approve`,
            { nonce: props.action.nonce },
        );
        approvalOpen.value = false;
        emit('updated', response.data.data as PendingAiAction);
    } catch (error) {
        const payload = errorPayload(error);
        if (payload?.data) emit('updated', payload.data);
        requestError.value = errorMessage(error);
        approvalOpen.value = false;
    } finally {
        isApproving.value = false;
    }
};

const reject = async () => {
    if (!props.action.nonce || isRejecting.value) return;
    isRejecting.value = true;
    requestError.value = null;

    try {
        const response = await axios.post(
            `/api/ai-actions/${props.action.id}/reject`,
            { nonce: props.action.nonce },
        );
        emit('updated', response.data.data as PendingAiAction);
    } catch (error) {
        const payload = errorPayload(error);
        if (payload?.data) emit('updated', payload.data);
        requestError.value = errorMessage(error);
    } finally {
        isRejecting.value = false;
    }
};
</script>

<template>
    <section
        data-testid="ai-action-approval"
        class="w-full rounded-xl border border-amber-500/25 bg-amber-500/[0.04] p-3 shadow-sm"
        :class="compact ? 'text-[11px]' : 'text-xs'"
        :aria-label="`AI action: ${action.title}`"
    >
        <div class="flex items-start justify-between gap-2">
            <div class="flex min-w-0 items-start gap-2">
                <div
                    class="mt-0.5 flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-amber-500/10 text-amber-600 dark:text-amber-300"
                >
                    <ShieldCheck class="h-4 w-4" />
                </div>
                <div class="min-w-0">
                    <p class="font-semibold text-foreground">
                        {{ action.title }}
                    </p>
                    <p class="mt-0.5 leading-relaxed text-muted-foreground">
                        {{ action.summary }}
                    </p>
                </div>
            </div>
            <span
                class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-semibold"
                :class="statusClass"
            >
                {{ statusLabel }}
            </span>
        </div>

        <div
            class="mt-3 overflow-hidden rounded-lg border border-border/60 bg-background/70"
        >
            <div
                v-for="(change, index) in action.changes"
                :key="`${change.field}-${index}`"
                class="grid gap-1 border-b border-border/50 p-2.5 last:border-b-0 sm:grid-cols-[8rem_minmax(0,1fr)]"
            >
                <span class="font-medium text-muted-foreground">
                    {{ change.field }}
                </span>
                <div class="min-w-0 space-y-1">
                    <div
                        v-if="
                            change.before !== null &&
                            change.before !== undefined
                        "
                        class="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-1"
                    >
                        <span
                            class="text-[10px] font-semibold text-muted-foreground"
                            >FROM</span
                        >
                        <pre
                            class="max-h-36 overflow-auto font-sans leading-relaxed break-words whitespace-pre-wrap text-muted-foreground line-through decoration-destructive/50"
                            >{{ formatValue(change.before) }}</pre
                        >
                    </div>
                    <div class="grid grid-cols-[2.5rem_minmax(0,1fr)] gap-1">
                        <span
                            class="text-[10px] font-semibold text-emerald-700 dark:text-emerald-300"
                            >TO</span
                        >
                        <pre
                            class="max-h-48 overflow-auto font-sans leading-relaxed break-words whitespace-pre-wrap text-foreground"
                            >{{ formatValue(change.after) }}</pre
                        >
                    </div>
                </div>
            </div>
        </div>

        <div
            v-if="action.workspace || (isPending && expiresLabel)"
            class="mt-2 flex flex-wrap items-center gap-x-3 gap-y-1 text-[10px] text-muted-foreground"
        >
            <span v-if="action.workspace"
                >Workspace: {{ action.workspace.name }}</span
            >
            <span
                v-if="isPending && expiresLabel"
                class="flex items-center gap-1"
            >
                <Clock3 class="h-3 w-3" /> Expires at {{ expiresLabel }}
            </span>
        </div>

        <p
            v-if="requestError || action.error"
            class="mt-2 flex items-start gap-1.5 rounded-md bg-destructive/10 p-2 text-destructive"
            role="alert"
        >
            <TriangleAlert class="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <span>{{ requestError || action.error }}</span>
        </p>

        <p
            v-if="action.result"
            class="mt-2 flex items-start gap-1.5 rounded-md bg-emerald-500/10 p-2 text-emerald-700 dark:text-emerald-300"
            role="status"
        >
            <CheckCircle2 class="mt-0.5 h-3.5 w-3.5 shrink-0" />
            <span>{{ action.result }}</span>
        </p>

        <div v-if="isPending" class="mt-3 flex justify-end gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                :disabled="isRejecting || isApproving"
                @click="reject"
            >
                <LoaderCircle
                    v-if="isRejecting"
                    class="h-3.5 w-3.5 animate-spin"
                />
                <X v-else class="h-3.5 w-3.5" />
                {{ isRejecting ? 'Rejecting…' : 'Reject' }}
            </Button>
            <Button
                type="button"
                size="sm"
                :disabled="isRejecting || isApproving"
                @click="approvalOpen = true"
            >
                <Check class="h-3.5 w-3.5" /> Review &amp; approve
            </Button>
        </div>

        <div
            v-else-if="action.status === 'executing'"
            class="mt-3 flex items-center gap-2 text-blue-700 dark:text-blue-300"
            role="status"
        >
            <LoaderCircle class="h-4 w-4 animate-spin" />
            This action is executing. Repeated approval clicks cannot run it
            twice.
        </div>
        <div
            v-else-if="
                action.status === 'rejected' || action.status === 'expired'
            "
            class="mt-3 flex items-center gap-2 text-muted-foreground"
        >
            <XCircle class="h-4 w-4" /> No changes were made.
        </div>

        <ResponsiveModal
            :open="approvalOpen"
            title="Approve this AI action?"
            description="This is the only step that can execute the write. Review every value below before continuing."
            content-class="max-w-2xl"
            @close="approvalOpen = false"
        >
            <div class="max-h-[55vh] space-y-2 overflow-y-auto pr-1">
                <p class="text-sm font-semibold text-foreground">
                    {{ action.title }}
                </p>
                <p class="text-xs leading-relaxed text-muted-foreground">
                    {{ action.summary }}
                </p>
                <div
                    v-for="(change, index) in action.changes"
                    :key="`modal-${change.field}-${index}`"
                    class="rounded-lg border border-border/60 p-2.5"
                >
                    <p class="text-xs font-semibold text-foreground">
                        {{ change.field }}
                    </p>
                    <div
                        v-if="
                            change.before !== null &&
                            change.before !== undefined
                        "
                        class="mt-1 text-[11px] text-muted-foreground"
                    >
                        <span class="font-semibold">From:</span>
                        <pre
                            class="mt-0.5 max-h-32 overflow-auto font-sans break-words whitespace-pre-wrap"
                            >{{ formatValue(change.before) }}</pre
                        >
                    </div>
                    <div class="mt-1 text-[11px] text-foreground">
                        <span
                            class="font-semibold text-emerald-700 dark:text-emerald-300"
                            >To:</span
                        >
                        <pre
                            class="mt-0.5 max-h-40 overflow-auto font-sans break-words whitespace-pre-wrap"
                            >{{ formatValue(change.after) }}</pre
                        >
                    </div>
                </div>
            </div>

            <template #footer>
                <div
                    class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end"
                >
                    <Button
                        type="button"
                        variant="outline"
                        :disabled="isApproving"
                        @click="approvalOpen = false"
                    >
                        Cancel
                    </Button>
                    <Button
                        type="button"
                        :disabled="isApproving"
                        @click="approve"
                    >
                        <LoaderCircle
                            v-if="isApproving"
                            class="h-4 w-4 animate-spin"
                        />
                        <Check v-else class="h-4 w-4" />
                        {{ isApproving ? 'Executing…' : 'Approve & execute' }}
                    </Button>
                </div>
            </template>
        </ResponsiveModal>
    </section>
</template>
