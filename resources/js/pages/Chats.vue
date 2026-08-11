<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { useFileDialog } from '@vueuse/core';
import axios from 'axios';
import {
    ArrowLeft,
    Bot,
    FileText,
    Image as ImageIcon,
    MessageSquare,
    Paperclip,
    Plus,
    Send,
    Square,
    Trash2,
    User,
    X,
} from 'lucide-vue-next';
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
} from '@/components/ui/card';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Textarea } from '@/components/ui/textarea';
import { useMobile } from '@/composables/useMobile';
import AppLayout from '@/layouts/AppLayout.vue';
import { renderMarkdown } from '@/lib/markdown';
import { dashboard } from '@/routes';
import {
    destroy as chatsDestroy,
    index as chatsIndex,
    message as chatsMessage,
    show as chatsShow,
    store as chatsStore,
    stream as chatsStream,
} from '@/routes/chats';
import type { BreadcrumbItem } from '@/types';

interface ChatMessage {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
    attachments?: ChatAttachment[];
    /** True while the reply is still streaming/typing into this bubble. */
    typing?: boolean;
}

interface ChatAttachment {
    name: string;
    size: number;
    mime: string;
    kind: 'image' | 'document';
    file?: File;
    url?: string;
}

interface ChatSession {
    id: number;
    title: string;
    messageCount?: number;
    messages?: ChatMessage[];
    updatedAt?: string | null;
    updatedAtHuman?: string;
}

const props = defineProps<{
    sessions: ChatSession[];
    activeSession?: ChatSession | null;
}>();

const page = usePage();
const { isCoarsePointer } = useMobile();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Chats', href: chatsIndex().url },
];

const branding = computed<{ logoUrl?: string | null; name?: string }>(
    () =>
        (page.props.schoolBranding ?? {}) as {
            logoUrl?: string | null;
            name?: string;
        },
);

const suggestions = computed<{ label: string; message: string }[]>(() => {
    const fromProps = (
        page.props.aiChat as {
            suggestions?: { label: string; message: string }[];
        }
    )?.suggestions;

    return fromProps?.length
        ? fromProps
        : [
              {
                  label: '📋 My Assignments',
                  message: 'What are my upcoming assignments?',
              },
              {
                  label: '📊 My Progress',
                  message: 'Show me my learning progress',
              },
              { label: '🏆 My Streak', message: "What's my current streak?" },
              {
                  label: '📝 Upcoming Exams',
                  message: 'What exams do I have coming up?',
              },
          ];
});

/* ──────────────── Local state ──────────────── */

const sessions = ref<ChatSession[]>([...(props.sessions ?? [])]);
const activeSession = ref<ChatSession | null>(
    props.activeSession ? { ...props.activeSession } : null,
);
const messages = ref<ChatMessage[]>(props.activeSession?.messages ?? []);
const inputMessage = ref('');
const isLoading = ref(false);
const sessionToDelete = ref<ChatSession | null>(null);

// Abort controller for the in-flight reply, so the user can stop generation.
let streamAbortController: AbortController | null = null;

const isAbortError = (error: unknown): boolean =>
    error instanceof DOMException && error.name === 'AbortError';

const stopGenerating = () => {
    streamAbortController?.abort();
    streamAbortController = null;
    isLoading.value = false;
};
const scrollContainer = ref<HTMLElement | null>(null);
const welcomeInputRef = ref<{ $el?: HTMLTextAreaElement | null } | null>(null);

// Attachment drag & drop / picker state
const attachments = ref<ChatAttachment[]>([]);
const isDragging = ref(false);
const dragDepth = ref(0);
const attachmentError = ref<string | null>(null);
let attachmentErrorTimer: ReturnType<typeof setTimeout> | null = null;

const ALLOWED_MIMES = [
    'image/png',
    'image/jpeg',
    'image/webp',
    'image/gif',
    'application/pdf',
    'text/plain',
    'text/csv',
    'text/markdown',
    'text/html',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
];
const MAX_ATTACHMENTS = 4;
const MAX_ATTACHMENT_BYTES = 5 * 1024 * 1024;

const isAdmin = computed(() =>
    Boolean((page.props.aiChat as { isAdmin?: boolean })?.isAdmin),
);

interface DateGroup {
    label: string;
    open: boolean;
    sessions: ChatSession[];
}

const groupLabel = (iso?: string | null): string => {
    if (!iso) return 'Earlier';

    const date = new Date(iso);
    const now = new Date();
    const startOfToday = new Date(
        now.getFullYear(),
        now.getMonth(),
        now.getDate(),
    );
    const startOfDate = new Date(
        date.getFullYear(),
        date.getMonth(),
        date.getDate(),
    );
    const diffDays = Math.round(
        (startOfToday.getTime() - startOfDate.getTime()) / 86_400_000,
    );

    if (diffDays <= 0) return 'Today';
    if (diffDays === 1) return 'Yesterday';
    if (diffDays <= 7) return 'Previous 7 days';

    return 'Earlier';
};

const groupedSessions = computed<DateGroup[]>(() => {
    const labels = ['Today', 'Yesterday', 'Previous 7 days', 'Earlier'];

    return labels.map((label) => ({
        label,
        open: label === 'Today' || label === 'Yesterday',
        sessions: sessions.value.filter(
            (s) => groupLabel(s.updatedAt) === label,
        ),
    }));
});

const showSuggestions = computed(() => {
    if (messages.value.length === 0) return true;
    return !messages.value.some((m) => m.role === 'user');
});

const currentTitle = computed(() => activeSession.value?.title || 'New chat');

// True whenever the welcome view should be shown — either no conversation is
// open yet (the Chats index) or the open conversation has no messages.
const isNewChat = computed(() => messages.value.length === 0);

