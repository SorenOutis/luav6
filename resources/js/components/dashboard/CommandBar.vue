<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { ChevronDown, Megaphone, RefreshCw, X } from 'lucide-vue-next';
import { computed, ref } from 'vue';

import ClaimXpButton from '@/components/dashboard/ClaimXpButton.vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { getInitials } from '@/composables/useInitials';

interface Announcement {
    id: number;
    title: string;
    description?: string;
    link?: string;
    sectionName?: string | null;
    createdAt?: string | null;
}

interface ClaimXp {
    enabled?: boolean;
    canClaim: boolean;
    amount: number;
    baseXp?: number;
    nextClaimAt: string | null;
    showPrompt?: boolean;
}

interface Props {
    userName: string;
    userAvatar?: string;
    profileHref?: string;
    level: number;
    currentXp: number;
    maxXpForLevel: number;
    greeting: string;
    statusLine: string;
    announcements: Announcement[];
    isRefreshing?: boolean;
    claimXp?: ClaimXp;
    streak: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{
    'close-announcement': [id: number];
    refresh: [];
    'open-section-modal': [];
    claimed: [];
    'prompt-open': [];
    'prompt-close': [];
}>();

const initials = computed(() => getInitials(props.userName));

const xpPercent = computed(() => {
    if (!props.maxXpForLevel) return 0;
    return Math.min(
        100,
        Math.round((props.currentXp / props.maxXpForLevel) * 100),
    );
});

// ─── Announcements: one-line pill + expandable list ───────────────────────
const isListOpen = ref(false);
const firstAnnouncement = computed(() => props.announcements[0] ?? null);
const overflowCount = computed(() =>
    Math.max(0, props.announcements.length - 1),
);
const toggleList = () => {
    isListOpen.value = !isListOpen.value;
};
</script>

<template>
    <section
        class="surface-card relative w-full min-w-0 p-3 sm:p-4"
        aria-label="Dashboard overview"
    >
        <div class="flex flex-wrap items-center gap-2.5 sm:gap-3">
            <!-- Identity: avatar + greeting + level chip -->
            <component
                :is="profileHref ? Link : 'div'"
                :href="profileHref"
                class="flex min-w-0 items-center gap-2.5 rounded-xl sm:gap-3"
                :class="profileHref && 'transition-colors hover:bg-muted/40'"
            >
                <Avatar class="h-10 w-10 shrink-0 sm:h-11 sm:w-11">
                    <AvatarImage
                        v-if="userAvatar"
                        :src="userAvatar"
                        :alt="`${userName} avatar`"
                    />
                    <AvatarFallback
                        class="bg-[#D97757]/15 text-sm font-semibold text-[#D97757]"
                    >
                        {{ initials }}
                    </AvatarFallback>
                </Avatar>

                <div class="min-w-0">
                    <h1
                        class="truncate text-[17px] leading-tight font-semibold tracking-tight text-foreground sm:text-xl"
                    >
                        {{ greeting }},
                        <span class="text-[#D97757]">{{ userName }}</span>
                    </h1>
                    <div class="mt-1 flex items-center gap-2">
                        <span
                            class="inline-flex items-center gap-1.5 rounded-full bg-[#D97757]/10 px-2 py-0.5 text-[12px] font-semibold text-[#D97757]"
                            :title="`Level ${level} · ${currentXp.toLocaleString()} / ${maxXpForLevel.toLocaleString()} XP`"
                        >
                            Lv {{ level }}
                            <span
                                class="h-1 w-14 overflow-hidden rounded-full bg-[#D97757]/20 sm:w-20"
                            >
                                <span
                                    class="block h-full rounded-full bg-[#D97757]"
                                    :style="{ width: `${xpPercent}%` }"
                                ></span>
                            </span>
                        </span>
                        <span
                            class="hidden truncate text-[12px] text-muted-foreground md:inline"
                            >{{ statusLine }}</span
                        >
                    </div>
                </div>
            </component>

