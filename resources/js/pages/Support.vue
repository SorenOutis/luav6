<script setup lang="ts">
import { Head, router, useForm, usePage } from '@inertiajs/vue3';
import { useIntervalFn } from '@vueuse/core';
import {
    ChevronLeft,
    LifeBuoy,
    MessagesSquare,
    Paperclip,
    Plus,
    Send,
    X,
} from 'lucide-vue-next';
import { computed, nextTick, ref, watch } from 'vue';
import InputError from '@/components/InputError.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem } from '@/types';

interface TicketReply {
    id: number;
    message: string;
    created_at: string | null;
    user: {
        id: number;
        name: string;
        avatar: string | null;
        is_mine: boolean;
    };
}

interface Ticket {
    id: number;
    subject: string;
    category: string;
    category_label: string;
    priority: string;
    priority_label: string;
    message: string;
    status: string;
    status_label: string;
    attachment_url: string | null;
    resolved_at: string | null;
    created_at: string | null;
    replies: TicketReply[];
}

const props = defineProps<{
    tickets: Ticket[];
    categories: Record<string, string>;
    priorities: Record<string, string>;
    dailyTicketLimit: number;
    ticketsUsedToday: number;
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Support', href: '/support' },
];

const page = usePage();

// Replies from the team are shown under the School / Platform Name so no
// individual admin account is ever exposed to students.
const supportName = computed(
    () => page.props.schoolBranding?.name?.trim() || 'Support Team',
);

const replyAuthorName = (reply: TicketReply): string =>
    reply.user.is_mine ? 'You' : supportName.value;

// School logo uploaded in Platform Settings (School Branding). Falls back
// to the generic icon when none is set.
const schoolLogoUrl = computed(
    () => page.props.schoolBranding?.logoUrl ?? null,
);

const ticketsRemaining = computed(() =>
    Math.max(0, props.dailyTicketLimit - props.ticketsUsedToday),
);
const canSubmitTicket = computed(() => ticketsRemaining.value > 0);

// Closed tickets never change for the student, so there is no reason to
// keep polling once everything is resolved.
const hasOpenTicket = computed(() =>
    props.tickets.some((ticket) => !isTicketClosed(ticket)),
);

const form = useForm({
    subject: '',
    category: 'general',
    priority: 'medium',
    message: '',
    attachment: null as File | null,
});

const fileInput = ref<HTMLInputElement | null>(null);
const fileError = ref<string | null>(null);

// Chat-style navigation state: which ticket is open in the conversation
// pane, or whether the compose form is shown instead. Visiting the page
// lands on the overview — never auto-opens a conversation.
const activeTicketId = ref<number | null>(null);
const composing = ref<boolean>(props.tickets.length === 0);
const selectNewestOnLoad = ref(false);
const conversationScroll = ref<HTMLElement | null>(null);
const previousReplyCount = ref(0);

// Sidebar filter driven by the overview status cards (null = show all).
const statusFilter = ref<string | null>(null);

const statusCounts = computed<Record<string, number>>(() => {
    const counts: Record<string, number> = {
        open: 0,
        in_progress: 0,
        resolved: 0,
    };
    for (const ticket of props.tickets) {
        if (ticket.status in counts) {
            counts[ticket.status] += 1;
        }
    }
    return counts;
});

const filteredTickets = computed<Ticket[]>(() =>
    statusFilter.value === null
        ? props.tickets
        : props.tickets.filter(
              (ticket) => ticket.status === statusFilter.value,
          ),
);

const statusFilterLabel = computed<string>(() => {
    const match = props.tickets.find(
        (ticket) => ticket.status === statusFilter.value,
    );
    return match?.status_label ?? statusFilter.value ?? '';
});

const toggleStatusFilter = (status: string) => {
    statusFilter.value = statusFilter.value === status ? null : status;
};

const activeTicket = computed<Ticket | null>(
    () =>
        props.tickets.find((ticket) => ticket.id === activeTicketId.value) ??
        null,
);

// On small screens only one pane is visible at a time: the list until the
// student picks a ticket or starts composing.
const showList = computed(
    () => activeTicketId.value === null && !composing.value,
);

const scrollConversationToBottom = async () => {
    await nextTick();
    const el = conversationScroll.value;
    if (el) {
        el.scrollTop = el.scrollHeight;
    }
};