const firstName = computed(() => {
    const user = page.props.auth.user;
    if (user?.first_name) return user.first_name;
    const fallback = user?.name?.trim().split(/\s+/)[0];
    return fallback || '';
});

const timeGreeting = computed(() => {
    const hour = new Date().getHours();
    if (hour >= 5 && hour < 12) return 'Good morning';
    if (hour >= 12 && hour < 17) return 'Good afternoon';
    return 'Good evening';
});

const greetingLine = computed(() =>
    firstName.value
        ? `${timeGreeting.value}, ${firstName.value}`
        : timeGreeting.value,
);

const activeSubtitle = computed(() => {
    if (isAdmin.value) return 'Teacher mode — workspace tools enabled';
    return 'Your intelligent companion';
});

/* ──────────────── Chat actions ──────────────── */

const scrollToBottom = async () => {
    await nextTick();
    const container = scrollContainer.value as
        | (HTMLElement & { $el?: HTMLElement })
        | null;
    const el = container?.$el || container;
    if (el) {
        el.scrollTop = el.scrollHeight;
    }
};

watch(messages, () => scrollToBottom(), { deep: true });

// Focus the centered input whenever a brand-new chat opens so the user can
// start typing immediately — except on touch devices (coarse pointer), where
// auto-focus would pop open the on-screen keyboard as soon as the chat is
// created. Coarse pointer covers phones in any orientation (landscape phones
// are wider than the 640px mobile breakpoint) plus tablets.
watch(
    isNewChat,
    async (newChat) => {
        if (!newChat) return;
        await nextTick();
        if (isCoarsePointer.value) return;
        welcomeInputRef.value?.$el?.focus();
    },
    { immediate: true },
);

const typeMessage = async (
    fullText: string,
    index?: number,
    signal?: AbortSignal,
) => {
    const messageIndex =
        index ??
        messages.value.push({ role: 'assistant', content: '', typing: true }) -
            1;

    let currentText = '';
    const speed = 8;

    for (let i = 0; i < fullText.length; i++) {
        if (signal?.aborted) {
            break;
        }
        currentText += fullText[i];
        messages.value[messageIndex].content = currentText;
        await new Promise((resolve) => setTimeout(resolve, speed));
        scrollToBottom();
    }

    messages.value[messageIndex].typing = false;
};

const updateSessionInList = (session: ChatSession) => {
    const enriched: ChatSession = {
        ...session,
        messageCount: session.messages?.length ?? session.messageCount,
        updatedAtHuman: 'Now',
    };

    const index = sessions.value.findIndex((s) => s.id === session.id);

    if (index !== -1) {
        sessions.value.splice(index, 1);
    }

    sessions.value.unshift(enriched);
};

const useSuggestion = (suggestion: string) => {
    inputMessage.value = suggestion;
    sendMessage();
};

/* ──────────────── Attachment helpers ──────────────── */

const formatFileSize = (bytes: number): string => {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${Math.round(bytes / 1024)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
};

const isImageMime = (mime: string): boolean => mime.startsWith('image/');

const showAttachmentError = (message: string) => {
    attachmentError.value = message;
    if (attachmentErrorTimer) clearTimeout(attachmentErrorTimer);
    attachmentErrorTimer = setTimeout(() => {
        attachmentError.value = null;
    }, 4000);
};

const addFiles = (fileList: FileList | File[] | null) => {
    if (!fileList) return;

    const files = Array.from(fileList);
    if (files.length === 0) return;

    const remaining = MAX_ATTACHMENTS - attachments.value.length;
    if (remaining <= 0) {
        showAttachmentError(
            `You can attach up to ${MAX_ATTACHMENTS} files at once.`,
        );
        return;
    }

    const accepted: ChatAttachment[] = [];
    let rejected = 0;

    for (const file of files) {
        if (accepted.length >= remaining) break;

        if (!ALLOWED_MIMES.includes(file.type)) {
            rejected++;
            continue;
        }

        if (file.size > MAX_ATTACHMENT_BYTES) {
            rejected++;
            continue;
        }

        accepted.push({
            name: file.name,
            size: file.size,
            mime: file.type,
            kind: isImageMime(file.type) ? 'image' : 'document',
            file,
            url: isImageMime(file.type) ? URL.createObjectURL(file) : undefined,
        });
    }

    if (rejected > 0) {
        showAttachmentError(
            'Some files were skipped — images and documents up to 5 MB each.',
        );
    }

    if (accepted.length > 0) {
        attachments.value.push(...accepted);
    }
};

const removeAttachment = (index: number) => {
    const removed = attachments.value[index];
    if (removed?.url && removed?.file) {
        URL.revokeObjectURL(removed.url);
    }
    attachments.value.splice(index, 1);
};

const { open: openFileDialog, onChange: onFilesChanged } = useFileDialog({
    accept: ALLOWED_MIMES.join(','),
    multiple: true,
});

onFilesChanged((files) => addFiles(files));

// Drag & drop state (depth counter handles nested dragenter/leave events)
const onDragEnter = (event: DragEvent) => {
    if (isNewChat.value) return;
    if (!event.dataTransfer?.types.includes('Files')) return;
    event.preventDefault();
    dragDepth.value++;
    isDragging.value = true;
};

const onDragOver = (event: DragEvent) => {
    if (isNewChat.value) return;
    if (!event.dataTransfer?.types.includes('Files')) return;
    event.preventDefault();
    if (event.dataTransfer) event.dataTransfer.dropEffect = 'copy';
};

const onDragLeave = (event: DragEvent) => {
    event.preventDefault();
    dragDepth.value = Math.max(0, dragDepth.value - 1);
    if (dragDepth.value === 0) isDragging.value = false;
};

const onDrop = (event: DragEvent) => {
    // No drag & drop while the welcome view is showing — the footer where
    // attachment chips render is hidden, so dropped files would silently
    // attach to the first message with no visual confirmation.
    if (isNewChat.value) return;
    event.preventDefault();
    dragDepth.value = 0;
    isDragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
};

/* ──────────────── Streaming helpers ──────────────── */

const getXsrfToken = (): string | null => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
};

