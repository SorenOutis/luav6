<script setup lang="ts">
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import axios from 'axios';
import {
    ArrowLeft,
    Bot,
    MessageSquare,
    Plus,
    Send,
    Trash2,
    User,
} from 'lucide-vue-next';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
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
import AppLayout from '@/layouts/AppLayout.vue';
import { renderMarkdown } from '@/lib/markdown';
import { dashboard } from '@/routes';
import {
    destroy as chatsDestroy,
    index as chatsIndex,
    message as chatsMessage,
    show as chatsShow,
    store as chatsStore,
} from '@/routes/chats';
import type { BreadcrumbItem } from '@/types';

interface ChatMessage {
    id?: number;
    role: 'user' | 'assistant';
    content: string;
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'Chats', href: chatsIndex().url },
];

const branding = computed<{ logoUrl?: string | null; name?: string }>(
    () => (page.props.schoolBranding ?? {}) as { logoUrl?: string | null; name?: string },
);

const suggestions = computed<{ label: string; message: string }[]>(() => {
    const fromProps = (page.props.aiChat as { suggestions?: { label: string; message: string }[] })
        ?.suggestions;

    return fromProps?.length
        ? fromProps
        : [
              { label: '📋 My Assignments', message: 'What are my upcoming assignments?' },
              { label: '📊 My Progress', message: 'Show me my learning progress' },
              { label: '🏆 My Streak', message: "What's my current streak?" },
              { label: '📝 Upcoming Exams', message: 'What exams do I have coming up?' },
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
const scrollContainer = ref<HTMLElement | null>(null);

const isAdmin = computed(() => Boolean((page.props.aiChat as { isAdmin?: boolean })?.isAdmin));

interface DateGroup {
    label: string;
    open: boolean;
    sessions: ChatSession[];
}

const groupLabel = (iso?: string | null): string => {
    if (!iso) return 'Earlier';

    const date = new Date(iso);
    const now = new Date();
    const startOfToday = new Date(now.getFullYear(), now.getMonth(), now.getDate());
    const startOfDate = new Date(date.getFullYear(), date.getMonth(), date.getDate());
    const diffDays = Math.round((startOfToday.getTime() - startOfDate.getTime()) / 86_400_000);

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
        sessions: sessions.value.filter((s) => groupLabel(s.updatedAt) === label),
    }));
});

const showSuggestions = computed(() => {
    if (messages.value.length === 0) return true;
    return !messages.value.some((m) => m.role === 'user');
});

const currentTitle = computed(
    () => activeSession.value?.title || 'New chat',
);

/* ──────────────── Chat actions ──────────────── */

const scrollToBottom = async () => {
    await nextTick();
    const container = scrollContainer.value as (HTMLElement & { $el?: HTMLElement }) | null;
    const el = container?.$el || container;
    if (el) {
        el.scrollTop = el.scrollHeight;
    }
};

watch(messages, () => scrollToBottom(), { deep: true });

