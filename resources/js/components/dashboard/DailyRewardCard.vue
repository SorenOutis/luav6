<script setup lang="ts">
import { ref, watch } from 'vue';
import ClaimXpButton from '@/components/dashboard/ClaimXpButton.vue';

interface ClaimXp {
    canClaim: boolean;
    amount: number;
    nextClaimAt: string | null;
    showPrompt?: boolean;
}

interface Props {
    claimXp?: ClaimXp;
    streak: number;
}

const props = defineProps<Props>();
const emit = defineEmits<{ claimed: [] }>();

const hideClaimCard = ref(false);

// When a new day's claim becomes available, unhide the card
watch(
    () => props.claimXp?.canClaim,
    (canClaim) => {
        if (canClaim) hideClaimCard.value = false;
    },
);

// Notify the dashboard so live totals refresh right away instead of waiting
// for the next poll, while keeping the card visible for a beat so the
// button's celebration (floating +XP, particles, claimed state) can play.
const onClaimed = () => {
    emit('claimed');
    setTimeout(() => {
        hideClaimCard.value = true;
    }, 1400);
};
</script>

<template>
    <section
        v-if="claimXp?.canClaim && !hideClaimCard"
        class="surface-card p-2.5 sm:p-4"
        aria-label="Daily reward"
    >
        <ClaimXpButton
            :can-claim="claimXp.canClaim"
            :amount="claimXp.amount"
            :next-claim-at="claimXp.nextClaimAt"
            :streak="streak"
            :show-prompt="claimXp.showPrompt"
            @claimed="onClaimed"
        />
    </section>
</template>