const normalizeMessages = (incoming: ChatMessage[]): ChatMessage[] => {
    return incoming.map((msg) => ({
        ...msg,
        attachments: Array.isArray(msg.attachments)
            ? msg.attachments.map((att) => ({
                  name: att.name,
                  size: att.size,
                  mime: att.mime,
                  kind: att.kind === 'image' ? 'image' : 'document',
              }))
            : undefined,
    }));
};

const sendMessageNonStreaming = async (userMessage: string) => {
    const messageIndex =
        messages.value.push({ role: 'assistant', content: '', typing: true }) -
        1;

    const controller = new AbortController();
    streamAbortController = controller;

    try {
        const response = await axios.post(
            chatsMessage({ session: activeSession.value!.id }).url,
            {
                message: userMessage,
            },
            { signal: controller.signal },
        );

        const aiResponse = response.data.response as string;
        await typeMessage(aiResponse, messageIndex, controller.signal);

        if (controller.signal.aborted) {
            // User stopped the reply — keep the partial text as-is.
            return;
        }

        const updatedSession = response.data.session as ChatSession;
        if (updatedSession) {
            activeSession.value = { ...updatedSession };
            messages.value = normalizeMessages(updatedSession.messages ?? []);
            updateSessionInList(updatedSession);
        }
    } catch (error) {
        const aborted =
            isAbortError(error) ||
            (error as { code?: string })?.code === 'ERR_CANCELED';

        if (aborted) {
            // User pressed stop — keep whatever typed so far (or drop the
            // placeholder if nothing arrived yet).
            const content = messages.value[messageIndex].content;
            if (!content) {
                messages.value.splice(messageIndex, 1);
            } else {
                messages.value[messageIndex].typing = false;
            }
            return;
        }

        messages.value.splice(messageIndex, 1);
        const err = error as { response?: { data?: { response?: string } } };
        console.error('Chat error:', error);
        const errorMessage =
            err.response?.data?.response ||
            'Sorry, something went wrong. Please try again in a moment.';
        await typeMessage(errorMessage, undefined, controller.signal);
    } finally {
        isLoading.value = false;
        if (streamAbortController === controller) {
            streamAbortController = null;
        }
    }
};

const streamMessage = async (
    userMessage: string,
    sessionIdValue: number,
    userAttachments: ChatAttachment[],
) => {
    // Show Echo's bubble (with typing dots) up front so the reply streams into
    // a single bubble instead of a separate loading indicator in between.
    const assistantIndex =
        messages.value.push({ role: 'assistant', content: '', typing: true }) -
        1;

    const controller = new AbortController();
    streamAbortController = controller;

    try {
        const formData = new FormData();
        formData.append('message', userMessage);
        for (const attachment of userAttachments) {
            if (attachment.file) {
                formData.append(
                    'attachments[]',
                    attachment.file,
                    attachment.name,
                );
            }
        }

        const headers: HeadersInit = {};
        const xsrf = getXsrfToken();
        if (xsrf) headers['X-XSRF-TOKEN'] = xsrf;

        const response = await fetch(
            chatsStream({ session: sessionIdValue }).url,
            {
                method: 'POST',
                headers,
                body: formData,
                credentials: 'same-origin',
                signal: controller.signal,
            },
        );

        if (!response.ok) {
            throw new Error(
                `Stream request failed with status ${response.status}`,
            );
        }

        const contentType = response.headers.get('content-type') ?? '';
        if (!contentType.includes('text/event-stream')) {
            throw new Error('Streaming is not available right now.');
        }

        const reader = response.body?.getReader();
        if (!reader) {
            throw new Error('Streaming is not supported by this browser.');
        }

        const decoder = new TextDecoder();
        let buffer = '';
        let assistantText = '';

        try {
            let streamDone = false;

            while (!streamDone) {
                const { done, value } = await reader.read();
                if (done) break;

                buffer += decoder.decode(value, { stream: true });

                let boundary;
                while ((boundary = buffer.indexOf('\n\n')) !== -1) {
                    const chunk = buffer.slice(0, boundary);
                    buffer = buffer.slice(boundary + 2);

                    const line = chunk
                        .split('\n')
                        .find((l) => l.startsWith('data: '));

                    if (!line) continue;

                    const payload = line.slice(6);
                    if (payload === '[DONE]') {
                        streamDone = true;
                        break;
                    }

                    try {
                        const event = JSON.parse(payload);
                        if (event.type === 'text_delta' && event.delta) {
                            assistantText += event.delta;
                            messages.value[assistantIndex].content =
                                assistantText;
                            scrollToBottom();
                        }
                    } catch {
                        // Ignore malformed frames.
                    }
                }
            }
        } finally {
            reader.releaseLock();
        }

        if (!assistantText) {
            // The stream ended without any content. Instead of silently
            // removing the placeholder (which made Echo's reply appear to
            // never arrive), type a soft error into the same bubble.
            await typeMessage(
                'Sorry, something went wrong. Please try again in a moment.',
                assistantIndex,
            );
        } else {
            messages.value[assistantIndex].typing = false;
        }

        return assistantText;
    } catch (error) {
        if (isAbortError(error)) {
            // User pressed stop — keep whatever streamed so far (or drop the
            // placeholder if nothing arrived yet).
            const content = messages.value[assistantIndex].content;
            if (!content) {
                messages.value.splice(assistantIndex, 1);
            } else {
                messages.value[assistantIndex].typing = false;
            }
            return content;
        }
        // Remove the placeholder bubble so the non-streaming fallback can
        // present the reply cleanly instead of leaving an empty bubble stuck.
        messages.value.splice(assistantIndex, 1);
        throw error;
    } finally {
        if (streamAbortController === controller) {
            streamAbortController = null;
        }
    }
};