watch(
    () => props.tickets,
    (tickets) => {
        if (selectNewestOnLoad.value && tickets.length > 0) {
            selectNewestOnLoad.value = false;
            activeTicketId.value = tickets[0].id;
            composing.value = false;
        } else if (tickets.length === 0) {
            activeTicketId.value = null;
            composing.value = true;
        } else if (
            activeTicketId.value !== null &&
            !tickets.some((ticket) => ticket.id === activeTicketId.value)
        ) {
            // The open ticket is gone — fall back to the overview, never
            // auto-open another conversation.
            activeTicketId.value = null;
        }

        const replyCount = activeTicket.value?.replies.length ?? 0;
        if (replyCount > previousReplyCount.value) {
            scrollConversationToBottom();
        }
        previousReplyCount.value = replyCount;
    },
);

watch(activeTicketId, () => {
    previousReplyCount.value = activeTicket.value?.replies.length ?? 0;
    scrollConversationToBottom();
});

const selectTicket = (ticketId: number) => {
    activeTicketId.value = ticketId;
    composing.value = false;
};

const startCompose = () => {
    composing.value = true;
};

const backToList = () => {
    composing.value = false;
    activeTicketId.value = null;
};

const replyForms = ref<
    Record<
        number,
        { message: string; processing: boolean; error: string | null }
    >
>({});

const replyState = (ticketId: number) => {
    if (!replyForms.value[ticketId]) {
        replyForms.value[ticketId] = {
            message: '',
            processing: false,
            error: null,
        };
    }
    return replyForms.value[ticketId];
};

const validateAndSetFile = (file: File | null) => {
    fileError.value = null;
    if (!file) {
        form.attachment = null;
        return;
    }

    const MAX_SIZE = 10 * 1024 * 1024;
    if (file.size > MAX_SIZE) {
        fileError.value = 'File size exceeds the 10 MB limit.';
        return;
    }

    const allowed = ['jpg', 'jpeg', 'png', 'webp', 'pdf', 'doc', 'docx', 'txt'];
    const ext = file.name.split('.').pop()?.toLowerCase() ?? '';
    if (!allowed.includes(ext)) {
        fileError.value =
            'Unsupported file type. Use an image, PDF, DOC, or TXT file.';
        return;
    }

    form.attachment = file;
};

const onFileChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    validateAndSetFile(input.files?.[0] ?? null);
};

const clearFile = () => {
    form.attachment = null;
    fileError.value = null;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
};

const submit = () => {
    fileError.value = null;
    form.post('/support', {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: () => {
            form.reset('subject', 'message', 'attachment');
            form.category = 'general';
            form.priority = 'medium';
            clearFile();
            selectNewestOnLoad.value = true;
        },
    });
};

const submitReply = (ticket: Ticket) => {
    if (isTicketClosed(ticket)) {
        return;
    }

    const state = replyState(ticket.id);
    if (!state.message.trim() || state.processing) {
        return;
    }

    state.processing = true;
    state.error = null;

    const replyForm = useForm({ message: state.message });
    replyForm.post(`/support/${ticket.id}/reply`, {
        preserveScroll: true,
        onSuccess: () => {
            state.message = '';
            state.processing = false;
        },
        onError: () => {
            state.error = 'Could not send your reply. Please try again.';
            state.processing = false;
        },
    });
};

const statusVariant = (
    status: string,
): 'default' | 'secondary' | 'destructive' | 'outline' => {
    if (status === 'open') {
        return 'destructive';
    }
    if (status === 'in_progress') {
        return 'default';
    }
    return 'secondary';
};

const isTicketClosed = (ticket: Ticket): boolean => {
    return ticket.status === 'resolved' || ticket.status === 'closed';
};