            <!-- Announcement pill (first item) -->
            <div
                v-if="firstAnnouncement"
                class="order-last flex w-full min-w-0 flex-1 items-center gap-2 rounded-full border border-[#D97757]/25 bg-[#D97757]/[0.07] py-1.5 pr-1.5 pl-3 sm:order-none sm:w-auto"
            >
                <Megaphone
                    class="h-3.5 w-3.5 shrink-0 text-[#D97757]"
                    aria-hidden="true"
                />
                <button
                    type="button"
                    class="min-w-0 flex-1 cursor-pointer truncate text-left text-[13px] font-medium text-foreground"
                    :title="firstAnnouncement.title"
                    @click="toggleList"
                >
                    {{ firstAnnouncement.title }}
                    <span
                        v-if="overflowCount > 0"
                        class="font-semibold text-[#D97757]"
                        >+{{ overflowCount }} more</span
                    >
                </button>
                <button
                    type="button"
                    class="flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    :aria-expanded="isListOpen"
                    aria-label="Toggle announcement list"
                    @click="toggleList"
                >
                    <ChevronDown
                        class="h-3.5 w-3.5 transition-transform duration-200"
                        :class="{ 'rotate-180': isListOpen }"
                    />
                </button>
                <button
                    type="button"
                    class="flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    :aria-label="`Dismiss announcement: ${firstAnnouncement.title}`"
                    @click="emit('close-announcement', firstAnnouncement.id)"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>

            <div class="ml-auto flex items-center gap-2">
                <!-- Daily claim (modal opens from here; auto-prompt sequencing preserved) -->
                <div
                    v-if="claimXp?.canClaim"
                    data-tour="dashboard-daily-reward"
                    class="w-56 shrink-0 max-sm:hidden"
                >
                    <ClaimXpButton
                        :can-claim="claimXp.canClaim"
                        :amount="claimXp.amount"
                        :base-xp="claimXp.baseXp"
                        :next-claim-at="claimXp.nextClaimAt"
                        :streak="streak"
                        :show-prompt="claimXp.showPrompt"
                        @claimed="emit('claimed')"
                        @prompt-open="emit('prompt-open')"
                        @prompt-close="emit('prompt-close')"
                    />
                </div>

                <button
                    type="button"
                    class="flex h-10 w-10 shrink-0 cursor-pointer items-center justify-center rounded-full border border-border/60 bg-card text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Refresh dashboard"
                    :disabled="isRefreshing"
                    @click="emit('refresh')"
                >
                    <RefreshCw
                        class="h-4 w-4"
                        :class="{ 'animate-spin': isRefreshing }"
                    />
                </button>
            </div>
        </div>

        <!-- Expanded announcement list -->
        <div
            v-if="isListOpen"
            class="mt-3 space-y-2 border-t border-border/40 pt-3"
        >
            <p
                class="text-[11px] font-bold tracking-wider text-muted-foreground uppercase"
            >
                Announcements
            </p>
            <div
                v-for="item in announcements"
                :key="item.id"
                class="flex items-start gap-2.5 rounded-xl bg-muted/40 p-3"
            >
                <Megaphone
                    class="mt-0.5 h-4 w-4 shrink-0 text-[#D97757]"
                    aria-hidden="true"
                />
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5">
                        <p class="text-[14px] font-semibold text-foreground">
                            {{ item.title }}
                        </p>
                        <span
                            v-if="item.sectionName"
                            class="rounded-full bg-muted px-2 py-0.5 text-[10px] font-medium text-muted-foreground"
                            >{{ item.sectionName }}</span
                        >
                        <span
                            v-if="item.createdAt"
                            class="text-[11px] text-muted-foreground"
                            >{{ item.createdAt }}</span
                        >
                    </div>
                    <p
                        v-if="item.description"
                        class="mt-0.5 text-[13px] leading-5 text-muted-foreground"
                    >
                        {{ item.description }}
                    </p>
                    <a
                        v-if="item.link"
                        :href="item.link"
                        class="mt-1 inline-block text-[13px] font-semibold text-[#D97757] hover:underline"
                        >Read more</a
                    >
                </div>
                <button
                    type="button"
                    class="flex h-6 w-6 shrink-0 cursor-pointer items-center justify-center rounded-full text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    :aria-label="`Dismiss announcement: ${item.title}`"
                    @click="emit('close-announcement', item.id)"
                >
                    <X class="h-3.5 w-3.5" />
                </button>
            </div>
            <p
                v-if="announcements.length === 0"
                class="text-[13px] text-muted-foreground"
            >
                You're all caught up on announcements.
            </p>
        </div>
    </section>
</template>