const truncateTitle = (text: string, length = 60): string =>
    text.length > length ? `${text.slice(0, length).trimEnd()}…` : text;

const sendMessage = async () => {
    if (!inputMessage.value.trim() || isLoading.value) return;
    // Claim the loading state up front — session creation below is async, and
    // without this a quick double-Enter could create two sessions.
    isLoading.value = true;

    // Sending from the welcome view with no open conversation — create the
    // persisted session first so the first message lands somewhere.
    if (!activeSession.value) {
        try {
            const response = await axios.post(chatsStore().url);
            const created = response.data.session as { id: number };
            activeSession.value = {
                id: created.id,
                title: 'New chat',
                messages: [],
            };
        } catch (error) {
            console.error('Failed to create a new chat:', error);
            isLoading.value = false;
            return;
        }
    }

    const userMessage = inputMessage.value.trim();
    const sessionId = activeSession.value.id;
    const userAttachments = [...attachments.value];

    // Mirror the server's auto-titling (first user message) so the header and
    // sidebar reflect the real title immediately instead of "New chat".
    if (
        !activeSession.value.title ||
        activeSession.value.title === 'New chat'
    ) {
        activeSession.value = {
            ...activeSession.value,
            title: truncateTitle(userMessage),
        };
        updateSessionInList({
            ...activeSession.value,
            messages: [
                ...messages.value,
                { role: 'user', content: userMessage },
            ],
        });
    }

    messages.value.push({
        role: 'user',
        content: userMessage,
        attachments: userAttachments.length > 0 ? userAttachments : undefined,
    });
    inputMessage.value = '';
    attachments.value = [];
    await scrollToBottom();

    try {
        // Try streaming first; fall back to the classic JSON endpoint.
        await streamMessage(userMessage, sessionId, userAttachments);
    } catch (error) {
        console.warn('Streaming failed, falling back to non-streaming:', error);
        await sendMessageNonStreaming(userMessage);
    } finally {
        isLoading.value = false;
        await scrollToBottom();
    }
};

const createNewChat = async () => {
    if (isLoading.value) return;

    try {
        const response = await axios.post(chatsStore().url);
        const sessionId = (response.data.session as { id: number }).id;
        router.visit(chatsShow({ session: sessionId }).url);
    } catch (error) {
        console.error('Failed to create a new chat:', error);
    }
};

const openDeleteModal = (session: ChatSession) => {
    sessionToDelete.value = session;
};

const confirmDelete = async () => {
    if (!sessionToDelete.value) return;

    const target = sessionToDelete.value;
    const wasActive = activeSession.value?.id === target.id;
    sessionToDelete.value = null;

    try {
        await axios.delete(chatsDestroy({ session: target.id }).url);

        sessions.value = sessions.value.filter((s) => s.id !== target.id);

        if (wasActive) {
            router.visit(chatsIndex().url);
        }
    } catch (error) {
        console.error('Failed to delete chat:', error);
    }
};

/* ──────────────── Lifecycle ──────────────── */

onMounted(() => {
    if (messages.value.length === 0) {
        scrollToBottom();
    }
});

onBeforeUnmount(() => {
    if (attachmentErrorTimer) clearTimeout(attachmentErrorTimer);
    attachments.value.forEach((att) => {
        if (att.url) URL.revokeObjectURL(att.url);
    });
});
</script>