const typeMessage = async (fullText: string) => {
    messages.value.push({ role: 'assistant', content: '' });

    const index = messages.value.length - 1;
    let currentText = '';
    const speed = 8;

    for (let i = 0; i < fullText.length; i++) {
        currentText += fullText[i];
        messages.value[index].content = currentText;
        await new Promise((resolve) => setTimeout(resolve, speed));
        scrollToBottom();
    }
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

const sendMessage = async () => {
    if (!inputMessage.value.trim() || isLoading.value) return;
    if (!activeSession.value) return;

    const userMessage = inputMessage.value.trim();
    const sessionId = activeSession.value.id;

    messages.value.push({ role: 'user', content: userMessage });
    inputMessage.value = '';
    isLoading.value = true;
    await scrollToBottom();

    try {
        const response = await axios.post(chatsMessage({ session: sessionId }).url, {
            message: userMessage,
        });

        isLoading.value = false;

        const aiResponse = response.data.response as string;
        await typeMessage(aiResponse);

        const updatedSession = response.data.session as ChatSession;
        if (updatedSession) {
            activeSession.value = { ...updatedSession };
            messages.value = updatedSession.messages ?? messages.value;
            updateSessionInList(updatedSession);
        }
    } catch (error) {
        isLoading.value = false;
        const err = error as { response?: { data?: { response?: string } } };
        console.error('Chat error:', error);
        const errorMessage =
            err.response?.data?.response ||
            'Sorry, something went wrong. Please try again in a moment.';
        await typeMessage(errorMessage);
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

                <div class="scrollbar-thin flex-1 overflow-y-auto p-2">
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
                        <p class="text-xs leading-relaxed text-muted-foreground">
                            Every conversation you have with Echo from the chat
                            widget will be saved here.
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
                                        :href="chatsShow({ session: session.id }).url"
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
                                        @click.prevent="openDeleteModal(session)"
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
            >
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 border-b border-border/40 py-3 pl-4 pr-3"
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
                            <p
                                v-if="activeSession"
                                class="text-[11px] text-muted-foreground"
                            >
                                {{
                                    isAdmin
                                        ? 'Teacher mode — workspace tools enabled'
                                        : 'Continued conversation with Echo'
                                }}
                            </p>
                            <p
                                v-else
                                class="text-[11px] text-muted-foreground"
                            >
                                Pick a conversation from your history
                            </p>
                        </div>
                    </div>
                </CardHeader>

                <CardContent
                    ref="scrollContainer"
                    class="scrollbar-thin flex-1 space-y-3 overflow-y-auto p-4"
                >
                    <div
                        v-if="!activeSession"
                        class="flex h-full flex-col items-center justify-center gap-2 text-center"
                    >
                        <div
                            class="flex h-14 w-14 items-center justify-center rounded-2xl bg-primary/10"
                        >
                            <MessageSquare class="h-7 w-7 text-primary" />
                        </div>
                        <p class="text-sm font-semibold text-foreground">
                            Select a chat to continue
                        </p>
                        <p class="max-w-xs text-xs text-muted-foreground">
                            Your saved conversations with Echo live in the
                            sidebar — pick one to pick up right where you left
                            off.
                        </p>
                    </div>

                    <template v-if="activeSession">
                        <div
                            v-for="(msg, index) in messages"
                            :key="msg.id ?? index"
                            class="animate-fade-in flex w-full max-w-[88%] gap-2"
                            :class="[
                                msg.role === 'user' ? 'ml-auto flex-row-reverse' : '',
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
                                <User v-if="msg.role === 'user'" class="h-3.5 w-3.5" />
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
                                {{ msg.content }}
                            </div>
                            <div
                                v-else
                                class="chat-markdown rounded-2xl rounded-tl-sm border border-border/40 bg-muted/40 px-3.5 py-2.5 text-[13px] leading-relaxed text-foreground shadow-xs"
                                v-html="renderMarkdown(msg.content)"
                            ></div>
                        </div>

                        <div
                            v-if="showSuggestions"
                            class="animate-fade-in flex flex-wrap gap-1.5"
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

                        <div
                            v-if="isLoading"
                            class="animate-fade-in flex max-w-[88%] gap-2"
                        >
                            <div
                                class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/60 bg-muted/80"
                            >
                                <img
                                    v-if="branding.logoUrl"
                                    :src="branding.logoUrl"
                                    alt="Echo"
                                    class="h-full w-full object-contain p-1"
                                />
                                <Bot v-else class="h-3.5 w-3.5 text-primary" />
                            </div>
                            <div
                                class="rounded-2xl rounded-tl-sm border border-border/40 bg-muted/40 p-3"
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
                        </div>
                    </template>
                </CardContent>

                <CardFooter class="border-t border-border/40 bg-muted/20 p-3">
                    <form
                        v-if="activeSession"
                        class="flex w-full items-end gap-2"
                        @submit.prevent="sendMessage"
                    >
                        <Textarea
                            v-model="inputMessage"
                            placeholder="Continue the conversation..."
                            class="max-h-[120px] min-h-[40px] resize-none rounded-xl border-border/40 bg-background/60 px-3.5 py-2.5 text-[13px] placeholder:text-muted-foreground/50 focus-visible:ring-1 focus-visible:ring-primary/30"
                            @keydown.enter.prevent="sendMessage"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            class="h-10 w-10 shrink-0 rounded-xl shadow-md"
                            :disabled="!inputMessage.trim() || isLoading"
                        >
                            <Send class="h-4 w-4" />
                        </Button>
                    </form>
                    <p
                        v-else
                        class="w-full py-1 text-center text-[11px] italic text-muted-foreground/70"
                    >
                        Select a conversation to continue chatting
                    </p>
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
            <div class="flex w-full flex-col-reverse gap-2 sm:flex-row sm:justify-end">
                <Button
                    variant="outline"
                    class="w-full sm:w-auto"
                    @click="sessionToDelete = null"
                >
                    Cancel
                </Button>
                <Button
                    variant="destructive"
                    class="gap-2 w-full sm:w-auto"
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

@keyframes fade-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fade-in {
    animation: fade-in 0.25s ease-out both;
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