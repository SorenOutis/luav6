<script setup lang="ts">
import { ref, computed, nextTick, watch, onMounted } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { MessageCircle, Send, X, Bot, User } from 'lucide-vue-next';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
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
watch(messages, () => {
    scrollToBottom();
}, { deep: true });

const focusTextarea = async () => {
    await nextTick();
    const el = textareaRef.value?.$el || textareaRef.value;
    const textarea = el instanceof HTMLTextAreaElement ? el : el?.querySelector('textarea');
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
            messages.value = [{ role: 'assistant', content: 'Hello! How can I help you today?' }];
        }
        await scrollToBottom();
    } catch (error) {
        console.error('Failed to fetch chat history:', error);
        messages.value = [{ role: 'assistant', content: 'Hello! How can I help you today?' }];
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
        await new Promise(resolve => setTimeout(resolve, speed));
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
            message: userMessage
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
        const errorMessage = error.response?.data?.response || 'KOA is having trouble connecting to the AI provider. Please try again in a moment.';
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
        class="fixed bottom-6 right-6 z-50 flex flex-col items-end gap-4"
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
            <Card v-if="isOpen" class="w-[350px] sm:w-[400px] h-[500px] shadow-2xl flex flex-col overflow-hidden border-border/50 bg-card/95 backdrop-blur-md">
                <CardHeader class="p-4 border-b bg-primary text-primary-foreground flex flex-row items-center justify-between space-y-0">
                    <CardTitle class="text-lg font-bold flex items-center gap-2">
                        <Bot class="w-5 h-5" />
                        KOA - AI Assistant
                    </CardTitle>
                    <Button variant="ghost" size="icon" class="h-8 w-8 text-primary-foreground hover:bg-primary-foreground/10" @click="toggleChat">
                        <X class="w-5 h-5" />
                    </Button>
                </CardHeader>
                
                <CardContent 
                    ref="scrollContainer"
                    class="flex-1 overflow-y-auto p-4 space-y-4 scroll-smooth"
                >
                    <div 
                        v-for="(msg, index) in messages" 
                        :key="index"
                        :class="[
                            'flex w-full gap-2 max-w-[85%]',
                            msg.role === 'user' ? 'ml-auto flex-row-reverse' : ''
                        ]"
                    >
                        <div 
                            :class="[
                                'w-8 h-8 rounded-full flex items-center justify-center shrink-0 shadow-sm',
                                msg.role === 'user' ? 'bg-primary text-primary-foreground' : 'bg-muted border border-border'
                            ]"
                        >
                            <User v-if="msg.role === 'user'" class="w-4 h-4" />
                            <Bot v-else class="w-4 h-4 text-primary" />
                        </div>
                        <div 
                            :class="[
                                'p-3 rounded-2xl text-sm leading-relaxed shadow-sm',
                                msg.role === 'user' 
                                    ? 'bg-primary text-primary-foreground rounded-tr-none' 
                                    : 'bg-muted/50 border border-border/50 rounded-tl-none'
                            ]"
                        >
                            {{ msg.content }}
                        </div>
                    </div>
                    <div v-if="isLoading" class="flex gap-2 max-w-[85%] animate-pulse">
                        <div class="w-8 h-8 rounded-full bg-muted flex items-center justify-center">
                            <Bot class="w-4 h-4 text-primary" />
                        </div>
                        <div class="p-3 rounded-2xl bg-muted/50 border border-border/50 rounded-tl-none">
                            <div class="flex gap-1">
                                <span class="w-1.5 h-1.5 bg-foreground/30 rounded-full animate-bounce" style="animation-delay: 0ms"></span>
                                <span class="w-1.5 h-1.5 bg-foreground/30 rounded-full animate-bounce" style="animation-delay: 150ms"></span>
                                <span class="w-1.5 h-1.5 bg-foreground/30 rounded-full animate-bounce" style="animation-delay: 300ms"></span>
                            </div>
                        </div>
                    </div>
                </CardContent>

                <CardFooter class="p-4 border-t bg-muted/30">
                    <form v-if="isEnabled" @submit.prevent="sendMessage" class="flex w-full gap-2 items-end">
                        <Textarea
                            ref="textareaRef"
                            v-model="inputMessage"
                            placeholder="Type your message..."
                            class="min-h-[44px] max-h-[150px] resize-none py-3 px-4 rounded-xl focus-visible:ring-primary border-border/50 bg-background/50"
                            @keydown.enter.prevent="sendMessage"
                        />
                        <Button 
                            type="submit" 
                            size="icon" 
                            class="h-[44px] w-[44px] shrink-0 rounded-xl shadow-lg"
                            :disabled="!inputMessage.trim() || isLoading"
                        >
                            <Send class="w-5 h-5" />
                        </Button>
                    </form>
                    <div v-else class="w-full text-center p-2 text-sm text-muted-foreground italic bg-muted/20 rounded-lg border border-dashed border-border/50">
                        {{ maintenanceMessage }}
                    </div>
                </CardFooter>
            </Card>
        </transition>

        <!-- Toggle Button -->
        <button
            @click="toggleChat"
            class="flex h-14 w-14 items-center justify-center rounded-full bg-primary text-primary-foreground shadow-xl transition-all duration-300 hover:scale-110 active:scale-95 group relative overflow-hidden"
        >
            <div class="absolute inset-0 bg-white/10 opacity-0 group-hover:opacity-100 transition-opacity"></div>
            <X v-if="isOpen" class="h-6 w-6 animate-in spin-in-90 duration-300" />
            <MessageCircle v-else class="h-6 w-6 animate-in zoom-in duration-300" />
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