<template>
    <Head title="Chats" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div
            class="flex h-[calc(100vh-7rem)] min-h-[480px] gap-3 md:h-[calc(100vh-6rem)]"
        >
            <!-- ─── History Panel ─── -->
            <aside
                :class="[
                    activeSession ? 'hidden md:flex' : 'flex',
                    'w-full flex-col overflow-hidden rounded-xl border border-border/40 bg-card/40 md:w-80 md:shrink-0',
                ]"
            >
                <div
                    class="flex items-center justify-between border-b border-border/40 px-4 py-3"
                >
                    <div class="flex items-center gap-2">
                        <MessageSquare class="h-4 w-4 text-primary" />
                        <h2 class="text-sm font-bold tracking-tight">Chats</h2>
                    </div>
                    <Button
                        variant="outline"
                        size="sm"
                        class="h-8 gap-1.5"
                        @click="createNewChat"
                    >
                        <Plus class="h-3.5 w-3.5" />
                        New chat
                    </Button>
                </div>

                <div class="flex-1 scrollbar-thin overflow-y-auto p-2">
                    <div
                        v-if="sessions.length === 0"
                        class="flex h-full flex-col items-center justify-center gap-2 px-6 text-center"
                    >
                        <div
                            class="flex h-12 w-12 items-center justify-center rounded-2xl bg-primary/10"
                        >
                            <MessageSquare class="h-6 w-6 text-primary" />
                        </div>
                        <p class="text-sm font-semibold text-foreground">
                            No chats yet
                        </p>
                        <p
                            class="text-xs leading-relaxed text-muted-foreground"
                        >
                            Every conversation you have with Echo will be saved
                            here.
                        </p>
                        <Button
                            size="sm"
                            class="mt-2 gap-1.5"
                            @click="createNewChat"
                        >
                            <Plus class="h-3.5 w-3.5" />
                            Start a chat
                        </Button>
                    </div>

                    <Collapsible
                        v-for="group in groupedSessions"
                        v-else
                        :key="group.label"
                        :default-open="group.open"
                        class="mb-1"
                    >
                        <CollapsibleTrigger
                            class="group flex w-full cursor-pointer items-center justify-between rounded-lg px-3 py-1.5 text-left transition-colors hover:bg-muted/60"
                        >
                            <span
                                class="text-[10px] font-bold tracking-[0.14em] text-muted-foreground uppercase"
                            >
                                {{ group.label }}
                            </span>
                            <span
                                v-if="group.sessions.length"
                                class="text-[10px] font-medium text-muted-foreground/70"
                            >
                                {{ group.sessions.length }}
                            </span>
                        </CollapsibleTrigger>

                        <CollapsibleContent>
                            <div class="reka-collapsible-content">
                                <div
                                    v-for="session in group.sessions"
                                    :key="session.id"
                                    class="group/item relative mb-0.5"
                                >
                                    <Link
                                        :href="
                                            chatsShow({ session: session.id })
                                                .url
                                        "
                                        class="flex min-w-0 items-center gap-2 rounded-lg px-3 py-2 transition-colors"
                                        :class="
                                            activeSession?.id === session.id
                                                ? 'bg-primary/10'
                                                : 'hover:bg-muted/60'
                                        "
                                    >
                                        <div class="min-w-0 flex-1">
                                            <p
                                                class="truncate text-[13px] font-medium text-foreground"
                                            >
                                                {{ session.title }}
                                            </p>
                                            <p
                                                class="truncate text-[11px] text-muted-foreground/80"
                                            >
                                                {{ session.updatedAtHuman }}
                                            </p>
                                        </div>
                                        <Badge
                                            variant="secondary"
                                            class="h-4 shrink-0 px-1.5 text-[9px] font-semibold"
                                        >
                                            {{ session.messageCount ?? 0 }}
                                        </Badge>
                                    </Link>
                                    <button
                                        type="button"
                                        title="Delete chat"
                                        class="absolute top-1/2 right-1.5 flex h-6 w-6 -translate-y-1/2 items-center justify-center rounded-md text-muted-foreground/60 transition-all duration-150 hover:bg-rose-500/10 hover:text-rose-500 sm:opacity-0 sm:group-hover/item:opacity-100 sm:focus:opacity-100"
                                        @click.prevent="
                                            openDeleteModal(session)
                                        "
                                    >
                                        <Trash2 class="h-3.5 w-3.5" />
                                    </button>
                                </div>
                            </div>
                        </CollapsibleContent>
                    </Collapsible>
                </div>
            </aside>

            <!-- ─── Chat Pane ─── -->
            <Card
                :class="[
                    !activeSession ? 'hidden md:flex' : 'flex',
                    'relative min-w-0 flex-1 flex-col gap-0 overflow-hidden rounded-xl border-border/40',
                ]"
                @dragenter="onDragEnter"
                @dragover="onDragOver"
                @dragleave="onDragLeave"
                @drop="onDrop"
            >
                <!-- Drop overlay shown while dragging files over the chat -->
                <transition
                    enter-active-class="transition duration-200 ease-out"
                    enter-from-class="opacity-0"
                    enter-to-class="opacity-100"
                    leave-active-class="transition duration-150 ease-in"
                    leave-from-class="opacity-100"
                    leave-to-class="opacity-0"
                >
                    <div
                        v-if="isDragging"
                        class="pointer-events-none absolute inset-0 z-20 flex items-center justify-center border-2 border-dashed border-primary/60 bg-background/80 p-4 backdrop-blur-sm"
                    >
                        <div
                            class="flex flex-col items-center gap-2 rounded-2xl border border-primary/20 bg-card/90 px-6 py-5 text-center shadow-lg"
                        >
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10"
                            >
                                <Paperclip class="h-5 w-5 text-primary" />
                            </div>
                            <p class="text-xs font-semibold text-foreground">
                                Drop files to attach
                            </p>
                            <p class="text-[10px] text-muted-foreground">
                                Images &amp; documents up to 5 MB
                            </p>
                        </div>
                    </div>
                </transition>

                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 border-b border-border/40 py-3 pr-3 pl-4"
                >
                    <div class="flex min-w-0 items-center gap-2.5">
                        <Link
                            v-if="activeSession"
                            :href="chatsIndex().url"
                            class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-muted-foreground transition-colors hover:bg-muted/60 hover:text-foreground md:hidden"
                        >
                            <ArrowLeft class="h-4 w-4" />
                        </Link>
                        <div
                            class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary/10"
                        >
                            <img
                                v-if="branding.logoUrl"
                                :src="branding.logoUrl"
                                alt="Echo"
                                class="h-5 w-5 object-contain"
                            />
                            <AppLogoIcon v-else class="h-4 w-4 text-primary" />
                        </div>
                        <div class="min-w-0">
                            <h1
                                class="truncate text-sm font-bold tracking-tight"
                            >
                                {{ currentTitle }}
                            </h1>
                            <p class="text-[11px] text-muted-foreground">
                                {{ activeSubtitle }}
                            </p>
                        </div>
                    </div>
                </CardHeader>

                <CardContent
                    ref="scrollContainer"
                    class="flex-1 scrollbar-thin space-y-3 overflow-y-auto p-4"
                >
                    <template v-if="isNewChat">
                        <!-- The inner wrapper centers itself with auto margins,
                             so the welcome content can't be clipped at the top
                             on short viewports (unlike justify-center). -->
                        <div
                            class="flex h-full flex-col items-center px-4 py-8 text-center"
                        >
                            <div
                                class="m-auto flex w-full max-w-xl flex-col items-center"
                            >
                                <!-- System logo -->
                                <div
                                    class="welcome-logo mb-6 flex h-20 w-20 items-center justify-center overflow-hidden rounded-3xl bg-primary/10 shadow-lg ring-1 shadow-primary/10 ring-primary/20"
                                >
                                    <img
                                        v-if="branding.logoUrl"
                                        :src="branding.logoUrl"
                                        alt="Echo"
                                        class="h-12 w-12 object-contain"
                                    />
                                    <AppLogoIcon
                                        v-else
                                        class="h-10 w-10 text-primary"
                                    />
                                </div>

                                <!-- Greeting -->
                                <div class="welcome-greeting">
                                    <p
                                        class="text-sm font-medium tracking-wide text-primary"
                                    >
                                        {{ greetingLine }}
                                    </p>
                                    <h2
                                        class="mt-1.5 text-2xl font-bold tracking-tight text-foreground sm:text-3xl"
                                    >
                                        How can I help you today?
                                    </h2>
                                </div>

                                <!-- Centered input -->
                                <form
                                    class="welcome-input mt-8 w-full max-w-xl"
                                    @submit.prevent="sendMessage"
                                >
                                    <div class="group relative">
                                        <Textarea
                                            ref="welcomeInputRef"
                                            v-model="inputMessage"
                                            placeholder="Ask about assignments, exams, or your study progress..."
                                            class="min-h-[64px] resize-none rounded-2xl border-border/40 bg-background/70 py-3.5 pr-14 pl-4 text-[15px] shadow-sm placeholder:text-muted-foreground/50 focus-visible:ring-primary/30"
                                            @keydown.enter.prevent="sendMessage"
                                        />
                                        <Button
                                            type="submit"
                                            size="icon"
                                            class="absolute right-2.5 bottom-2.5 h-9 w-9 rounded-xl shadow-md transition-transform duration-200 group-focus-within:scale-105"
                                            :disabled="
                                                !inputMessage.trim() ||
                                                isLoading
                                            "
                                        >
                                            <Send class="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <p
                                        class="mt-3 text-[11px] text-muted-foreground/60"
                                    >
                                        Echo can make mistakes — double-check
                                        important answers.
                                    </p>
                                </form>

                                <!-- Suggestions -->
                                <div
                                    class="welcome-suggestions mt-6 flex flex-wrap justify-center gap-1.5"
                                >
                                    <button
                                        v-for="(chip, i) in suggestions"
                                        :key="i"
                                        @click="useSuggestion(chip.message)"
                                        class="cursor-pointer rounded-full border border-border/50 bg-muted/40 px-3 py-1.5 text-[11px] font-medium text-muted-foreground transition-all duration-200 hover:border-primary/30 hover:bg-primary/5 hover:text-foreground active:scale-95"
                                    >
                                        {{ chip.label }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    </template>

                    <template v-else>
                        <!-- Keyed by index (not id): chat messages only ever
                             append, and the non-streaming fallback replaces
                             the locally-pushed messages with persisted server
                             messages after each reply. A stable index key keeps
                             the same DOM node, so bubbles don't replay their
                             entrance animation when the server data arrives. -->
                        <div
                            v-for="(msg, index) in messages"
                            :key="index"
                            class="message-enter flex w-full max-w-[88%] gap-2"
                            :class="[
                                msg.role === 'user'
                                    ? 'message-enter-user ml-auto flex-row-reverse'
                                    : 'message-enter-assistant',
                            ]"
                        >
                            <div
                                :class="[
                                    'flex h-7 w-7 shrink-0 items-center justify-center rounded-full shadow-xs',
                                    msg.role === 'user'
                                        ? 'bg-primary text-primary-foreground'
                                        : 'overflow-hidden border border-border/60 bg-muted/80',
                                ]"
                            >
                                <User
                                    v-if="msg.role === 'user'"
                                    class="h-3.5 w-3.5"
                                />
                                <img
                                    v-else-if="branding.logoUrl"
                                    :src="branding.logoUrl"
                                    alt="Echo"
                                    class="h-full w-full object-contain p-1"
                                />
                                <Bot v-else class="h-3.5 w-3.5 text-primary" />
                            </div>
                            <div
                                v-if="msg.role === 'user'"
                                :class="[
                                    'rounded-2xl px-3.5 py-2.5 text-[13px] leading-relaxed shadow-xs',
                                    'rounded-tr-sm bg-primary text-primary-foreground',
                                ]"
                            >
                                <div
                                    v-if="msg.attachments?.length"
                                    class="mb-1.5 flex flex-wrap gap-1"
                                >
                                    <div
                                        v-for="(
                                            att, attIndex
                                        ) in msg.attachments"
                                        :key="attIndex"
                                        class="flex items-center gap-1 rounded-md bg-primary-foreground/10 px-1.5 py-0.5"
                                    >
                                        <img
                                            v-if="
                                                att.kind === 'image' && att.url
                                            "
                                            :src="att.url"
                                            :alt="att.name"
                                            class="h-4 w-4 rounded object-cover"
                                        />
                                        <FileText
                                            v-else-if="att.kind === 'document'"
                                            class="h-3 w-3 shrink-0"
                                        />
                                        <ImageIcon
                                            v-else
                                            class="h-3 w-3 shrink-0"
                                        />
                                        <span
                                            class="max-w-[100px] truncate text-[10px] font-medium"
                                        >
                                            {{ att.name }}
                                        </span>
                                    </div>
                                </div>
                                {{ msg.content }}
                            </div>
                            <!-- Chained to the user-bubble v-if above: user
                                 messages must NEVER fall through into the
                                 assistant branches (a fresh v-if here used to
                                 double every user bubble). -->
                            <div
                                v-else-if="msg.typing && !msg.content"
                                class="rounded-2xl rounded-tl-sm border border-border/40 bg-muted/40 p-3 shadow-xs"
                            >
                                <div class="flex items-center gap-1.5">
                                    <span
                                        class="h-1.5 w-1.5 animate-bounce rounded-full bg-foreground/25"
                                    ></span>
                                    <span
                                        class="h-1.5 w-1.5 animate-bounce rounded-full bg-foreground/25"
                                        style="animation-delay: 150ms"
                                    ></span>
                                    <span
                                        class="h-1.5 w-1.5 animate-bounce rounded-full bg-foreground/25"
                                        style="animation-delay: 300ms"
                                    ></span>
                                </div>
                            </div>
                            <span
                                v-else-if="msg.typing"
                                class="rounded-2xl rounded-tl-sm border border-border/40 bg-muted/40 px-3.5 py-2.5 text-[13px] leading-relaxed whitespace-pre-wrap text-foreground shadow-xs"
                                >{{ msg.content }}</span
                            >
                            <div
                                v-else
                                class="chat-markdown rounded-2xl rounded-tl-sm border border-border/40 bg-muted/40 px-3.5 py-2.5 text-[13px] leading-relaxed text-foreground shadow-xs"
                                v-html="renderMarkdown(msg.content)"
                            ></div>
                        </div>

                        <div
                            v-if="showSuggestions"
                            class="message-enter flex flex-wrap gap-1.5"
                        >
                            <button
                                v-for="(chip, i) in suggestions"
                                :key="i"
                                @click="useSuggestion(chip.message)"
                                class="cursor-pointer rounded-full border border-border/50 bg-muted/40 px-3 py-1.5 text-[11px] font-medium text-muted-foreground transition-all duration-200 hover:border-primary/30 hover:bg-primary/5 hover:text-foreground active:scale-95"
                            >
                                {{ chip.label }}
                            </button>
                        </div>
                    </template>
                </CardContent>

                <CardFooter
                    v-if="!isNewChat"
                    class="border-t border-border/40 bg-muted/20 p-3"
                >
                    <!-- Attachment validation error -->
                    <transition
                        enter-active-class="transition duration-300 ease-out"
                        enter-from-class="translate-y-2 opacity-0"
                        enter-to-class="translate-y-0 opacity-100"
                        leave-active-class="transition duration-200 ease-in"
                        leave-from-class="translate-y-0 opacity-100"
                        leave-to-class="translate-y-2 opacity-0"
                    >
                        <div
                            v-if="attachmentError"
                            class="w-full rounded-lg border border-red-500/20 bg-red-500/10 px-3 py-1.5 text-[11px] text-red-700/80 dark:text-red-300/70"
                        >
                            ⚠ {{ attachmentError }}
                        </div>
                    </transition>

                    <form
                        class="flex w-full flex-col items-stretch gap-1.5"
                        @submit.prevent="sendMessage"
                    >
                        <!-- Attached files -->
                        <div
                            v-if="attachments.length > 0"
                            class="flex flex-wrap gap-1.5"
                        >
                            <div
                                v-for="(att, index) in attachments"
                                :key="att.name + index"
                                class="group flex max-w-[240px] items-center gap-1.5 rounded-lg border border-border/50 bg-background/70 py-1 pr-1 pl-1.5"
                            >
                                <img
                                    v-if="att.kind === 'image' && att.url"
                                    :src="att.url"
                                    :alt="att.name"
                                    class="h-6 w-6 shrink-0 rounded object-cover"
                                />
                                <div
                                    v-else
                                    class="flex h-6 w-6 shrink-0 items-center justify-center rounded bg-primary/10 text-primary"
                                >
                                    <FileText class="h-3.5 w-3.5" />
                                </div>
                                <div class="min-w-0">
                                    <p
                                        class="max-w-[130px] truncate text-[10px] leading-tight font-medium text-foreground"
                                    >
                                        {{ att.name }}
                                    </p>
                                    <p
                                        class="text-[9px] leading-tight text-muted-foreground"
                                    >
                                        {{ formatFileSize(att.size) }}
                                    </p>
                                </div>
                                <button
                                    type="button"
                                    class="flex h-5 w-5 shrink-0 items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-red-500/10 hover:text-red-500"
                                    :aria-label="`Remove ${att.name}`"
                                    @click="removeAttachment(index)"
                                >
                                    <X class="h-3 w-3" />
                                </button>
                            </div>
                        </div>

                        <!-- Textarea row -->
                        <div class="flex w-full items-end gap-2">
                            <Button
                                type="button"
                                variant="ghost"
                                size="icon"
                                class="h-10 w-10 shrink-0 rounded-xl text-muted-foreground hover:bg-muted hover:text-foreground"
                                title="Attach a file"
                                :disabled="isLoading"
                                @click="openFileDialog"
                            >
                                <Paperclip class="h-4 w-4" />
                            </Button>
                            <Textarea
                                v-model="inputMessage"
                                placeholder="Continue the conversation... (drag & drop files)"
                                class="max-h-[120px] min-h-[40px] flex-1 resize-none rounded-xl border-border/40 bg-background/60 px-3.5 py-2.5 text-[13px] placeholder:text-muted-foreground/50 focus-visible:ring-1 focus-visible:ring-primary/30"
                                @keydown.enter.prevent="sendMessage"
                            />
                            <Button
                                v-if="isLoading"
                                type="button"
                                size="icon"
                                class="h-10 w-10 shrink-0 rounded-xl shadow-md"
                                title="Stop generating"
                                aria-label="Stop generating"
                                @click="stopGenerating"
                            >
                                <Square class="h-4 w-4" />
                            </Button>
                            <Button
                                v-else
                                type="submit"
                                size="icon"
                                class="h-10 w-10 shrink-0 rounded-xl shadow-md"
                                :disabled="!inputMessage.trim() || isLoading"
                            >
                                <Send class="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                </CardFooter>
            </Card>
        </div>

        <!-- ─── Delete Confirmation ─── -->
        <ResponsiveModal
            :open="!!sessionToDelete"
            title="Delete chat?"
            description="This conversation and all of its messages will be permanently deleted."
            @close="sessionToDelete = null"
        >
            <div
                class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end"
            >
                <Button
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="sessionToDelete = null"
                >
                    Cancel
                </Button>
                <Button
                    variant="destructive"
                    class="w-full gap-2 sm:w-auto"
                    @click="confirmDelete"
                >
                    <Trash2 class="h-4 w-4" />
                    Delete chat
                </Button>
            </div>
        </ResponsiveModal>
    </AppLayout>
