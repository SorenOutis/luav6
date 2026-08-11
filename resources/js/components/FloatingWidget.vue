<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import { useFileDialog } from '@vueuse/core';
import axios from 'axios';
import {
    MessageCircle,
    Send,
    Square,
    X,
    Bot,
    User,
    RotateCcw,
    Paperclip,
    FileText,
    Image as ImageIcon,
} from 'lucide-vue-next';
import {
    ref,
    computed,
    nextTick,
    watch,
    onMounted,
    onBeforeUnmount,
} from 'vue';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { renderMarkdown } from '@/lib/markdown';

const page = usePage();

interface SchoolBranding {
    name?: string;
    logoUrl?: string | null;
}

const branding = computed<SchoolBranding>(
    () => (page.props.schoolBranding ?? {}) as SchoolBranding,
);
const logoUrl = computed(() => branding.value.logoUrl || null);

const isOpen = ref(false);
const inputMessage = ref('');
const currentSessionId = ref<string | number | null>(null);

interface ChatAttachment {
    name: string;
    size: number;
    mime: string;
    kind: 'image' | 'document';
    // Local file object before it's sent; absent when loaded from history.
    file?: File;
    url?: string;
}

interface ChatMessage {
    role: 'user' | 'assistant';
    content: string;
    typing?: boolean;
    attachments?: ChatAttachment[];
}

const messages = ref<ChatMessage[]>([]);
const isLoading = ref(false);
const showBlockedWarning = ref(false);

// Abort controller for the in-flight reply, so the user can stop generation.
let streamAbortController: AbortController | null = null;

const isAbortError = (error: unknown): boolean =>
    error instanceof DOMException && error.name === 'AbortError';

const stopGenerating = () => {
    streamAbortController?.abort();
    streamAbortController = null;
    isLoading.value = false;
};
const shaking = ref(false);

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

let blockedWarningTimer: ReturnType<typeof setTimeout> | null = null;
let shakeTimer: ReturnType<typeof setTimeout> | null = null;
const scrollContainer = ref<HTMLElement | null>(null);
const textareaRef = ref<any>(null);

interface Suggestion {
    label: string;
    message: string;
}

interface AiChatProps {
    enabled?: boolean;
    maintenanceMessage?: string;
    isAdmin?: boolean;
    suggestions?: Suggestion[];
}

const aiChat = computed<AiChatProps>(
    () => (page.props.aiChat ?? {}) as AiChatProps,
);
const isAdmin = computed(() => Boolean(aiChat.value.isAdmin));

const isVisible = computed(() => {
    const component = page.component;
    return component === 'Dashboard' || component === 'Assignments';
});

const isEnabled = computed(() => aiChat.value.enabled);
const maintenanceMessage = computed(() => aiChat.value.maintenanceMessage);

const showSuggestions = computed(() => {
    // Only show suggestions when the only messages are the welcome message
    if (messages.value.length === 0) return false;
    const userMessages = messages.value.filter((m) => m.role === 'user');
    return userMessages.length === 0;
});

