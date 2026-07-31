<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3';
import { useTimeoutFn } from '@vueuse/core';
import gsap from 'gsap';
import {
    MessageSquare,
    Send,
    Shield,
    User,
    Heart,
    Sparkles,
} from 'lucide-vue-next';
import { ref, onMounted } from 'vue';
import ResponsiveModal from '@/components/ResponsiveModal.vue';
import { Button } from '@/components/ui/button';
import {
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/AppLayout.vue';
import { dashboard } from '@/routes';
import {
    index as nglIndex,
    store as nglStore,
    like as nglLike,
} from '@/routes/ngl';
import type { BreadcrumbItem } from '@/types';

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

    // useTimeoutFn auto-cancels on unmount.
    useTimeoutFn(() => {
        gsap.from('.ngl-card', {
            y: 20,
            opacity: 0,
            duration: 0.8,
            stagger: 0.1,
            ease: 'power3.out',
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
        localLikedMessageIds.value = localLikedMessageIds.value.filter(
            (id) => id !== messageId,
        );
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
                localLikedMessageIds.value = localLikedMessageIds.value.filter(
                    (id) => id !== messageId,
                );
            }
        },
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
            // Auto-cancelled if the student navigates away before it fires.
            useTimeoutFn(() => {
                showSuccess.value = false;
            }, 5000);
        },
        onError: () => {
            isSubmitting.value = false;
        },
    });
};