const formatDate = (value: string | null) => {
    if (!value) {
        return '';
    }
    return new Date(value).toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

// Live conversation via polling: refresh just the ticket list every few
// seconds so team replies appear without a manual reload. Skipped when the
// tab is hidden, while a submit is in flight, or when there is nothing to
// refresh. Stops automatically when the page unmounts.
useIntervalFn(
    () => {
        if (
            document.hidden ||
            form.processing ||
            props.tickets.length === 0 ||
            !hasOpenTicket.value
        ) {
            return;
        }

        const replyBusy = Object.values(replyForms.value).some(
            (state) => state.processing,
        );
        if (replyBusy) {
            return;
        }

        router.reload({ only: ['tickets'] });
    },
    10000,
    { immediate: false },
);
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Support" />

        <div
            class="flex h-[calc(100dvh-7.25rem)] min-h-0 w-full flex-col md:h-[calc(100dvh-4.5rem)]"
        >
            <div
                class="mb-2 flex shrink-0 items-center gap-2.5 px-4 pt-3 sm:px-6"
            >
                <div
                    class="flex h-9 w-9 items-center justify-center overflow-hidden rounded-xl bg-primary/10 text-primary"
                >
                    <img
                        v-if="schoolLogoUrl"
                        :src="schoolLogoUrl"
                        alt="School logo"
                        class="h-7 w-7 object-contain"
                    />
                    <LifeBuoy v-else class="h-4 w-4" />
                </div>
                <div>
                    <h1 class="text-xl leading-tight font-bold tracking-tight">
                        Support
                    </h1>
                    <p class="text-xs text-muted-foreground">
                        Submit a concern and track replies from our team.
                    </p>
                </div>
            </div>

            <div class="flex min-h-0 flex-1 border-t border-border/60">
                <!-- ─── Ticket sidebar ─── -->
                <aside
                    :class="[
                        showList ? 'flex' : 'hidden',
                        'min-h-0 w-full flex-col border-border/60 md:flex md:w-72 md:shrink-0 md:border-r',
                    ]"
                >
                    <div class="border-b border-border/60 p-3">
                        <Button
                            variant="outline"
                            class="w-full"
                            @click="startCompose"
                        >
                            <Plus class="mr-2 h-4 w-4" />
                            New ticket
                        </Button>
                    </div>

                    <div
                        v-if="statusFilter !== null"
                        class="flex shrink-0 items-center justify-between gap-2 border-b border-border/60 px-3 py-2"
                    >
                        <span class="truncate text-xs text-muted-foreground">
                            Showing: {{ statusFilterLabel }}
                        </span>
                        <button
                            type="button"
                            class="shrink-0 rounded-full p-1 text-muted-foreground hover:bg-muted"
                            aria-label="Clear filter"
                            @click="statusFilter = null"
                        >
                            <X class="h-3.5 w-3.5" />
                        </button>
                    </div>

                    <div class="min-h-0 flex-1 space-y-1 overflow-y-auto p-2">
                        <p
                            v-if="tickets.length === 0"
                            class="px-3 py-8 text-center text-sm text-muted-foreground"
                        >
                            No tickets yet. Create your first one to get help
                            from our team.
                        </p>
                        <p
                            v-else-if="filteredTickets.length === 0"
                            class="px-3 py-8 text-center text-sm text-muted-foreground"
                        >
                            No {{ statusFilterLabel.toLowerCase() }} tickets.
                        </p>
                        <button
                            v-for="ticket in filteredTickets"
                            :key="ticket.id"
                            type="button"
                            class="w-full rounded-xl p-3 text-left transition-colors"
                            :class="
                                ticket.id === activeTicketId && !composing
                                    ? 'bg-[#D97757]/10 hover:bg-[#D97757]/15'
                                    : 'hover:bg-muted/60'
                            "
                            @click="selectTicket(ticket.id)"
                        >
                            <span class="block truncate text-sm font-semibold">
                                {{ ticket.subject }}
                            </span>
                            <span class="mt-1.5 flex items-center gap-2">
                                <Badge :variant="statusVariant(ticket.status)">
                                    {{ ticket.status_label }}
                                </Badge>
                                <span
                                    class="min-w-0 flex-1 truncate text-[11px] text-muted-foreground"
                                >
                                    {{ ticket.category_label }} ·
                                    {{ formatDate(ticket.created_at) }}
                                </span>
                            </span>
                        </button>
                    </div>
                </aside>

                <!-- ─── Conversation / compose pane ─── -->
                <section
                    :class="[
                        showList ? 'hidden' : 'flex',
                        'min-h-0 min-w-0 flex-1 flex-col md:flex',
                    ]"
                >
                    <!-- Compose mode -->
                    <template v-if="composing">
                        <div
                            class="flex items-center gap-2 border-b border-border/60 p-4"
                        >
                            <Button
                                v-if="tickets.length > 0"
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 md:hidden"
                                aria-label="Back to tickets"
                                @click="backToList"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <h2 class="text-base font-bold">Submit a ticket</h2>
                        </div>

                        <div class="min-h-0 flex-1 overflow-y-auto p-4 sm:p-6">
                            <form
                                class="mx-auto w-full max-w-2xl space-y-4"
                                @submit.prevent="submit"
                            >
                                <div class="space-y-2">
                                    <Label for="support-subject">Subject</Label>
                                    <Input
                                        id="support-subject"
                                        v-model="form.subject"
                                        placeholder="Brief summary of your concern"
                                        maxlength="255"
                                    />
                                    <InputError
                                        :message="form.errors.subject"
                                    />
                                </div>

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <div class="space-y-2">
                                        <Label for="support-category"
                                            >Category</Label
                                        >
                                        <Select v-model="form.category">
                                            <SelectTrigger
                                                id="support-category"
                                                class="w-full"
                                            >
                                                <SelectValue
                                                    placeholder="Select a category"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="(
                                                        label, value
                                                    ) in categories"
                                                    :key="value"
                                                    :value="value"
                                                >
                                                    {{ label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            :message="form.errors.category"
                                        />
                                    </div>

                                    <div class="space-y-2">
                                        <Label for="support-priority"
                                            >Priority</Label
                                        >
                                        <Select v-model="form.priority">
                                            <SelectTrigger
                                                id="support-priority"
                                                class="w-full"
                                            >
                                                <SelectValue
                                                    placeholder="Select a priority"
                                                />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem
                                                    v-for="(
                                                        label, value
                                                    ) in priorities"
                                                    :key="value"
                                                    :value="value"
                                                >
                                                    {{ label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                        <InputError
                                            :message="form.errors.priority"
                                        />
                                    </div>
                                </div>

                                <div class="space-y-2">
                                    <Label for="support-message">
                                        Describe your concern
                                    </Label>
                                    <Textarea
                                        id="support-message"
                                        v-model="form.message"
                                        placeholder="Tell us what happened, what you expected, and any steps to reproduce it…"
                                        class="min-h-32"
                                        maxlength="5000"
                                    />
                                    <InputError
                                        :message="form.errors.message"
                                    />
                                </div>

                                <div class="space-y-2">
                                    <Label for="support-attachment">
                                        Attachment (optional)
                                    </Label>
                                    <div
                                        class="flex flex-wrap items-center gap-2"
                                    >
                                        <input
                                            id="support-attachment"
                                            ref="fileInput"
                                            type="file"
                                            accept=".jpg,.jpeg,.png,.webp,.pdf,.doc,.docx,.txt"
                                            class="hidden"
                                            @change="onFileChange"
                                        />
                                        <Button
                                            type="button"
                                            variant="outline"
                                            @click="fileInput?.click()"
                                        >
                                            <Paperclip class="mr-2 h-4 w-4" />
                                            {{
                                                form.attachment
                                                    ? 'Change file'
                                                    : 'Attach screenshot or file'
                                            }}
                                        </Button>
                                        <span
                                            v-if="form.attachment"
                                            class="flex items-center gap-2 text-sm text-muted-foreground"
                                        >
                                            {{ form.attachment.name }}
                                            <button
                                                type="button"
                                                class="rounded-full p-1 hover:bg-muted"
                                                aria-label="Remove attachment"
                                                @click="clearFile"
                                            >
                                                <X class="h-3.5 w-3.5" />
                                            </button>
                                        </span>
                                    </div>
                                    <p
                                        v-if="fileError"
                                        class="text-sm text-destructive"
                                    >
                                        {{ fileError }}
                                    </p>
                                    <InputError
                                        :message="form.errors.attachment"
                                    />
                                    <p class="text-xs text-muted-foreground">
                                        Images, PDF, DOC, or TXT up to 10 MB.
                                    </p>
                                </div>

                                <Button
                                    type="submit"
                                    :disabled="
                                        form.processing || !canSubmitTicket
                                    "
                                >
                                    <Send class="mr-2 h-4 w-4" />
                                    {{
                                        form.processing
                                            ? 'Submitting…'
                                            : 'Submit ticket'
                                    }}
                                </Button>
                                <p class="text-xs text-muted-foreground">
                                    {{
                                        canSubmitTicket
                                            ? `${ticketsRemaining} of ${dailyTicketLimit} tickets left today.`
                                            : 'Daily ticket limit reached. Please try again tomorrow.'
                                    }}
                                </p>
                                <InputError :message="form.errors.limit" />
                            </form>
                        </div>
                    </template>

                    <!-- Ticket conversation mode -->
                    <template v-else-if="activeTicket !== null">
                        <div
                            class="flex items-center gap-2 border-b border-border/60 p-4"
                        >
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-8 w-8 shrink-0 md:hidden"
                                aria-label="Back to tickets"
                                @click="backToList"
                            >
                                <ChevronLeft class="h-4 w-4" />
                            </Button>
                            <div class="min-w-0 flex-1">
                                <h2 class="truncate text-base font-bold">
                                    {{ activeTicket.subject }}
                                </h2>
                                <div
                                    class="mt-1 flex flex-wrap items-center gap-1.5"
                                >
                                    <Badge
                                        :variant="
                                            statusVariant(activeTicket.status)
                                        "
                                    >
                                        {{ activeTicket.status_label }}
                                    </Badge>
                                    <span
                                        class="text-[11px] text-muted-foreground"
                                    >
                                        {{ activeTicket.category_label }} ·
                                        {{ activeTicket.priority_label }} ·
                                        {{
                                            formatDate(activeTicket.created_at)
                                        }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div
                            ref="conversationScroll"
                            class="min-h-0 flex-1 space-y-4 overflow-y-auto p-4 sm:p-6"
                        >
                            <p
                                v-if="activeTicket.resolved_at"
                                class="text-center text-xs text-muted-foreground"
                            >
                                {{ activeTicket.status_label }} ·
                                {{ formatDate(activeTicket.resolved_at) }}
                            </p>

                            <!-- Original concern -->
                            <div class="flex justify-end">
                                <div
                                    class="max-w-[85%] rounded-2xl rounded-br-md bg-primary px-4 py-2.5 text-sm text-primary-foreground"
                                >
                                    <p
                                        class="mb-1 text-[11px] font-semibold opacity-70"
                                    >
                                        You ·
                                        {{
                                            formatDate(activeTicket.created_at)
                                        }}
                                    </p>
                                    <p class="whitespace-pre-line">
                                        {{ activeTicket.message }}
                                    </p>
                                    <a
                                        v-if="activeTicket.attachment_url"
                                        :href="activeTicket.attachment_url"
                                        target="_blank"
                                        rel="noopener"
                                        class="mt-2 inline-flex items-center gap-1.5 text-xs underline opacity-90"
                                    >
                                        <Paperclip class="h-3.5 w-3.5" />
                                        View attachment
                                    </a>
                                </div>
                            </div>

                            <!-- Replies -->
                            <div
                                v-for="reply in activeTicket.replies"
                                :key="reply.id"
                                class="flex"
                                :class="
                                    reply.user.is_mine
                                        ? 'justify-end'
                                        : 'justify-start'
                                "
                            >
                                <div
                                    class="max-w-[85%] rounded-2xl px-4 py-2.5 text-sm"
                                    :class="
                                        reply.user.is_mine
                                            ? 'rounded-br-md bg-primary text-primary-foreground'
                                            : 'rounded-bl-md bg-muted'
                                    "
                                >
                                    <p
                                        class="mb-1 text-[11px] font-semibold"
                                        :class="
                                            reply.user.is_mine
                                                ? 'opacity-70'
                                                : 'text-muted-foreground'
                                        "
                                    >
                                        {{ replyAuthorName(reply) }}
                                        ·
                                        {{ formatDate(reply.created_at) }}
                                    </p>
                                    <p class="whitespace-pre-line">
                                        {{ reply.message }}
                                    </p>
                                </div>
                            </div>

                            <div
                                v-if="activeTicket.replies.length === 0"
                                class="flex flex-col items-center gap-2 py-6 text-center"
                            >
                                <MessagesSquare
                                    class="h-8 w-8 text-muted-foreground/40"
                                />
                                <p class="text-sm text-muted-foreground">
                                    No replies yet. Our team will respond here.
                                </p>
                            </div>
                        </div>

                        <div class="border-t border-border/60 p-3 sm:p-4">
                            <p
                                v-if="isTicketClosed(activeTicket)"
                                class="rounded-xl bg-muted/60 p-3 text-center text-sm text-muted-foreground"
                            >
                                This conversation is closed. The ticket was
                                marked as
                                {{ activeTicket.status_label.toLowerCase() }}
                                <span v-if="activeTicket.resolved_at">
                                    on
                                    {{
                                        formatDate(activeTicket.resolved_at)
                                    }}</span
                                >.
                            </p>
                            <form
                                v-else
                                class="flex gap-2"
                                @submit.prevent="submitReply(activeTicket)"
                            >
                                <Input
                                    v-model="
                                        replyState(activeTicket.id).message
                                    "
                                    placeholder="Write a follow-up…"
                                    maxlength="2000"
                                />
                                <Button
                                    type="submit"
                                    :disabled="
                                        replyState(activeTicket.id)
                                            .processing ||
                                        !replyState(
                                            activeTicket.id,
                                        ).message.trim()
                                    "
                                >
                                    <Send class="h-4 w-4 sm:mr-2" />
                                    <span class="hidden sm:inline">Send</span>
                                </Button>
                            </form>
                            <p
                                v-if="
                                    !isTicketClosed(activeTicket) &&
                                    replyState(activeTicket.id).error
                                "
                                class="mt-2 text-sm text-destructive"
                            >
                                {{ replyState(activeTicket.id).error }}
                            </p>
                        </div>
                    </template>

                    <!-- Overview landing: create a ticket or browse by status -->
                    <template v-else>
                        <div class="min-h-0 flex-1 overflow-y-auto p-6 sm:p-10">
                            <div
                                class="mx-auto flex min-h-full w-full max-w-md flex-col items-center justify-center gap-6 text-center"
                            >
                                <div
                                    class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-2xl bg-primary/10 text-primary"
                                >
                                    <img
                                        v-if="schoolLogoUrl"
                                        :src="schoolLogoUrl"
                                        alt="School logo"
                                        class="h-12 w-12 object-contain"
                                    />
                                    <LifeBuoy v-else class="h-7 w-7" />
                                </div>
                                <div class="max-w-sm space-y-2">
                                    <h2
                                        class="text-xl font-bold tracking-tight"
                                    >
                                        How can we help?
                                    </h2>
                                    <p class="text-sm text-muted-foreground">
                                        Create a ticket and our team will get
                                        back to you, or pick up an existing
                                        conversation from the list.
                                    </p>
                                </div>
                                <Button
                                    size="lg"
                                    :disabled="!canSubmitTicket"
                                    @click="startCompose"
                                >
                                    <Plus class="mr-2 h-4 w-4" />
                                    Create a ticket
                                </Button>
                                <p
                                    v-if="!canSubmitTicket"
                                    class="text-xs text-muted-foreground"
                                >
                                    Daily ticket limit reached. Please try again
                                    tomorrow.
                                </p>

                                <div
                                    v-if="tickets.length > 0"
                                    class="grid w-full max-w-md grid-cols-3 gap-2"
                                >
                                    <button
                                        type="button"
                                        class="rounded-xl border p-3 transition-colors hover:bg-muted/60"
                                        :class="
                                            statusFilter === 'open'
                                                ? 'border-[#D97757] bg-[#D97757]/10'
                                                : 'border-border/60'
                                        "
                                        @click="toggleStatusFilter('open')"
                                    >
                                        <span
                                            class="block text-2xl font-black tabular-nums"
                                        >
                                            {{ statusCounts.open }}
                                        </span>
                                        <span
                                            class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                                        >
                                            Open
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-xl border p-3 transition-colors hover:bg-muted/60"
                                        :class="
                                            statusFilter === 'in_progress'
                                                ? 'border-[#D97757] bg-[#D97757]/10'
                                                : 'border-border/60'
                                        "
                                        @click="
                                            toggleStatusFilter('in_progress')
                                        "
                                    >
                                        <span
                                            class="block text-2xl font-black tabular-nums"
                                        >
                                            {{ statusCounts.in_progress }}
                                        </span>
                                        <span
                                            class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                                        >
                                            Pending
                                        </span>
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-xl border p-3 transition-colors hover:bg-muted/60"
                                        :class="
                                            statusFilter === 'resolved'
                                                ? 'border-[#D97757] bg-[#D97757]/10'
                                                : 'border-border/60'
                                        "
                                        @click="toggleStatusFilter('resolved')"
                                    >
                                        <span
                                            class="block text-2xl font-black tabular-nums"
                                        >
                                            {{ statusCounts.resolved }}
                                        </span>
                                        <span
                                            class="text-[11px] font-semibold tracking-wide text-muted-foreground uppercase"
                                        >
                                            Resolved
                                        </span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>
                </section>
            </div>
        </div>
    </AppLayout>
</template>
