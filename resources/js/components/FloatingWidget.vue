<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { MessageCircle, Send, X, Bot, User } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import {
    Card,
    CardContent,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import axios from 'axios';

const page = usePage();
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
                    content: 'Hello! How can I help you today?',
                },
            ];
        }
        await scrollToBottom();
    } catch (error) {
        console.error('Failed to fetch chat history:', error);
        messages.value = [
            { role: 'assistant', content: 'Hello! How can I help you today?' },
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
            'KOA is having trouble connecting to the AI provider. Please try again in a moment.';
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
        class="fixed right-6 bottom-6 z-50 flex flex-col items-end gap-4"
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
                class="flex h-[500px] w-[350px] flex-col overflow-hidden border-border/50 bg-card/95 shadow-2xl backdrop-blur-md sm:w-[400px]"
            >
                <CardHeader
                    class="flex flex-row items-center justify-between space-y-0 border-b bg-primary p-4 text-primary-foreground"
                >
                    <CardTitle
                        class="flex items-center gap-2 text-lg font-bold"
                    >
                        <Bot class="h-5 w-5" />
                        KOA - AI Assistant
                    </CardTitle>
                    <Button
                        variant="ghost"
                        size="icon"
                        class="h-8 w-8 text-primary-foreground hover:bg-primary-foreground/10"
                        @click="toggleChat"
                    >
                        <X class="h-5 w-5" />
                    </Button>
                </CardHeader>

                <CardContent
                    ref="scrollContainer"
                    class="flex-1 space-y-4 overflow-y-auto scroll-smooth p-4"
                >
                    <div
                        v-for="(msg, index) in messages"
                        :key="index"
                        :class="[
                            'flex w-full max-w-[85%] gap-2',
                            msg.role === 'user'
                                ? 'ml-auto flex-row-reverse'
                                : '',
                        ]"
                    >
                        <div
                            :class="[
                                'flex h-8 w-8 shrink-0 items-center justify-center rounded-full shadow-sm',
                                msg.role === 'user'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'border border-border bg-muted',
                            ]"
                        >
                            <User v-if="msg.role === 'user'" class="h-4 w-4" />
                            <Bot v-else class="h-4 w-4 text-primary" />
                        </div>
                        <div
                            :class="[
                                'rounded-2xl p-3 text-sm leading-relaxed shadow-sm',
                                msg.role === 'user'
                                    ? 'rounded-tr-none bg-primary text-primary-foreground'
                                    : 'rounded-tl-none border border-border/50 bg-muted/50',
                            ]"
                        >
                            {{ msg.content }}
                        </div>
                    </div>
                    <div
                        v-if="isLoading"
                        class="flex max-w-[85%] animate-pulse gap-2"
                    >
                        <div
                            class="flex h-8 w-8 items-center justify-center rounded-full bg-muted"
                        >
                            <Bot class="h-4 w-4 text-primary" />
                        </div>
                        <div
                            class="rounded-2xl rounded-tl-none border border-border/50 bg-muted/50 p-3"
                        >
                            <div class="flex gap-1">
                                <span
                                    class="h-1.5 w-1.5 animate-bounce rounded-full bg-foreground/30"
                                    style="animation-delay: 0ms"
                                ></span>
                                <span
                                    class="h-1.5 w-1.5 animate-bounce rounded-full bg-foreground/30"
                                    style="animation-delay: 150ms"
                                ></span>
                                <span
                                    class="h-1.5 w-1.5 animate-bounce rounded-full bg-foreground/30"
                                    style="animation-delay: 300ms"
                                ></span>
                            </div>
                        </div>
                    </div>
                </CardContent>

                <CardFooter class="border-t bg-muted/30 p-4">
                    <form
                        v-if="isEnabled"
                        @submit.prevent="sendMessage"
                        class="flex w-full items-end gap-2"
                    >
                        <Textarea
                            ref="textareaRef"
                            v-model="inputMessage"
                            placeholder="Type your message..."
                            class="max-h-[150px] min-h-[44px] resize-none rounded-xl border-border/50 bg-background/50 px-4 py-3 focus-visible:ring-primary"
                            @keydown.enter.prevent="sendMessage"
                        />
                        <Button
                            type="submit"
                            size="icon"
                            class="h-[44px] w-[44px] shrink-0 rounded-xl shadow-lg"
                            :disabled="!inputMessage.trim() || isLoading"
                        >
                            <Send class="h-5 w-5" />
                        </Button>
                    </form>
                    <div
                        v-else
                        class="w-full rounded-lg border border-dashed border-border/50 bg-muted/20 p-2 text-center text-sm text-muted-foreground italic"
                    >
                        {{ maintenanceMessage }}
                    </div>
                </CardFooter>
            </Card>
        </transition>

        <!-- Toggle Button -->
        <button
            @click="toggleChat"
            class="group relative flex h-14 w-14 items-center justify-center overflow-hidden rounded-full bg-primary text-primary-foreground shadow-xl transition-all duration-300 hover:scale-110 active:scale-95"
        >
            <div
                class="absolute inset-0 bg-white/10 opacity-0 transition-opacity group-hover:opacity-100"
            ></div>
            <X
                v-if="isOpen"
                class="animate-in spin-in-90 h-6 w-6 duration-300"
            />
            <MessageCircle
                v-else
                class="animate-in zoom-in h-6 w-6 duration-300"
            />
        </button>
    </div>
</template>

<style scoped>
/* Hide scrollbar but keep functionality if needed */
.overflow-y-auto {
    scrollbar-width: thin;
    scrollbar-color: var(--color-border) transparent;
}
</style>
