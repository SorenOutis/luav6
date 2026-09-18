import { readFileSync, writeFileSync } from 'fs';

const path = 'resources/js/pages/Exams/Show.vue';
let c = readFileSync(path, 'utf-8');

// 1. Add jumpToNextFlagged function after scrollToQuestion
// First find the end of scrollToQuestion and add the new function after it
const afterScrollToQuestion = `    },
    );
};`;

const jumpToNextFlaggedFn = `    },
    );
};

// Cycle to the next flagged question. If at the last flagged question, wrap to the first.
const jumpToNextFlagged = () => {
    const flagged = Array.from(flaggedQuestions.value).sort((a, b) => a - b);
    if (flagged.length === 0) return;

    // Find the current position among flagged questions, or start from the first
    const currentIdx = flagged.indexOf(visibleQuestionIndex.value);
    const nextIdx = currentIdx >= 0 && currentIdx < flagged.length - 1 ? currentIdx + 1 : 0;
    scrollToQuestion(flagged[nextIdx]);
};`;

if (c.includes(afterScrollToQuestion)) {
    c = c.replace(afterScrollToQuestion, jumpToNextFlaggedFn);
    console.log('1. Added jumpToNextFlagged function');
} else {
    console.log('1. scrollToQuestion end - NOT FOUND');
}

// 2. Update the button click handler and text
const oldButton = `                                    <button
                                        v-if="firstFlaggedIndex >= 0"
                                        @click.prevent="scrollToQuestion(firstFlaggedIndex)"
                                        class="flex w-full items-center justify-center gap-2 border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-[9px] font-black tracking-widest text-amber-500 uppercase transition-all hover:bg-amber-500/20"
                                    >
                                        <Flag
                                            class="h-3 w-3 fill-amber-500/20"
                                        />
                                        Review Flagged
                                    </button>`;

const newButton = `                                    <button
                                        v-if="firstFlaggedIndex >= 0"
                                        @click.prevent="jumpToNextFlagged"
                                        class="flex w-full items-center justify-center gap-2 border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-[9px] font-black tracking-widest text-amber-500 uppercase transition-all hover:bg-amber-500/20"
                                    >
                                        <Flag
                                            class="h-3 w-3 fill-amber-500/20"
                                        />
                                        Next Flagged
                                    </button>`;

if (c.includes(oldButton)) {
    c = c.replace(oldButton, newButton);
    console.log('2. Updated button to use jumpToNextFlagged');
} else {
    console.log('2. Button - NOT FOUND, trying alt version...');
    // Try without extra whitespace
    const altButton = c.indexOf('v-if="firstFlaggedIndex');
    if (altButton >= 0) {
        console.log('Found firstFlaggedIndex at', altButton);
        console.log('Context:', c.substring(altButton - 100, altButton + 300));
    }
}

writeFileSync(path, c, 'utf-8');
console.log('Done');
