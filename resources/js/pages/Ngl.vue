<script setup lang="ts">
import { Head, useForm, usePage } from '@inertiajs/vue3';
import { ref, onMounted } from 'vue';
import gsap from 'gsap';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import { index as nglIndex, store as nglStore, like as nglLike } from '@/routes/ngl';
import type { BreadcrumbItem } from '@/types';
import Card from '@/components/ui/card/Card.vue';
import CardContent from '@/components/ui/card/CardContent.vue';
import CardHeader from '@/components/ui/card/CardHeader.vue';
import CardTitle from '@/components/ui/card/CardTitle.vue';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { MessageSquare, Send, Shield, User, Heart, Sparkles, Plus } from 'lucide-vue-next';

interface Message {
    id: number;
    content: string;
    likes_count: number;
    created_at: string;
}

const props = defineProps<{
    messages: Message[];
    userLikedMessageIds: number[];
}>();

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: dashboard() },
    { title: 'NGL', href: nglIndex().url },
];

const form = useForm({
    content: '',
});

const isSubmitting = ref(false);
const showSuccess = ref(false);
const showSubmissionModal = ref(false);

const localLikedMessageIds = ref<number[]>([]);

onMounted(() => {
    localLikedMessageIds.value = [...props.userLikedMessageIds];

    setTimeout(() => {
        gsap.from('.ngl-card', {
            y: 20,
            opacity: 0,
            duration: 0.8,
            stagger: 0.1,
            ease: 'power3.out'
        });
    }, 100);
});

const isMessageLiked = (messageId: number) => {
    return localLikedMessageIds.value.includes(messageId);
};

const toggleLike = (message: Message) => {
    const messageId = message.id;
    const isLiked = isMessageLiked(messageId);

    // Optimistic UI update
    if (isLiked) {
        localLikedMessageIds.value = localLikedMessageIds.value.filter(id => id !== messageId);
    } else {
        localLikedMessageIds.value.push(messageId);
    }

    form.post(nglLike(messageId).url, {
        preserveScroll: true,
        onError: () => {
            // Revert on error
            if (isLiked) {
                localLikedMessageIds.value.push(messageId);
            } else {
                localLikedMessageIds.value = localLikedMessageIds.value.filter(id => id !== messageId);
            }
        }
    });
};

const submit = () => {
    if (!form.content.trim() || isSubmitting.value) return;

    isSubmitting.value = true;
    form.post(nglStore().url, {
        preserveScroll: true,
        onSuccess: () => {
            form.reset();
            showSuccess.value = true;
            isSubmitting.value = false;
            showSubmissionModal.value = false;
            setTimeout(() => {
                showSuccess.value = false;
            }, 5000);
        },
        onError: () => {
            isSubmitting.value = false;
        }
    });
};

const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit'
    });
};