</template>

<style scoped>
.scrollbar-thin {
    scrollbar-width: thin;
    scrollbar-color: var(--color-border) transparent;
}

.scrollbar-thin::-webkit-scrollbar {
    width: 4px;
}

.scrollbar-thin::-webkit-scrollbar-track {
    background: transparent;
}

.scrollbar-thin::-webkit-scrollbar-thumb {
    background-color: var(--color-border);
    border-radius: 999px;
}

.reka-collapsible-content {
    overflow: hidden;
}

.reka-collapsible-content[data-state='open'] {
    animation: slide-down 0.2s ease-out;
}

.reka-collapsible-content[data-state='closed'] {
    animation: slide-up 0.15s ease-out;
}

@keyframes slide-down {
    from {
        height: 0;
        opacity: 0;
    }
    to {
        height: var(--reka-collapsible-content-height);
        opacity: 1;
    }
}

@keyframes slide-up {
    from {
        height: var(--reka-collapsible-content-height);
        opacity: 1;
    }
    to {
        height: 0;
        opacity: 0;
    }
}

/* Entrance animation for chat elements — new message bubbles, the suggestion
   chips and the loading indicator fade in and slide up. Bubbles are
   directional: user messages slide in from the right, assistant replies from
   the left. */
.message-enter {
    animation: message-enter 0.2s cubic-bezier(0.23, 1, 0.32, 1) both;
}