const suggestions = computed<Suggestion[]>(() => {
    return aiChat.value.suggestions?.length
        ? aiChat.value.suggestions
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

/* ──────────────── Client-side guardrail ──────────────── */
interface GuardrailPattern {
    patterns: RegExp[];
    response: string;
}

const guardrails: GuardrailPattern[] = [
    {
        // Personal / non-educational advice
        patterns: [
            /\b(relationship|dating|girlfriend|boyfriend|love|crush)\b/i,
            /\b(invest|stock market|crypto|trading|buy stock)\b/i,
            /\b(medical|diagnosis|symptom|prescription|doctor|hospital|treatment)\b/i,
            /\b(legal|lawyer|sue|attorney|lawsuit)\b/i,
        ],
        response:
            "I'm sorry, but I'm here to help with your learning journey on LSI. I can't provide personal advice on that topic. Is there something school-related I can help you with?",
    },
    {
        // Entertainment / pop culture
        patterns: [
            /\b(movie|movies|film|celebrity|actor|actress|netflix|youtuber|influencer)\b/i,
            /\b(video game|gaming|playstation|xbox|nintendo|valorant|cod|ml|mobile legends|roblox)\b/i,
            /\b(music|song|singer|album|rap|pop|rock|spotify)\b/i,
            /\b(sports team|nba|nfl|uefa|champions league|super bowl|world cup)\b.*(?:score|game|match|winner|champion)/i,
        ],
        response:
            "I'm sorry, but I'm here to help with your learning journey on LSI. I can assist you with your exams, assignments, progress, and other academic needs. Is there something school-related I can help you with?",
    },
    {
        // Politics / religion / controversial
        patterns: [
            /\b(politician|president|election|vote|democrat|republican|government|senator|congress)\b/i,
            /\b(religion|god|bible|quran|church|mosque|pray|prayer|atheist)\b/i,
            /\b(abortion|gun rights|climate change|controversial|offensive)\b/i,
        ],
        response:
            "I'm sorry, but I'm here to help with your learning journey on LSI. I can assist you with your exams, assignments, progress, and other academic needs. Is there something school-related I can help you with?",
    },
    {
        // Homework cheating (doing work FOR the student)
        patterns: [
            /\b(do my homework|do my assignment|write my essay|complete my|answer my|give me the answer|cheat|plagiarize)/i,
            /\b(write (a|an|the|my) (essay|paper|report|story|poem|code|program|script))\b/i,
            /\b(solve this|solve for|calculate this|do this math|write code|generate.*essay)/i,
        ],
        response:
            "I can help guide you and explain concepts, but I can't do your assignments for you. Let me know what topic you're studying and I'll help you understand it better!",
    },
];

/**
 * Educational context keywords — if any of these appear in the message,
 * the guardrail allows it through since it's likely a legitimate academic
 * question that happens to mention a blocked keyword.
 *
 * E.g. "Can you explain the stock market for my economics class?" has "class"
 * and "economics" so it passes, but "What should I invest in?" does not.
 */
const educationalKeywords =
    /\b(assignment|exam|course|lesson|study|learn|class|homework|grade|teacher|professor|school|university|subject|topic|chapter|review|practice|quiz|test|project|research|paper|essay|report|reading|lecture|tutor|academic|science|math|history|literature|english|filipino|physics|chemistry|biology|geography|economics|psychology|philosophy|art|music|drama|exercise|problem|solve|explain|understand|help|question|answer|feedback|score|level|x[pP]|streak|badge|achiev|progress|module|unit|curriculum|syllabus|lesson|discuss|analyze|analysis|evaluate|critique|summarize|define|describe|compare|contrast|outline|diagram|illustrate|interpret|justify|argument|thesis|concept|theory|principle|formula|equation|experiment|lab|observation|data|evidence|source|citation|reference|bibliography|vocabulary|grammar|sentence|paragraph|comprehension|essay|writing|prompt|rubric|score)\b/i;

/**
 * Harassment/toxicity patterns — these are ALWAYS blocked regardless of context.
 */
const toxicityPatterns = [
    // Swear words and abbreviations (word-boundary)
    /\b(fuck|fck|fkn|wtf|wth|stfu|shit|bullshit|shitty|ass|asshole|bitch|bastard|damn|goddamn|hell|crap|pissed|dick|dickhead|prick|cunt|whore|slut|hoe|motherfucker|mofo|douche|douchebag|jackass|arse|bloody)\b/i,
    // Sloppy match — catches fuck/fck anywhere (inside compound words like "fucking", "motherfcker")
    // These substrings are unambiguously profanity and never appear in legitimate academic English.
    /(fuck|fck)/i,
    // Insults
    /\b(stupid|dumb|idiot|moron|retard|useless|trash|suck|kys|kill yourself|shut up|annoying|loser)\b/i,
    // Harassment / toxicity
    /\b(bully|harass|threat|hate speech|racist|sexist|creep|weirdo)\b/i,
];

/**
 * Normalize a message to catch creative spellings and leetspeak.
 * Replaces common character substitutions before the guardrail checks.
 * E.g. "sh1t" → "shit", "b@stard" → "bastard"
 */
/**
 * Normalize a message to catch creative spellings and leetspeak.
 * Replaces common character substitutions before the guardrail checks.
 * E.g. "sh1t" → "shit", "b@stard" → "bastard"
 *
 * NOTE: Underscores are NOT removed because `_` is a word character in
 * JavaScript regex (`\w` includes `_`), so removing them doesn't help
 * with word-boundary matching.
 */
const normalizeMessage = (message: string): string => {
    return (
        message
            // Leetspeak character substitutions
            .replace(/0/g, 'o')
            .replace(/1/g, 'i')
            .replace(/3/g, 'e')
            .replace(/4/g, 'a')
            .replace(/5/g, 's')
            .replace(/7/g, 't')
            .replace(/8/g, 'b')
            .replace(/@/g, 'a')
            .replace(/\$/g, 's')
            .replace(/\!/g, 'i')
            .replace(/\|/g, 'i')
    );
};

/**
 * Check if a message is blocked by the client-side guardrail.
 * Returns the guardrail response if blocked, or null if allowed.
 *
 * Messages with educational context keywords are always allowed through
 * (the server-side AI handles those). Only obviously off-topic or
 * toxic messages are blocked here.
 */
const checkGuardrail = (message: string): string | null => {
    // Normalize leetspeak/creative spellings before checking
    const normalized = normalizeMessage(message);

    // Always block toxicity/harassment first
    for (const pattern of toxicityPatterns) {
        if (pattern.test(message) || pattern.test(normalized)) {
            return "I'm here to help you learn, but I need our conversation to stay respectful. Let's focus on your studies — how can I assist you with your courses or assignments?";
        }
    }

    // If the message has educational context, let it through to the AI
    if (educationalKeywords.test(message)) {
        return null;
    }

    // Check topical guardrails for non-educational messages
    for (const guardrail of guardrails) {
        for (const pattern of guardrail.patterns) {
            if (pattern.test(message)) {
                return guardrail.response;
            }
        }
    }

    return null;
};

const useSuggestion = (suggestion: string) => {
    inputMessage.value = suggestion;
    sendMessage();
};

const scrollToBottom = async () => {
    await nextTick();
    const container =
        (scrollContainer.value as (HTMLElement & { $el?: HTMLElement }) | null)
            ?.$el || scrollContainer.value;
    if (container) {
        container.scrollTop = container.scrollHeight;
    }
};

// Watch for message changes to scroll
watch(
    messages,
    () => {
        scrollToBottom();
    },
    { deep: true },
);

const focusTextarea = async () => {
    await nextTick();
    const el = textareaRef.value?.$el || textareaRef.value;
    const textarea =
        el instanceof HTMLTextAreaElement ? el : el?.querySelector('textarea');
    if (textarea) {
        textarea.focus();
    }
};

const normalizeHistoryMessages = (history: any[]): ChatMessage[] => {
    return history.map((msg) => ({
        role: msg.role,
        content: msg.content,
        attachments: Array.isArray(msg.attachments)
            ? msg.attachments.map((att: any) => ({
                  name: att.name,
                  size: att.size,
                  mime: att.mime,
                  kind: att.kind === 'image' ? 'image' : 'document',
              }))
            : undefined,
    }));
};

const fetchHistory = async () => {
    try {
        const response = await axios.get('/api/chat/history');
        if (response.data.session_id) {
            currentSessionId.value = response.data.session_id;
        }
        if (response.data.history && response.data.history.length > 0) {
            messages.value = normalizeHistoryMessages(response.data.history);
        } else {
            messages.value = [
                {
                    role: 'assistant',
                    content: "Hello! I'm Echo. How can I assist you today?",
                },
            ];
        }
        await scrollToBottom();
    } catch (error) {
        console.error('Failed to fetch chat history:', error);
        messages.value = [
            {
                role: 'assistant',
                content: "Hello! I'm Echo. How can I assist you today?",
            },
        ];
    }
};

onMounted(() => {
    fetchHistory();
});

onBeforeUnmount(() => {
    if (blockedWarningTimer) clearTimeout(blockedWarningTimer);
    if (shakeTimer) clearTimeout(shakeTimer);
    if (attachmentErrorTimer) clearTimeout(attachmentErrorTimer);
    attachments.value.forEach((att) => {
        if (att.url) URL.revokeObjectURL(att.url);
    });
});

const toggleChat = () => {
    isOpen.value = !isOpen.value;
};

const clearChat = async () => {
    try {
        await axios.post('/api/chat/clear');
    } catch (error) {
        console.error('Failed to clear chat:', error);
    }

    currentSessionId.value = null;
    messages.value = [
        {
            role: 'assistant',
            content: "Hello! I'm Echo. How can I assist you today?",
        },
    ];
    await scrollToBottom();
    focusTextarea();
};

const handleAfterEnter = () => {
    scrollToBottom();
    focusTextarea();
};

const typeMessage = async (
    fullText: string,
    index?: number,
    signal?: AbortSignal,
) => {
    const messageIndex =
        index ??
        messages.value.push({
            role: 'assistant',
            content: '',
            typing: true,
        }) - 1;

    let currentText = '';
    const speed = 15; // ms per character

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
    if (!event.dataTransfer?.types.includes('Files')) return;
    event.preventDefault();
    dragDepth.value++;
    isDragging.value = true;
};

const onDragOver = (event: DragEvent) => {
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
    event.preventDefault();
    dragDepth.value = 0;
    isDragging.value = false;
    addFiles(event.dataTransfer?.files ?? null);
};

/* ──────────────── Streaming helpers ──────────────── */

// Read the XSRF-TOKEN cookie (same mechanism axios uses) so fetch can satisfy
// Laravel's CSRF middleware on the streaming route.
const getXsrfToken = (): string | null => {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
};

const sendMessageNonStreaming = async (userMessage: string) => {
    const messageIndex =
        messages.value.push({ role: 'assistant', content: '', typing: true }) -
        1;

    const controller = new AbortController();
    streamAbortController = controller;

    try {
        const response = await axios.post(
            '/api/chat',
            {
                message: userMessage,
                session_id: currentSessionId.value,
            },
            { signal: controller.signal },
        );

        if (response.data.session_id) {
            currentSessionId.value = response.data.session_id;
        }

        const aiResponse = response.data.response;
        await typeMessage(aiResponse, messageIndex, controller.signal);

        if (controller.signal.aborted) {
            // User stopped the reply — keep the partial text as-is.
            return;
        }

        messages.value = response.data.history;
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
        if (currentSessionId.value) {
            formData.append('session_id', String(currentSessionId.value));
        }
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

        const response = await fetch('/api/chat/stream', {
            method: 'POST',
            headers,
            body: formData,
            credentials: 'same-origin',
            signal: controller.signal,
        });

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

const sendMessage = async () => {
    if (!inputMessage.value.trim() || isLoading.value) return;

    const userMessage = inputMessage.value.trim();
    const userAttachments = [...attachments.value];

    // ── Client-side guardrail check ──
    const blockedResponse = checkGuardrail(userMessage);
    if (blockedResponse) {
        messages.value.push({
            role: 'user',
            content: userMessage,
            attachments:
                userAttachments.length > 0 ? userAttachments : undefined,
        });
        inputMessage.value = '';
        attachments.value = [];
        // Show the blocked warning indicator and shake input
        showBlockedWarning.value = true;
        if (blockedWarningTimer) clearTimeout(blockedWarningTimer);
        blockedWarningTimer = setTimeout(() => {
            showBlockedWarning.value = false;
        }, 3000);

        shaking.value = true;
        if (shakeTimer) clearTimeout(shakeTimer);
        shakeTimer = setTimeout(() => {
            shaking.value = false;
        }, 500);
        await scrollToBottom();
        await typeMessage(blockedResponse);
        return;
    }

    messages.value.push({
        role: 'user',
        content: userMessage,
        attachments: userAttachments.length > 0 ? userAttachments : undefined,
    });
    inputMessage.value = '';
    attachments.value = [];
    isLoading.value = true;

    await scrollToBottom();

    try {
        // Try streaming first; fall back to the classic JSON endpoint.
        await streamMessage(userMessage, userAttachments);
    } catch (error) {
        console.warn('Streaming failed, falling back to non-streaming:', error);
        await sendMessageNonStreaming(userMessage);
    } finally {
        isLoading.value = false;
        await scrollToBottom();
        focusTextarea();
    }
};

// Auto-expand textarea logic
watch(inputMessage, () => {
    if (textareaRef.value?.$el) {
        const el = textareaRef.value.$el;
        el.style.height = 'auto';
        el.style.height = `${Math.min(el.scrollHeight, 150)}px`;
    }
});
</script>

<template>
    <div
        v-if="isVisible"
        class="fixed right-5 bottom-20 z-[60] flex flex-col items-end gap-3 md:bottom-5"
    >
        <!-- Chat Window -->
        <transition
            enter-active-class="transition duration-300 ease-out"
            enter-from-class="translate-y-4 opacity-0 scale-95"
            enter-to-class="translate-y-0 opacity-100 scale-100"
            leave-active-class="transition duration-200 ease-in"
            leave-from-class="translate-y-0 opacity-100 scale-100"
            leave-to-class="translate-y-4 opacity-0 scale-95"
            @after-enter="handleAfterEnter"
        >
            <Card
                v-if="isOpen"
                class="relative flex h-[480px] w-[360px] flex-col gap-0 overflow-hidden rounded-xl border-border/40 bg-card/90 p-0 shadow-2xl shadow-black/5 backdrop-blur-xl sm:w-[400px]"
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

                <!-- Compact Header with rounded top corners -->
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 rounded-t-xl border-b border-border/40 bg-gradient-to-r from-primary/95 to-primary/90 px-3 py-2.5"
                >
                    <div class="flex min-w-0 items-center gap-2">
                        <div
                            class="flex h-6 w-6 shrink-0 items-center justify-center overflow-hidden rounded-full bg-primary-foreground/10"
                        >
                            <img
                                v-if="logoUrl"
                                :src="logoUrl"
                                alt="LSI"
                                class="h-5 w-5 object-contain"
                            />
                            <AppLogoIcon
                                v-else
                                class="h-4 w-4 text-primary-foreground"
                            />
                        </div>
                        <div class="min-w-0">
                            <CardTitle
                                class="truncate text-xs leading-tight font-semibold text-primary-foreground"
                            >
                                Echo — LSI Assistant
                            </CardTitle>
                            <p
                                class="text-[10px] leading-tight text-primary-foreground/60"
                            >
                                {{
                                    isAdmin
                                        ? 'Teacher mode — workspace tools enabled'
                                        : 'Your intelligent companion'
                                }}
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            title="Start a new chat"
                            class="h-6 w-6 text-primary-foreground/70 hover:bg-primary-foreground/10 hover:text-primary-foreground"
                            @click="clearChat"
                        >
                            <RotateCcw class="h-3.5 w-3.5" />
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            class="h-6 w-6 text-primary-foreground/70 hover:bg-primary-foreground/10 hover:text-primary-foreground"
                            @click="toggleChat"
                        >
                            <X class="h-3.5 w-3.5" />
                        </Button>
                    </div>
                </CardHeader>

                <!-- Messages -->
                <CardContent
                    ref="scrollContainer"
                    class="flex-1 scrollbar-thin space-y-3 overflow-y-auto scroll-smooth p-3"
                >
                    <template v-for="(msg, index) in messages" :key="index">
                        <div
                            :class="[
                                'flex w-full max-w-[88%] gap-2',
                                msg.role === 'user'
                                    ? 'ml-auto flex-row-reverse'
                                    : '',
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
                                    v-else-if="logoUrl"
                                    :src="logoUrl"
                                    alt="Echo"
                                    class="h-full w-full object-contain p-1"
                                />
                                <Bot v-else class="h-3.5 w-3.5 text-primary" />
                            </div>
                            <div
                                :class="[
                                    'rounded-2xl px-3 py-2 text-xs leading-relaxed shadow-xs',
                                    msg.role === 'user'
                                        ? 'rounded-tr-sm bg-primary text-primary-foreground'
                                        : 'rounded-tl-sm border border-border/40 bg-muted/40 text-foreground',
                                ]"
                            >
                                <template v-if="msg.role === 'user'">
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
                                                    att.kind === 'image' &&
                                                    att.url
                                                "
                                                :src="att.url"
                                                :alt="att.name"
                                                class="h-4 w-4 rounded object-cover"
                                            />
                                            <FileText
                                                v-else-if="
                                                    att.kind === 'document'
                                                "
                                                class="h-3 w-3 shrink-0"
                                            />
                                            <ImageIcon
                                                v-else
                                                class="h-3 w-3 shrink-0"
                                            />
                                            <span
                                                class="max-w-[90px] truncate text-[10px] font-medium"
                                            >
                                                {{ att.name }}
                                            </span>
                                        </div>
                                    </div>
                                    {{ msg.content }}
                                </template>
                                <!-- While the reply is pending, show typing
                                dots; raw text while it streams in; snap to
                                rendered markdown when done. Chained to the
                                user template above so user messages don't
                                also render the markdown copy. -->
                                <div
                                    v-else-if="msg.typing && !msg.content"
                                    class="flex items-center gap-1.5 py-0.5"
                                >
                                    <span
                                        class="h-1.5 w-1.5 animate-bounce rounded-full bg-foreground/25"
                                        style="animation-delay: 0ms"
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
                                <span
                                    v-else-if="msg.typing"
                                    class="whitespace-pre-wrap"
                                    >{{ msg.content }}</span
                                >
                                <div
                                    v-else
                                    class="chat-markdown"
                                    v-html="renderMarkdown(msg.content)"
                                ></div>
                            </div>
                        </div>
                    </template>

                    <!-- Suggestion chips -->
                    <div
                        v-if="showSuggestions"
                        class="animate-fade-in flex flex-wrap gap-1.5 px-1"
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
                </CardContent>

                <!-- Blocked Warning -->
                <transition
                    enter-active-class="transition duration-300 ease-out"
                    enter-from-class="translate-y-2 opacity-0"
                    enter-to-class="translate-y-0 opacity-100"
                    leave-active-class="transition duration-200 ease-in"
                    leave-from-class="translate-y-0 opacity-100"
                    leave-to-class="translate-y-2 opacity-0"
                >
                    <div
                        v-if="showBlockedWarning"
                        class="flex items-center gap-1.5 border-b border-border/40 bg-amber-500/10 px-3 py-1.5"
                    >
                        <span
                            class="text-[10px] font-medium text-amber-600 dark:text-amber-400"
                            >⚠</span
                        >
                        <span
                            class="text-[10px] text-amber-700/80 dark:text-amber-300/70"
                        >
                            Message flagged — please keep the conversation
                            respectful and study-focused
                        </span>
                    </div>
                </transition>

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
                        class="flex items-center gap-1.5 border-b border-border/40 bg-red-500/10 px-3 py-1.5"
                    >
                        <span
                            class="text-[10px] font-medium text-red-600 dark:text-red-400"
                            >⚠</span
                        >
                        <span
                            class="text-[10px] text-red-700/80 dark:text-red-300/70"
                        >
                            {{ attachmentError }}
                        </span>
                    </div>
                </transition>

                <!-- Input Footer -->
                <CardFooter
                    class="border-t border-border/40 bg-muted/20 p-3 pt-2.5"
                >
                    <form
                        v-if="isEnabled"
                        @submit.prevent="sendMessage"
                        :class="[
                            'flex w-full flex-col items-stretch gap-1.5',
                            shaking ? 'animate-shake' : '',
                        ]"
                    >
                        <!-- Attached files -->
                        <div
                            v-if="attachments.length > 0"
                            class="flex flex-wrap gap-1.5"
                        >
                            <div
                                v-for="(att, index) in attachments"
                                :key="att.name + index"
                                class="group flex max-w-[220px] items-center gap-1.5 rounded-lg border border-border/50 bg-background/70 py-1 pr-1 pl-1.5"
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
                                        class="max-w-[120px] truncate text-[10px] leading-tight font-medium text-foreground"
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
                                size="icon-sm"
                                class="h-[38px] w-[38px] shrink-0 rounded-xl text-muted-foreground hover:bg-muted hover:text-foreground"
                                title="Attach a file"
                                :disabled="isLoading"
                                @click="openFileDialog"
                            >
                                <Paperclip class="h-4 w-4" />
                            </Button>
                            <Textarea
                                ref="textareaRef"
                                v-model="inputMessage"
                                placeholder="Ask me anything... (drag & drop files)"
                                class="max-h-[120px] min-h-[38px] flex-1 resize-none rounded-xl border-border/40 bg-background/60 px-3.5 py-2.5 text-xs placeholder:text-muted-foreground/50 focus-visible:ring-1 focus-visible:ring-primary/30"
                                @keydown.enter.prevent="sendMessage"
                            />
                            <Button
                                v-if="isLoading"
                                type="button"
                                size="icon-sm"
                                class="h-[38px] w-[38px] shrink-0 rounded-xl shadow-md"
                                title="Stop generating"
                                aria-label="Stop generating"
                                @click="stopGenerating"
                            >
                                <Square class="h-4 w-4" />
                            </Button>
                            <Button
                                v-else
                                type="submit"
                                size="icon-sm"
                                class="h-[38px] w-[38px] shrink-0 rounded-xl shadow-md"
                                :disabled="!inputMessage.trim() || isLoading"
                            >
                                <Send class="h-4 w-4" />
                            </Button>
                        </div>
                    </form>
                    <div
                        v-else
                        class="w-full rounded-xl border border-dashed border-border/40 bg-muted/20 px-3 py-2 text-center text-[11px] leading-relaxed text-muted-foreground italic"
                    >
                        {{ maintenanceMessage }}
                    </div>
                </CardFooter>
            </Card>
        </transition>

        <!-- Toggle Button -->
        <button
            @click="toggleChat"
            class="group relative flex h-12 w-12 items-center justify-center overflow-hidden rounded-full bg-primary text-primary-foreground shadow-xl shadow-black/10 transition-all duration-300 hover:scale-110 hover:shadow-primary/25 active:scale-95"
        >
            <div
                class="absolute inset-0 bg-white/10 opacity-0 transition-opacity group-hover:opacity-100"
            ></div>
            <X
                v-if="isOpen"
                class="animate-in spin-in-90 h-5 w-5 duration-300"
            />
            <MessageCircle
                v-else
                class="animate-in zoom-in h-5 w-5 duration-300"
            />
        </button>
    </div>
</template>

<style scoped>
/* Thin scrollbar for the messages area */
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

@keyframes shake {
    0%,
    100% {
        transform: translateX(0);
    }
    10%,
    50%,
    90% {
        transform: translateX(-4px);
    }
    30%,
    70% {
        transform: translateX(4px);
    }
}

.animate-shake {
    animation: shake 0.5s ease-in-out;
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
   :deep() to be reached by scoped styles). Sized to match the text-xs bubble. */
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