const formatDate = (dateStr: string) => {
    const date = new Date(dateStr);
    return date.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(() => {
    gsap.from('.ngl-card', {
        y: 20,
        opacity: 0,
        duration: 0.8,
        stagger: 0.1,
        ease: 'power3.out',
    });
});
</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Anonymous Messages" />

        <div class="relative min-h-screen bg-background/50">
            <!-- Global Background Gradient to avoid white space -->
            <div
                class="pointer-events-none fixed inset-0 bg-gradient-to-br from-background via-muted/10 to-background"
            ></div>

            <!-- Background Elements -->
            <div class="pointer-events-none absolute inset-0 overflow-hidden">
                <div
                    class="absolute -top-[10%] -right-[10%] h-[40%] w-[40%] animate-pulse rounded-full bg-primary/5 blur-[120px]"
                ></div>
                <div
                    class="absolute top-[20%] -left-[10%] h-[30%] w-[30%] rounded-full bg-primary/3 blur-[100px]"
                ></div>
                <div class="bg-noise absolute inset-0 opacity-[0.02]"></div>
            </div>

            <div
                class="relative mx-auto max-w-[1600px] space-y-10 px-4 py-8 sm:px-6 lg:px-8"
            >
                <!-- Compact Header -->
                <div
                    class="surface-card group relative flex flex-col items-center justify-between gap-8 overflow-hidden border-primary/20 p-6 sm:p-10 lg:flex-row"
                >
                    <div
                        class="absolute inset-0 bg-gradient-to-br from-primary/[0.03] to-transparent opacity-0 transition-opacity duration-700 group-hover:opacity-100"
                    ></div>

                    <div
                        class="relative z-10 max-w-2xl space-y-4 text-center lg:text-left"
                    >
                        <div
                            class="inline-flex items-center gap-2 rounded-full border border-primary/20 bg-primary/10 px-3 py-1.5 text-[10px] font-black tracking-[0.2em] text-primary uppercase"
                        >
                            <Shield class="h-3.5 w-3.5" />
                            Anonymous Shield Active
                        </div>
                        <h1
                            class="text-4xl leading-none font-black tracking-tighter sm:text-6xl"
                        >
                            Academy <span class="text-primary">Shoutouts</span>
                        </h1>
                        <p
                            class="max-w-xl text-base font-medium text-muted-foreground sm:text-lg"
                        >
                            Share your thoughts, celebrate wins, or give
                            feedback. Your identity is 100% protected by our
                            elite encryption.
                        </p>
                    </div>

                    <div
                        class="relative z-10 flex shrink-0 flex-col items-center gap-4 lg:items-end"
                    >
                        <Button
                            @click="showSubmissionModal = true"
                            size="lg"
                            class="group/btn h-16 gap-4 rounded-2xl border-b-4 border-primary/40 bg-primary px-10 font-black tracking-[0.15em] text-primary-foreground uppercase shadow-2xl shadow-primary/30 transition-all hover:scale-[1.02] hover:border-b-0 active:scale-[0.98]"
                        >
                            <div
                                class="rounded-xl bg-primary-foreground/10 p-2 transition-transform group-hover/btn:rotate-12"
                            >
                                <MessageSquare class="h-6 w-6" />
                            </div>
                            Post a Shoutout
                        </Button>
                        <div
                            v-if="showSuccess"
                            class="flex animate-bounce items-center gap-2 rounded-xl border border-emerald-500/20 bg-emerald-500/10 px-4 py-2 text-[10px] font-black tracking-widest text-emerald-500 uppercase"
                        >
                            <Heart class="h-3 w-3 fill-current" />
                            Post Delivered Successfully
                        </div>
                    </div>
                </div>

                <!-- Content Grid -->
                <div class="space-y-6">
                    <div class="flex items-center justify-between px-2">
                        <div class="flex items-center gap-3">
                            <div
                                class="flex h-10 w-10 items-center justify-center rounded-xl border border-primary/20 bg-primary/10 shadow-inner"
                            >
                                <Sparkles class="h-5 w-5 text-primary" />
                            </div>
                            <h2
                                class="text-2xl font-black tracking-tight tracking-tighter uppercase"
                            >
                                The Shoutout Feed
                            </h2>
                        </div>
                        <div class="flex items-center gap-4">
                            <div
                                class="hidden items-center gap-2 rounded-xl border border-border/40 bg-muted/30 px-4 py-2 text-[10px] font-black tracking-widest text-muted-foreground uppercase sm:flex"
                            >
                                <span
                                    class="h-2 w-2 animate-pulse rounded-full bg-emerald-500"
                                ></span>
                                {{ messages.length }} Approved Messages
                            </div>
                        </div>
                    </div>

                    <!-- Masonry-like Grid -->
                    <div
                        v-if="messages.length === 0"
                        class="surface-card animate-fade-up p-20 text-center"
                    >
                        <div
                            class="group mx-auto mb-8 flex h-24 w-24 items-center justify-center rounded-3xl border border-border/20 bg-muted/30 shadow-2xl transition-transform duration-500 hover:scale-110"
                        >
                            <MessageSquare
                                class="h-12 w-12 text-muted-foreground/30 transition-colors group-hover:text-primary/40"
                            />
                        </div>
                        <h3 class="mb-3 text-3xl font-black tracking-tighter">
                            The feed is waiting...
                        </h3>
                        <p
                            class="mx-auto max-w-sm text-lg font-medium text-muted-foreground italic opacity-70"
                        >
                            Be the one to break the silence and inspire the
                            academy.
                        </p>
                        <Button
                            @click="showSubmissionModal = true"
                            variant="link"
                            class="mt-6 font-black tracking-widest text-primary uppercase transition-transform hover:scale-105"
                            >Post the first message</Button
                        >
                    </div>

                    <div
                        v-else
                        class="grid grid-cols-1 gap-4 sm:grid-cols-2 sm:gap-6 lg:grid-cols-3 xl:grid-cols-4"
                    >
                        <div
                            v-for="(message, idx) in messages"
                            :key="message.id"
                            class="ngl-card group animate-fade-up relative flex min-h-[180px] flex-col justify-between rounded-[1.5rem] border border-border/40 bg-card/40 p-5 backdrop-blur-xl transition-all duration-500 hover:-translate-y-2 hover:border-primary/40 sm:min-h-[220px] sm:rounded-[2rem] sm:p-8"
                            :class="`stagger-${(idx % 10) + 1}`"
                        >
                            <!-- Decorative Quote Mark -->
                            <div
                                class="absolute -top-4 -left-2 font-serif text-[80px] leading-none text-primary/5 italic transition-colors select-none group-hover:text-primary/10 sm:text-[120px]"
                            >
                                "
                            </div>

                            <div class="relative z-10 space-y-4 sm:space-y-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <div
                                            class="flex h-10 h-12 w-10 items-center justify-center rounded-xl border border-primary/20 bg-gradient-to-br from-primary/20 to-primary/5 shadow-xl transition-all duration-500 group-hover:from-primary group-hover:to-primary/80 group-hover:text-primary-foreground sm:w-12 sm:rounded-2xl"
                                        >
                                            <User class="h-5 h-6 w-5 sm:w-6" />
                                        </div>
                                        <div class="space-y-0.5">
                                            <div
                                                class="text-[10px] font-black tracking-tight uppercase sm:text-xs"
                                            >
                                                Anonymous
                                            </div>
                                            <div
                                                class="text-[8px] font-bold tracking-[0.1em] text-muted-foreground uppercase opacity-50 sm:text-[9px]"
                                            >
                                                {{
                                                    formatDate(
                                                        message.created_at,
                                                    )
                                                }}
                                            </div>
                                        </div>
                                    </div>
                                    <button
                                        @click="toggleLike(message)"
                                        class="group/heart flex flex-col items-center gap-1 transition-all duration-300 hover:scale-125 active:scale-90"
                                    >
                                        <div
                                            class="rounded-full p-2 transition-colors"
                                            :class="
                                                isMessageLiked(message.id)
                                                    ? 'bg-primary/20'
                                                    : 'bg-primary/10 group-hover/heart:bg-primary/20'
                                            "
                                        >
                                            <Heart
                                                class="h-4 w-4 transition-all duration-300"
                                                :class="
                                                    isMessageLiked(message.id)
                                                        ? 'text-primary'
                                                        : 'text-primary/40 group-hover/heart:text-primary'
                                                "
                                                :fill="
                                                    isMessageLiked(message.id)
                                                        ? 'currentColor'
                                                        : 'none'
                                                "
                                            />
                                        </div>
                                        <span
                                            v-if="
                                                message.likes_count > 0 ||
                                                isMessageLiked(message.id)
                                            "
                                            class="text-[8px] font-black text-primary/60"
                                        >
                                            {{ message.likes_count }}
                                        </span>
                                    </button>
                                </div>

                                <div class="relative">
                                    <p
                                        class="line-clamp-4 text-sm leading-relaxed font-bold tracking-tight text-foreground/90 italic transition-colors group-hover:text-foreground sm:text-lg"
                                    >
                                        {{ message.content }}
                                    </p>
                                </div>
                            </div>

                            <!-- Bottom Accent -->
                            <div
                                class="mt-6 flex items-center justify-between border-t border-primary/5 pt-4 sm:mt-8"
                            >
                                <div class="flex gap-1">
                                    <div
                                        v-for="i in 3"
                                        :key="i"
                                        class="h-1 w-1 rounded-full bg-primary/20"
                                    ></div>
                                </div>
                                <div
                                    class="text-[8px] font-black tracking-widest text-primary/40 uppercase transition-colors group-hover:text-primary/60"
                                >
                                    Verified Shoutout
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Submission Modal -->
        <ResponsiveModal
            :open="showSubmissionModal"
            custom-header
            content-class="overflow-hidden rounded-[2rem] border-primary/30 bg-background/95 p-0 shadow-2xl shadow-primary/20 backdrop-blur-3xl sm:rounded-[2.5rem] sm:max-w-[600px]"
            @close="showSubmissionModal = false"
        >
            <div class="absolute top-0 left-0 h-2 w-full bg-gradient-to-r from-transparent via-primary to-transparent pointer-events-none"></div>
            <template #header>
                <div class="flex flex-col items-center gap-4 sm:flex-row sm:gap-6">
                    <div
                        class="group relative flex h-12 w-12 items-center justify-center rounded-2xl border border-primary/20 bg-primary/10 shadow-2xl sm:h-20 sm:w-20 sm:rounded-3xl"
                    >
                        <div
                            class="absolute inset-0 bg-primary opacity-20 blur-xl transition-opacity group-hover:opacity-40"
                        ></div>
                        <MessageSquare
                            class="relative z-10 h-6 w-6 text-primary sm:h-10 sm:w-10"
                        />
                    </div>
                    <div class="space-y-1 sm:space-y-2">
                        <DialogTitle
                            class="text-2xl leading-tight font-black tracking-tighter sm:text-4xl"
                            >Post Anonymously</DialogTitle
                        >
                        <DialogDescription
                            class="text-xs leading-tight font-medium text-muted-foreground sm:text-lg"
                        >
                            Your message will be encrypted and shielded.
                            No one will know it's you.
                        </DialogDescription>
                    </div>
                </div>
            </template>

            <div class="space-y-4 sm:space-y-6">
                <div class="group relative">
                    <div
                        class="absolute -inset-1 rounded-[1.5rem] bg-gradient-to-r from-primary/20 to-transparent opacity-0 blur transition-opacity group-focus-within:opacity-100 sm:rounded-[2rem]"
                    ></div>
                    <Textarea
                        v-model="form.content"
                        placeholder="What's on your mind? Be bold, be real..."
                        class="relative min-h-[140px] resize-none rounded-[1.25rem] border-border/40 bg-muted/40 p-5 text-base font-bold tracking-tight shadow-inner transition-all placeholder:text-muted-foreground/30 focus:border-primary/50 focus:ring-primary/20 sm:min-h-[220px] sm:rounded-[1.5rem] sm:p-8 sm:text-xl"
                        :disabled="isSubmitting"
                    />
                    <div
                        v-if="form.errors.content"
                        class="px-2 pt-2 text-[9px] font-black tracking-[0.2em] text-destructive uppercase sm:text-[10px]"
                    >
                        {{ form.errors.content }}
                    </div>
                </div>

                <div
                    class="group flex items-center gap-3 rounded-[1.25rem] border border-primary/10 bg-primary/5 p-4 sm:gap-4 sm:rounded-[1.5rem] sm:p-5"
                >
                    <div
                        class="rounded-lg bg-primary/10 p-1.5 transition-transform group-hover:rotate-12"
                    >
                        <Shield
                            class="h-4 w-4 text-primary sm:h-6 sm:w-6"
                        />
                    </div>
                    <div class="space-y-0.5">
                        <div
                            class="text-[9px] font-black tracking-[0.1em] text-primary uppercase sm:text-[11px]"
                        >
                            Identity Shield Active
                        </div>
                        <div
                            class="text-[8px] font-bold tracking-widest text-muted-foreground uppercase opacity-60 sm:text-[10px]"
                        >
                            100% Anonymous Routing
                        </div>
                    </div>
                </div>
            </div>

            <template #footer>
                <Button
                    @click="submit"
                    class="group/submit order-1 h-12 flex-1 gap-3 rounded-xl px-8 font-black tracking-[0.15em] uppercase shadow-2xl shadow-primary/30 transition-all hover:scale-[1.02] active:scale-[0.98] sm:h-14 sm:gap-4 sm:rounded-2xl sm:px-10"
                    :disabled="isSubmitting || !form.content.trim()"
                >
                    <div
                        v-if="!isSubmitting"
                        class="rounded-lg bg-primary-foreground/10 p-1.5 transition-transform group-hover/submit:translate-x-1"
                    >
                        <Send class="h-4 w-4" />
                    </div>
                    <Sparkles v-else class="h-5 w-5 animate-spin" />
                    {{ isSubmitting ? 'Encrypting...' : 'Post Shoutout' }}
                </Button>
                <Button
                    variant="ghost"
                    @click="showSubmissionModal = false"
                    class="order-2 h-10 rounded-xl px-6 text-[9px] font-black tracking-widest uppercase hover:bg-muted/50 sm:h-14 sm:rounded-2xl sm:px-8 sm:text-[10px]"
                    :disabled="isSubmitting"
                >
                    Cancel
                </Button>
            </template>
        </ResponsiveModal>
    </AppLayout>
</template>

<style scoped>
.animate-fade-in {
    animation: fadeIn 0.8s ease-out forwards;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.surface-card,
.ngl-card {
    position: relative;
    isolation: isolate;
}

.surface-card::before,
.ngl-card::before {
    content: '';
    position: absolute;
    inset: 0;
    opacity: 0.02;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 100 100' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='noiseFilter'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.65' numOctaves='3' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23noiseFilter)'/%3E%3C/svg%3E");
    z-index: -1;
}

.surface-card {
    background: linear-gradient(
        135deg,
        hsl(var(--card) / 0.8),
        hsl(var(--card) / 0.4)
    );
    backdrop-filter: blur(20px);
    border: 1px solid hsl(var(--border) / 0.4);
    border-radius: 2.5rem;
}
</style>