.message-enter-user {
    animation-name: message-enter-user;
}

.message-enter-assistant {
    animation-name: message-enter-assistant;
}

@keyframes message-enter {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes message-enter-user {
    from {
        opacity: 0;
        transform: translate(14px, 6px);
    }
    to {
        opacity: 1;
        transform: translate(0, 0);
    }
}

@keyframes message-enter-assistant {
    from {
        opacity: 0;
        transform: translate(-14px, 6px);
    }
    to {
        opacity: 1;
        transform: translate(0, 0);
    }
}

@keyframes message-fade {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .message-enter {
        animation: message-fade 0.15s ease-out both;
    }
}

/* Staggered entrance for the new-chat welcome view (claude.ai-style).
   Each block fades up in sequence; the logo pops in with a subtle scale. */
.welcome-logo,
.welcome-greeting,
.welcome-input,
.welcome-suggestions {
    animation: welcome-enter 0.3s cubic-bezier(0.23, 1, 0.32, 1) both;
}

.welcome-logo {
    animation-name: welcome-logo-in;
}

.welcome-greeting {
    animation-delay: 70ms;
}

.welcome-input {
    animation-delay: 140ms;
}

.welcome-suggestions {
    animation-delay: 210ms;
}

@keyframes welcome-enter {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

@keyframes welcome-logo-in {
    from {
        opacity: 0;
        transform: translateY(8px) scale(0.92);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes welcome-fade {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@media (prefers-reduced-motion: reduce) {
    .welcome-logo,
    .welcome-greeting,
    .welcome-input,
    .welcome-suggestions {
        animation: welcome-fade 0.2s ease-out both;
    }
}

/* Rendered markdown inside Echo's assistant messages (v-html content needs
   :deep() to be reached by scoped styles). Mirrors the widget's styling. */
.chat-markdown :deep(p) {
    margin: 0.25rem 0;
}

.chat-markdown :deep(p:first-child) {
    margin-top: 0;
}

.chat-markdown :deep(p:last-child) {
    margin-bottom: 0;
}

.chat-markdown :deep(strong) {
    font-weight: 600;
}

.chat-markdown :deep(em) {
    font-style: italic;
}

.chat-markdown :deep(ul),
.chat-markdown :deep(ol) {
    margin: 0.25rem 0;
    padding-left: 1.125rem;
}

.chat-markdown :deep(ul) {
    list-style: disc;
}

.chat-markdown :deep(ol) {
    list-style: decimal;
}

.chat-markdown :deep(li) {
    margin: 0.125rem 0;
}

.chat-markdown :deep(h1),
.chat-markdown :deep(h2),
.chat-markdown :deep(h3),
.chat-markdown :deep(h4) {
    margin: 0.375rem 0 0.125rem;
    font-weight: 600;
}

.chat-markdown :deep(h1) {
    font-size: 0.95rem;
}

.chat-markdown :deep(h2) {
    font-size: 0.875rem;
}

.chat-markdown :deep(h3),
.chat-markdown :deep(h4) {
    font-size: 0.8125rem;
}

.chat-markdown :deep(code) {
    border-radius: 0.25rem;
    background: color-mix(
        in srgb,
        var(--color-muted-foreground) 12%,
        transparent
    );
    padding: 0 0.25rem;
    font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
    font-size: 0.6875rem;
}

.chat-markdown :deep(pre) {
    margin: 0.375rem 0;
    overflow-x: auto;
    border-radius: 0.5rem;
    background: color-mix(
        in srgb,
        var(--color-muted-foreground) 10%,
        transparent
    );
    padding: 0.5rem 0.625rem;
}

.chat-markdown :deep(pre code) {
    background: transparent;
    padding: 0;
}

.chat-markdown :deep(blockquote) {
    margin: 0.375rem 0;
    border-left: 2px solid var(--color-border);
    padding-left: 0.5rem;
    opacity: 0.85;
}

.chat-markdown :deep(a) {
    color: var(--color-primary);
    text-decoration: underline;
}

.chat-markdown :deep(table) {
    margin: 0.375rem 0;
    width: 100%;
    border-collapse: collapse;
}

.chat-markdown :deep(th),
.chat-markdown :deep(td) {
    border: 1px solid var(--color-border);
    padding: 0.125rem 0.375rem;
    text-align: left;
}

.chat-markdown :deep(hr) {
    margin: 0.5rem 0;
    border-color: var(--color-border);
}
</style>
