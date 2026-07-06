<script setup lang="ts">
import { usePage } from '@inertiajs/vue3';
import axios from 'axios';
import { MessageCircle, Send, X, Bot, User } from 'lucide-vue-next';
import AppLogoIcon from '@/components/AppLogoIcon.vue';
import { ref, computed, nextTick, watch, onMounted } from 'vue';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';

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
const messages = ref<{ role: string; content: string; typing?: boolean }[]>([]);
const isLoading = ref(false);
const scrollContainer = ref<HTMLElement | null>(null);
const textareaRef = ref<any>(null);

const isVisible = computed(() => {
    const component = page.component;
    return component === 'Dashboard' || component === 'Assignments';
});

const isEnabled = computed(() => page.props.aiChat.enabled);
const maintenanceMessage = computed(() => page.props.aiChat.maintenanceMessage);

const showSuggestions = computed(() => {
    // Only show suggestions when the only messages are the welcome message
    if (messages.value.length === 0) return false;
    const userMessages = messages.value.filter((m) => m.role === 'user');
    return userMessages.length === 0;
});

const suggestions = [
    { label: '📋 My Assignments', message: 'What are my upcoming assignments?' },
    { label: '📊 My Progress', message: 'Show me my learning progress' },
    { label: '🏆 My Streak', message: "What's my current streak?" },
    { label: '📝 Upcoming Exams', message: 'What exams do I have coming up?' },
];

const useSuggestion = (suggestion: string) => {
    inputMessage.value = suggestion;
    sendMessage();
};

const scrollToBottom = async () => {
    await nextTick();
    const container = scrollContainer.value?.$el || scrollContainer.value;
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

const fetchHistory = async () => {
    try {
        const response = await axios.get('/api/chat/history');
        if (response.data.history && response.data.history.length > 0) {
            messages.value = response.data.history;
        } else {
            messages.value = [
                {
                    role: 'assistant',
                    content: 'Hello! I\'m Echo. How can I assist you today?',
                },
            ];
        }
        await scrollToBottom();
    } catch (error) {
        console.error('Failed to fetch chat history:', error);
        messages.value = [
            { role: 'assistant', content: 'Hello! I\'m Echo. How can I assist you today?' },
        ];
    }
};

onMounted(() => {
    fetchHistory();
});

const toggleChat = () => {
    isOpen.value = !isOpen.value;
};

const handleAfterEnter = () => {
    scrollToBottom();
    focusTextarea();
};

const typeMessage = async (fullText: string) => {
    const newMessage = { role: 'assistant', content: '', typing: true };
    messages.value.push(newMessage);

    const index = messages.value.length - 1;
    let currentText = '';
    const speed = 15; // ms per character

    for (let i = 0; i < fullText.length; i++) {
        currentText += fullText[i];
        messages.value[index].content = currentText;
        await new Promise((resolve) => setTimeout(resolve, speed));
        scrollToBottom();
    }

    messages.value[index].typing = false;
};

const sendMessage = async () => {
    if (!inputMessage.value.trim() || isLoading.value) return;

    const userMessage = inputMessage.value.trim();
    messages.value.push({ role: 'user', content: userMessage });
    inputMessage.value = '';
    isLoading.value = true;

    await scrollToBottom();

    try {
        const response = await axios.post('/api/chat', {
            message: userMessage,
        });

        // Hide loading indicator before starting the typing effect
        isLoading.value = false;

        // Don't just replace history, handle the new response with typing effect
        const aiResponse = response.data.response;
        await typeMessage(aiResponse);

        // Sync full history silently if needed
        messages.value = response.data.history;
    } catch (error) {
        isLoading.value = false;
        console.error('Chat error:', error);
        const errorMessage =
            error.response?.data?.response ||
            'Echo is having trouble connecting to the AI provider. Please try again in a moment.';
        await typeMessage(errorMessage);
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
        class="fixed right-5 bottom-5 z-50 flex flex-col items-end gap-3"
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
                class="flex h-[480px] w-[360px] flex-col gap-0 overflow-hidden rounded-xl border-border/40 bg-card/90 p-0 shadow-2xl shadow-black/5 backdrop-blur-xl sm:w-[400px]"
            >
                <!-- Compact Header with rounded top corners -->
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 rounded-t-xl border-b border-border/40 bg-gradient-to-r from-primary/95 to-primary/90 px-3 py-2.5"
                >
                    <div class="flex items-center gap-2 min-w-0">
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
                                class="text-xs font-semibold leading-tight text-primary-foreground truncate"
                            >
                                Echo — LSI Assistant
                            </CardTitle>
                            <p class="text-[10px] leading-tight text-primary-foreground/60">
                                Your intelligent companion
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
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
                    class="flex-1 space-y-3 overflow-y-auto scroll-smooth p-3 scrollbar-thin"
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
                                <Bot
                                    v-else
                                    class="h-3.5 w-3.5 text-primary"
                                />
                            </div>
                            <div
                                :class="[
                                    'rounded-2xl px-3 py-2 text-xs leading-relaxed shadow-xs',
                                    msg.role === 'user'
                                        ? 'rounded-tr-sm bg-primary text-primary-foreground'
                                        : 'rounded-tl-sm border border-border/40 bg-muted/40 text-foreground',
                                ]"
                            >
                                {{ msg.content }}
                            </div>
                        </div>
                    </template>

                    <!-- Suggestion chips -->
                    <div
                        v-if="showSuggestions"
                        class="flex flex-wrap gap-1.5 px-1 animate-fade-in"
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

                    <!-- Loading indicator -->
                    <div
                        v-if="isLoading"
                        class="flex max-w-[88%] animate-fade-in gap-2"
                    >
                        <div
                            class="flex h-7 w-7 shrink-0 items-center justify-center overflow-hidden rounded-full border border-border/60 bg-muted/80"
                        >
                            <img
                                v-if="logoUrl"
                                :src="logoUrl"
                                alt="Echo"
                                class="h-full w-full object-contain p-1"
                            />
                            <Bot
                                v-else
                                class="h-3.5 w-3.5 text-primary"
                            />
                        </div>
                        <div
                            class="rounded-2xl rounded-tl-sm border border-border/40 bg-muted/40 p-3"
                        >
                            <div class="flex items-center gap-1.5">
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
                        </div>
                    </div>
                </CardContent>

                <!-- Input Footer -->
                <CardFooter
                    class="border-t border-border/40 bg-muted/20 p-3 pt-2.5"
                >
                    <form
                        v-if="isEnabled"
                        @submit.prevent="sendMessage"
                        class="flex w-full items-end gap-2"
                    >
                        <Textarea
                            ref="textareaRef"
                            v-model="inputMessage"
                            placeholder="Ask me anything..."
                            class="max-h-[120px] min-h-[38px] resize-none rounded-xl border-border/40 bg-background/60 px-3.5 py-2.5 text-xs placeholder:text-muted-foreground/50 focus-visible:ring-1 focus-visible:ring-primary/30"
                            @keydown.enter.prevent="sendMessage"
                        />
                        <Button
                            type="submit"
                            size="icon-sm"
                            class="h-[38px] w-[38px] shrink-0 rounded-xl shadow-md"
                            :disabled="!inputMessage.trim() || isLoading"
                        >
                            <Send class="h-4 w-4" />
                        </Button>
                    </form>
                    <div
                        v-else
                        class="w-full rounded-xl border border-dashed border-border/40 bg-muted/20 px-3 py-2 text-center text-[11px] text-muted-foreground italic leading-relaxed"
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
</style>
