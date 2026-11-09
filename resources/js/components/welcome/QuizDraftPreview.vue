<script setup lang="ts">
const options = [
    { label: 'A', text: 'x = 2, x = 6', isCorrect: false },
    { label: 'B', text: 'x = 3, x = 4', isCorrect: true },
    { label: 'C', text: 'x = −3, x = −4', isCorrect: false },
    { label: 'D', text: 'x = −2, x = −6', isCorrect: false },
];
</script>

<template>
    <div
        data-testid="quiz-draft-preview"
        role="group"
        aria-label="Preview of quiz item 1 of 15"
        class="mt-2.5 rounded-xl border border-border/70 bg-secondary/30 p-3 dark:border-[#2c2520] dark:bg-[#17120e]"
    >
        <div
            class="flex items-center justify-between gap-3 font-mono text-[9px] tracking-wider text-muted-foreground uppercase dark:text-[#a1968a]"
        >
            <span>Quiz item 01</span>
            <span>Multiple choice</span>
        </div>
        <p class="mt-2 text-xs font-medium text-foreground dark:text-[#f5f0eb]">
            Solve x² − 7x + 12 = 0.
        </p>
        <ul
            aria-label="Answer choices"
            class="mt-2 grid grid-cols-2 gap-1.5 text-[10px]"
        >
            <li
                v-for="option in options"
                :key="option.label"
                :aria-label="
                    option.isCorrect
                        ? `Option ${option.label}, correct answer`
                        : `Option ${option.label}`
                "
                class="flex min-w-0 items-center gap-1.5 rounded-lg border px-2 py-1.5"
                :class="
                    option.isCorrect
                        ? 'border-brand/35 bg-brand/10 text-foreground dark:border-brand/40 dark:bg-brand/10 dark:text-[#f5f0eb]'
                        : 'border-border/70 bg-background/60 text-muted-foreground dark:border-[#2c2520] dark:bg-[#120e0b] dark:text-[#a1968a]'
                "
            >
                <span class="font-mono text-[9px] opacity-70">{{
                    option.label
                }}</span>
                <span class="truncate">{{ option.text }}</span>
            </li>
        </ul>
        <p
            class="mt-2 flex items-center gap-1.5 text-[10px] font-medium text-emerald-700 dark:text-emerald-400"
        >
            <span aria-hidden="true">✓</span>
            Answer key verified
        </p>
    </div>
</template>