onMounted(() => {
    gsap.from('.ngl-card', {
        y: 20,
        opacity: 0,
        duration: 0.8,
        stagger: 0.1,
        ease: 'power3.out'
    });
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Anonymous Messages" />

        <div class="relative min-h-screen bg-background/50">
            <!-- Global Background Gradient to avoid white space -->
            <div class="fixed inset-0 bg-gradient-to-br from-background via-muted/10 to-background pointer-events-none"></div>
            
            <!-- Background Elements -->
            <div class="absolute inset-0 overflow-hidden pointer-events-none">
                <div class="absolute -top-[10%] -right-[10%] w-[40%] h-[40%] bg-primary/5 blur-[120px] rounded-full animate-pulse"></div>
                <div class="absolute top-[20%] -left-[10%] w-[30%] h-[30%] bg-primary/3 blur-[100px] rounded-full"></div>
                <div class="absolute inset-0 bg-noise opacity-[0.02]"></div>
            </div>

            <div class="relative max-w-[1600px] mx-auto px-4 sm:px-6 lg:px-8 py-8 space-y-10">
                <!-- Compact Header -->
                <div class="surface-card p-6 sm:p-10 flex flex-col lg:flex-row items-center justify-between gap-8 overflow-hidden relative group border-primary/20">
                    <div class="absolute inset-0 bg-gradient-to-br from-primary/[0.03] to-transparent opacity-0 group-hover:opacity-100 transition-opacity duration-700"></div>
                    
                    <div class="relative z-10 space-y-4 text-center lg:text-left max-w-2xl">
                        <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-full bg-primary/10 border border-primary/20 text-primary text-[10px] font-black uppercase tracking-[0.2em]">
                            <Shield class="w-3.5 h-3.5" />
                            Anonymous Shield Active
                        </div>
                        <h1 class="text-4xl sm:text-6xl font-black tracking-tighter leading-none">
                            Academy <span class="text-primary">Shoutouts</span>
                        </h1>
                        <p class="text-muted-foreground text-base sm:text-lg font-medium max-w-xl">
                            Share your thoughts, celebrate wins, or give feedback. Your identity is 100% protected by our elite encryption.
                        </p>
                    </div>
                    
                    <div class="relative z-10 flex flex-col items-center lg:items-end gap-4 shrink-0">
                        <Button 
                            @click="showSubmissionModal = true"
                            size="lg"
                            class="h-16 px-10 rounded-2xl font-black uppercase tracking-[0.15em] gap-4 transition-all hover:scale-[1.02] active:scale-[0.98] shadow-2xl shadow-primary/30 bg-primary text-primary-foreground border-b-4 border-primary/40 hover:border-b-0 group/btn"
                        >
                            <div class="p-2 bg-primary-foreground/10 rounded-xl group-hover/btn:rotate-12 transition-transform">
                                <MessageSquare class="w-6 h-6" />
                            </div>
                            Post a Shoutout
                        </Button>
                        <div v-if="showSuccess" class="flex items-center gap-2 px-4 py-2 rounded-xl bg-emerald-500/10 border border-emerald-500/20 text-emerald-500 text-[10px] font-black uppercase tracking-widest animate-bounce">
                            <Heart class="w-3 h-3 fill-current" />
                            Post Delivered Successfully
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="space-y-6">
                    <div class="flex items-center justify-between px-2">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-primary/10 flex items-center justify-center border border-primary/20 shadow-inner">
                                <Sparkles class="w-5 h-5 text-primary" />
                            </div>
                            <h2 class="text-2xl font-black tracking-tight uppercase tracking-tighter">The Shoutout Feed</h2>
                        </div>
                        <div class="flex items-center gap-4">
                            <div class="hidden sm:flex items-center gap-2 text-[10px] font-black text-muted-foreground uppercase tracking-widest bg-muted/30 px-4 py-2 rounded-xl border border-border/40">
                                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                {{ messages.length }} Approved Messages
                            </div>
                        </div>
                    </div>

                    <!-- Masonry-like Grid -->
                    <div v-if="messages.length === 0" class="surface-card p-20 text-center animate-fade-up">
                        <div class="w-24 h-24 mb-8 bg-muted/30 rounded-3xl flex items-center justify-center mx-auto border border-border/20 shadow-2xl group hover:scale-110 transition-transform duration-500">
                            <MessageSquare class="w-12 h-12 text-muted-foreground/30 group-hover:text-primary/40 transition-colors" />
                        </div>
                        <h3 class="text-3xl font-black tracking-tighter mb-3">The feed is waiting...</h3>
                        <p class="text-muted-foreground text-lg max-w-sm mx-auto font-medium opacity-70 italic">Be the one to break the silence and inspire the academy.</p>
                        <Button @click="showSubmissionModal = true" variant="link" class="mt-6 font-black uppercase tracking-widest text-primary hover:scale-105 transition-transform">Post the first message</Button>
                    </div>

                    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 sm:gap-6">
                    <div 
                        v-for="(message, idx) in messages" 
                        :key="message.id"
                        class="ngl-card group relative p-5 sm:p-8 rounded-[1.5rem] sm:rounded-[2rem] border border-border/40 bg-card/40 backdrop-blur-xl hover:border-primary/40 transition-all duration-500 hover:-translate-y-2 animate-fade-up flex flex-col justify-between min-h-[180px] sm:min-h-[220px]"
                        :class="`stagger-${(idx % 10) + 1}`"
                    >
                        <!-- Decorative Quote Mark -->
                        <div class="absolute -top-4 -left-2 text-[80px] sm:text-[120px] font-serif text-primary/5 leading-none select-none group-hover:text-primary/10 transition-colors italic">"</div>
                        
                        <div class="relative z-10 space-y-4 sm:space-y-6">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-3">
                                    <div class="w-10 h-10 sm:w-12 h-12 rounded-xl sm:rounded-2xl bg-gradient-to-br from-primary/20 to-primary/5 flex items-center justify-center border border-primary/20 shadow-xl group-hover:from-primary group-hover:to-primary/80 group-hover:text-primary-foreground transition-all duration-500">
                                        <User class="w-5 h-5 sm:w-6 h-6" />
                                    </div>
                                    <div class="space-y-0.5">
                                        <div class="text-[10px] sm:text-xs font-black tracking-tight uppercase">Anonymous</div>
                                        <div class="text-[8px] sm:text-[9px] text-muted-foreground font-bold uppercase tracking-[0.1em] opacity-50">
                                            {{ formatDate(message.created_at) }}
                                        </div>
                                    </div>
                                </div>
                                <button 
                                    @click="toggleLike(message)"
                                    class="transition-all duration-300 hover:scale-125 active:scale-90 flex flex-col items-center gap-1 group/heart"
                                >
                                    <div class="p-2 rounded-full transition-colors" :class="isMessageLiked(message.id) ? 'bg-primary/20' : 'bg-primary/10 group-hover/heart:bg-primary/20'">
                                        <Heart 
                                            class="w-4 h-4 transition-all duration-300" 
                                            :class="isMessageLiked(message.id) ? 'text-primary' : 'text-primary/40 group-hover/heart:text-primary'" 
                                            :fill="isMessageLiked(message.id) ? 'currentColor' : 'none'"
                                        />
                                    </div>
                                    <span v-if="message.likes_count > 0 || isMessageLiked(message.id)" class="text-[8px] font-black text-primary/60">
                                        {{ message.likes_count }}
                                    </span>
                                </button>
                            </div>
                            
                            <div class="relative">
                                <p class="text-foreground/90 font-bold leading-relaxed italic text-sm sm:text-lg tracking-tight group-hover:text-foreground transition-colors line-clamp-4">
                                    {{ message.content }}
                                </p>
                            </div>
                        </div>

                        <!-- Bottom Accent -->
                        <div class="mt-6 sm:mt-8 pt-4 border-t border-primary/5 flex items-center justify-between">
                            <div class="flex gap-1">
                                <div v-for="i in 3" :key="i" class="w-1 h-1 rounded-full bg-primary/20"></div>
                            </div>
                            <div class="text-[8px] font-black uppercase tracking-widest text-primary/40 group-hover:text-primary/60 transition-colors">Verified Shoutout</div>
                        </div>
                    </div>
                </div>
                </div>
            </div>
        </div>

        <!-- Submission Modal - Revamped -->
        <Dialog :open="showSubmissionModal" @update:open="showSubmissionModal = $event">
            <DialogContent class="max-w-[95vw] sm:max-w-[600px] border-primary/30 bg-background/95 backdrop-blur-3xl rounded-[2rem] sm:rounded-[2.5rem] shadow-[0_32px_64px_-12px_rgba(var(--primary),0.2)] p-0 overflow-hidden">
                <div class="absolute top-0 left-0 w-full h-2 bg-gradient-to-r from-transparent via-primary to-transparent"></div>
                
                <div class="p-6 sm:p-12 space-y-6 sm:space-y-8">
                    <DialogHeader class="text-center sm:text-left">
                        <div class="flex flex-col sm:flex-row items-center gap-4 sm:gap-6">
                            <div class="w-12 h-12 sm:w-20 sm:h-20 rounded-2xl sm:rounded-3xl bg-primary/10 flex items-center justify-center border border-primary/20 shadow-2xl relative group">
                                <div class="absolute inset-0 bg-primary blur-xl opacity-20 group-hover:opacity-40 transition-opacity"></div>
                                <MessageSquare class="w-6 h-6 sm:w-10 sm:h-10 text-primary relative z-10" />
                            </div>
                            <div class="space-y-1 sm:space-y-2">
                                <DialogTitle class="text-2xl sm:text-4xl font-black tracking-tighter leading-tight">Post Anonymously</DialogTitle>
                                <DialogDescription class="text-muted-foreground font-medium text-xs sm:text-lg leading-tight">
                                    Your message will be encrypted and shielded. No one will know it's you.
                                </DialogDescription>
                            </div>
                        </div>
                    </DialogHeader>

                    <div class="space-y-4 sm:space-y-6">
                        <div class="relative group">
                            <div class="absolute -inset-1 bg-gradient-to-r from-primary/20 to-transparent rounded-[1.5rem] sm:rounded-[2rem] blur opacity-0 group-focus-within:opacity-100 transition-opacity"></div>
                            <Textarea 
                                v-model="form.content"
                                placeholder="What's on your mind? Be bold, be real..."
                                class="relative min-h-[140px] sm:min-h-[220px] bg-muted/40 border-border/40 focus:border-primary/50 focus:ring-primary/20 text-base sm:text-xl font-bold tracking-tight transition-all resize-none rounded-[1.25rem] sm:rounded-[1.5rem] p-5 sm:p-8 placeholder:text-muted-foreground/30 shadow-inner"
                                :disabled="isSubmitting"
                            />
                            <div v-if="form.errors.content" class="text-destructive text-[9px] sm:text-[10px] font-black uppercase tracking-[0.2em] pt-2 px-2">
                                {{ form.errors.content }}
                            </div>
                        </div>

                        <div class="flex items-center gap-3 sm:gap-4 p-4 sm:p-5 rounded-[1.25rem] sm:rounded-[1.5rem] bg-primary/5 border border-primary/10 group">
                            <div class="p-1.5 bg-primary/10 rounded-lg group-hover:rotate-12 transition-transform">
                                <Shield class="w-4 h-4 sm:w-6 sm:h-6 text-primary" />
                            </div>
                            <div class="space-y-0.5">
                                <div class="text-[9px] sm:text-[11px] font-black uppercase tracking-[0.1em] text-primary">Identity Shield Active</div>
                                <div class="text-[8px] sm:text-[10px] font-bold text-muted-foreground uppercase tracking-widest opacity-60">100% Anonymous Routing</div>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row gap-2 sm:gap-4">
                        <Button 
                            @click="submit" 
                            class="h-12 sm:h-14 px-8 sm:px-10 rounded-xl sm:rounded-2xl font-black uppercase tracking-[0.15em] gap-3 sm:gap-4 transition-all hover:scale-[1.02] active:scale-[0.98] shadow-2xl shadow-primary/30 flex-1 group/submit order-1"
                            :disabled="isSubmitting || !form.content.trim()"
                        >
                            <div v-if="!isSubmitting" class="p-1.5 bg-primary-foreground/10 rounded-lg group-hover/submit:translate-x-1 transition-transform">
                                <Send class="w-4 h-4" />
                            </div>
                            <Sparkles v-else class="w-5 h-5 animate-spin" />
                            {{ isSubmitting ? 'Encrypting...' : 'Post Shoutout' }}
                        </Button>
                        <Button 
                            variant="ghost" 
                            @click="showSubmissionModal = false"
                            class="h-10 sm:h-14 px-6 sm:px-8 rounded-xl sm:rounded-2xl font-black uppercase tracking-widest text-[9px] sm:text-[10px] hover:bg-muted/50 order-2"
                            :disabled="isSubmitting"
                        >
                            Cancel
                        </Button>
                    </div>
                </div>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.8s ease-out forwards;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

.surface-card, .ngl-card {
    position: relative;
    isolation: isolate;
}

.surface-card::before, .ngl-card::before {
    content: "";
    position: absolute;
    inset: 0;
    opacity: 0.02;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
    z-index: -1;
}

.surface-card {
    background: linear-gradient(135deg, hsl(var(--card) / 0.8), hsl(var(--card) / 0.4));
    backdrop-filter: blur(20px);
    border: 1px solid hsl(var(--border) / 0.4);
    border-radius: 2.5rem;
}
</style>
