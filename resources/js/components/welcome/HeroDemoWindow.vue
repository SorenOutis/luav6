<script setup lang="ts">
import {
    computed,
    nextTick,
    onBeforeUnmount,
    onMounted,
    reactive,
    ref,
    watch,
} from 'vue';
import type { Ref } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';
import { useDemoLoop, useInView } from '@/composables/useDemoLoop';

const props = withDefaults(
    defineProps<{
        prefersReducedMotion?: boolean;
    }>(),
    { prefersReducedMotion: false },
);

/**
 * Scripted vignette, seen from the teacher's console: Maria asks Echo to
 * build a quiz / check the roster / read the scores / export grades → Echo
 * works → Echo types its answer line by line → the teacher confirms and it
 * goes out to the class. The script stays parked behind a boot placeholder
 * until the hero window's entrance animation has finished, so the
 * conversation never plays underneath the fade-in. Runs only while visible;
 * parks on the finished state when motion is reduced.
 */
const STATIC_PHASE = 4;

/** Phase 0 — Echo thinks, greets Maria, then Maria types her request. */
const GREETING_THINK_MS = 700;
const GREETING_TYPE_MS = 750;
const PROMPT_START_MS = 1600;
/** A believable hand on the keyboard, not a text marquee. */
const PROMPT_MS_PER_CHAR = 58;
/** A beat of stillness once Maria has finished typing. */
const PROMPT_TAIL_MS = 300;
/** Her pointer glides onto the Enter key. */
const CURSOR_TRAVEL_MS = 400;
/** The click. */
const PRESS_MS = 280;
/** A long sweep across the window — back to the keyboard, or to the history. */
const POINTER_SWEEP_MS = 700;
/** Phase 1 — the request lands and Echo's result pill confirms it. */
const ANSWERED_MS = 1900;
/** Phase 2 — Echo works; the skeleton holds the bubble. */
const THINKING_MS = 2100;
/** Phase 3 — Echo streams its answer, one line breathing at a time. */
const ECHO_LEAD_MS = 420;
const ECHO_MS_PER_CHAR = 33;
const ECHO_TAIL_MS = 600;
/** Phase 4 — the teacher confirms and it reaches the class. */
const APPROVED_MS = 3200;
/** The pointer glides to the next conversation in the history. */
const SWITCH_TRAVEL_MS = POINTER_SWEEP_MS;
/** The click that opens it. */
const SWITCH_CLICK_MS = 260;
/** Slack so the pointer's click lands before the loop would auto-advance. */
const SWITCH_SLACK_MS = 140;

/** How the highlighted values in Echo's third line read when strung together. */
const RESULT_JOIN = ' · ';

/**
 * Per-character pause weights. Real typing stalls at punctuation and speeds
 * through ordinary letters; a flat tick reads as a scroll instead.
 */
const WEIGHT_CHAR = 1;
const WEIGHT_SPACE = 0.7;
const WEIGHT_GLYPH = 1.4;
const WEIGHT_PAUSE = 2.1;
const WEIGHT_STOP = 3.6;
/** Extra beat when a new line of the reply begins. */
const WEIGHT_LINE = 3;

function charWeight(char: string): number {
    if (/[.!?]/.test(char)) return WEIGHT_STOP;
    if (/[,;:·—–-]/.test(char)) return WEIGHT_PAUSE;
    if (/\s/.test(char)) return WEIGHT_SPACE;
    if (/[a-zA-Z0-9]/.test(char)) return WEIGHT_CHAR;

    return WEIGHT_GLYPH;
}

/** One weight per character, so a weight index maps to a typed character. */
function weigh(text: string): number[] {
    return Array.from(text, charWeight);
}

/** Must match the keyframes name behind `.hero-window-in` in app.css. */
const ENTRANCE_ANIMATION = 'hero-window-in';
/** 500ms animation-delay + 950ms duration from app.css, plus slack. */
const ENTRANCE_FALLBACK_MS = 1600;

interface Scenario {
    id: string;
    topic: string;
    /** Left half of the chat header, e.g. `Draft · 15 items`. */
    contextLabel: string;
    /** Right half of the chat header, e.g. the standard it aligns to. */
    alignment: string;
    /** Last line of the exchange, as the history list shows it. */
    historyPreview: string;
    echoGreeting: string;
    /** What the teacher types into the composer. */
    teacherRequest: string;
    /** Confirmation pill that lands with the request. */
    resultLabel: string;
    resultValue: string;
    echoThinking: string;
    echoSteps: {
        /** The one-line takeaway. */
        bold: string;
        /** The supporting detail. */
        detail: string;
        /** Leads into the highlighted values. */
        resultIntro: string;
        resultValues: string[];
        /** Provenance / what Echo can do next. */
        note: string;
    };
    /** Footer telemetry under the progress rail. */
    footNote: string;
    progressWidth: string;
}

const scenarios: Scenario[] = [
    {
        id: 'quiz-builder',
        topic: 'Quadratics · Quiz Builder',
        contextLabel: 'Draft · 15 items',
        alignment: 'Standards Aligned · Quadratics',
        historyPreview: 'Drafted 15 items across three difficulty tiers.',
        echoGreeting:
            'Hi Maria! Ready to build tomorrow’s quiz? I can draft it from your syllabus.',
        teacherRequest: 'Build me a 15-item quiz on quadratics for tomorrow.',
        resultLabel: 'Drafted in 6s ·',
        resultValue: '15 items ready',
        echoThinking: 'Echo is aligning items to your syllabus',
        echoSteps: {
            bold: 'Drafted 15 items across three difficulty tiers.',
            detail: 'Every item maps directly to quadratic equations.',
            resultIntro: 'Answer key verified — ',
            resultValues: ['14 auto-scored', '1 free response'],
            note: '“Checked against last term’s item bank.”',
        },
        footNote: '28 learners · Section Diamond',
        progressWidth: '86%',
    },
    {
        id: 'roster',
        topic: 'Section Diamond · Roster',
        contextLabel: 'Live · 28 enrolled',
        alignment: 'Today · Term 2',
        historyPreview: '21 of 28 learners opened a task today.',
        echoGreeting:
            'Hi Maria! Want a live look at Section Diamond? I can pull it right now.',
        teacherRequest: 'Who has actually logged in today?',
        resultLabel: 'Checked in 2s ·',
        resultValue: '21 of 28 active',
        echoThinking: 'Echo is reading today’s activity log',
        echoSteps: {
            bold: '21 of 28 learners opened a task today.',
            detail: 'Last activity: Reyes, Aquino, and Limbu, six minutes ago.',
            resultIntro: 'Worth a nudge — ',
            resultValues: ['5 not yet opened', '2 flagged offline'],
            note: '“I can export these names to CSV if you follow up.”',
        },
        footNote: 'Last synced 6 min ago',
        progressWidth: '90%',
    },
    {
        id: 'scores',
        topic: 'Grade 8 · Analytics',
        contextLabel: 'Term 2 · Item analysis',
        alignment: 'Quadratic Equations · 6 at risk',
        historyPreview: '6 of 28 learners sit below 70% on quadratics.',
        echoGreeting:
            'Hi Maria! I’ve finished scoring Term 2 — want the item analysis?',
        teacherRequest: 'Which students are struggling with quadratics?',
        resultLabel: 'Scored in 2s ·',
        resultValue: '6 flagged at risk',
        echoThinking: 'Echo is cross-referencing item scores',
        echoSteps: {
            bold: '6 of 28 learners sit below 70% on quadratics.',
            detail: 'All six missed the same factoring items in Item 3.',
            resultIntro: 'Suggested next step — ',
            resultValues: ['pull small group', 'Friday reteach'],
            note: '“Say the word and I’ll build a 10-item reteach set.”',
        },
        footNote: '28 learners · Term 2 scored',
        progressWidth: '93%',
    },
    {
        id: 'export',
        topic: 'Grade 8 · Reports',
        contextLabel: 'Report · Term 2',
        alignment: 'Official Records · School year 2026',
        historyPreview: 'Compiled 28 learners across your three sections.',
        echoGreeting:
            'Hi Maria! Term 2 reporting is packaged whenever you’re ready for it.',
        teacherRequest: 'Export Grade 8 term grades.',
        resultLabel: 'Packaged in 4s ·',
        resultValue: '3 files ready',
        echoThinking: 'Echo is preparing the grade report',
        echoSteps: {
            bold: 'Compiled 28 learners across your three sections.',
            detail: 'Included item-level scores, standards breakdown, and rubric ratings.',
            resultIntro: 'Attached — ',
            resultValues: ['grades-g8.csv', 'report-g8.pdf'],
            note: '“Add your signature and it is ready to submit.”',
        },
        footNote: '84 learners · all sections',
        progressWidth: '96%',
    },
];

/**
 * Per-scenario budgets, sized from its own copy so every typewriter lands
 * just before its phase flips — no rushed stream, no dead air.
 */
const SCENARIO_TIMINGS = scenarios.map((scenario) => {
    const promptChars = scenario.teacherRequest.length;
    const echoChars =
        scenario.echoSteps.bold.length +
        scenario.echoSteps.detail.length +
        (
            scenario.echoSteps.resultIntro +
            scenario.echoSteps.resultValues.join(RESULT_JOIN)
        ).length +
        scenario.echoSteps.note.length;

    const prompt = promptChars * PROMPT_MS_PER_CHAR;
    const echo = echoChars * ECHO_MS_PER_CHAR;

    return {
        /** Streaming budget for Maria's request, and for Echo's answer. */
        prompt,
        echo,
        phases: [
            PROMPT_START_MS +
                prompt +
                PROMPT_TAIL_MS +
                CURSOR_TRAVEL_MS +
                PRESS_MS,
            ANSWERED_MS,
            THINKING_MS,
            ECHO_LEAD_MS + echo + ECHO_TAIL_MS,
            APPROVED_MS + SWITCH_TRAVEL_MS + SWITCH_CLICK_MS + SWITCH_SLACK_MS,
        ],
    };
});

const root = ref<HTMLElement | null>(null);
const inView = useInView(root);

/**
 * Reads `cycle` lazily — `useDemoLoop` only resolves durations at the moment
 * it schedules the next phase, well after this is wired up.
 */
const phaseDurations = computed(() => {
    return SCENARIO_TIMINGS[cycle.value % SCENARIO_TIMINGS.length].phases;
});

const { phase, cycle, start, stop, set } = useDemoLoop(phaseDurations);

const enabled = computed(() => !props.prefersReducedMotion);

/**
 * Flips once the hero window entrance animation has settled. The scripted
 * conversation (and its timers) stay parked until then.
 */
const entranceDone = ref(false);
let entranceTimer: ReturnType<typeof setTimeout> | undefined;

/** Reduced motion parks on the finished state, so nothing needs to boot. */
const booted = computed(() => !enabled.value || entranceDone.value);

const activeScenarioIndex = computed(() => {
    return enabled.value ? cycle.value % scenarios.length : 0;
});

const activeScenario = computed(() => {
    return scenarios[activeScenarioIndex.value];
});

const answered = computed(() => phase.value >= 1);
const drafting = computed(() => phase.value === 2);
const draftReady = computed(() => phase.value >= 3);
const confirmed = computed(() => phase.value >= 4);

/**
 * The working skeleton keeps holding the bubble until Echo's first character
 * actually lands — otherwise the swap renders an empty reply box for a beat.
 */
const draftingSkeleton = computed(() => {
    if (!enabled.value) return false;

    return drafting.value || (phase.value === 3 && typedEchoChars.value === 0);
});

const draftVisible = computed(() => {
    if (!enabled.value) return draftReady.value;

    return phase.value >= 3 && typedEchoChars.value > 0;
});

const greetingReady = ref(false);

/**
 * Bumped every time the teacher picks a conversation by hand. The script has
 * to restart from its first frame even when the phase number does not change,
 * so the watcher keys off this instead of the phase alone.
 */
const replay = ref(0);

const orbState = computed<'thinking' | 'micro'>(() => {
    if (enabled.value && phase.value === 0 && !greetingReady.value) {
        return 'thinking';
    }
    return drafting.value ? 'thinking' : 'micro';
});

const badges = computed(() => [
    {
        label: 'Awaiting request',
        class: 'border border-border/80 bg-secondary/80 text-foreground/80 dark:border-[#2c2520] dark:bg-[#221b15] dark:text-[#a1968a]',
    },
    {
        label: 'Request sent',
        class: 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-400',
    },
    {
        label: 'Echo working',
        class: 'border border-brand/30 bg-brand/15 text-brand dark:border-brand/30 dark:bg-brand/15 dark:text-brand',
    },
    {
        label: 'Needs your OK',
        class: 'border border-amber-500/30 bg-amber-500/15 text-amber-800 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-300',
    },
    {
        label: 'Sent to class',
        class: 'border border-emerald-500/30 bg-emerald-500/15 text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-400',
    },
]);

// ── Typewriter Letter-by-Letter Streaming Logic ──
const typedGreetingChars = ref(0);
const typedPromptChars = ref(0);
const typedEchoChars = ref(0);
let cancelGreetingStream: (() => void) | undefined;
let cancelPromptStream: (() => void) | undefined;
let cancelEchoStream: (() => void) | undefined;
let initialDelayTimer: ReturnType<typeof setTimeout> | undefined;
let greetingTimer: ReturnType<typeof setTimeout> | undefined;

/**
 * True in the tail of phase 0, once Maria has finished typing — the beat
 * where her thumb lands on Enter and the request leaves the composer.
 */
const pressing = ref(false);

/** Maria has typed her request in full. */
const promptComplete = computed(() => {
    return (
        typedPromptChars.value > 0 &&
        typedPromptChars.value >= activeScenario.value.teacherRequest.length
    );
});

/** Echo is mid-turn: thinking, or working through the request. */
const echoWorking = computed(() => {
    if (!enabled.value) return false;
    if (phase.value === 0) return !greetingReady.value;

    return phase.value === 2;
});

/**
 * What the history list shows for the open conversation. It mirrors the live
 * exchange so the sidebar keeps moving with the chat instead of sitting still
 * while the demo runs.
 */
const activePreview = computed(() => {
    const scenario = activeScenario.value;

    if (!enabled.value) {
        return scenario.historyPreview;
    }

    if (echoWorking.value) {
        return 'Echo is typing…';
    }

    if (phase.value === 0) {
        if (typedPromptChars.value > 0) {
            return `You: ${effectiveTypedPrompt.value}`;
        }

        return scenario.echoGreeting;
    }

    if (phase.value === 1) {
        return scenario.resultValue;
    }

    if (phase.value === 3) {
        return displayLine1.value || 'Echo is writing…';
    }

    if (phase.value === 4) {
        return 'Sent to class ✓';
    }

    return scenario.historyPreview;
});

watch(promptComplete, (complete) => {
    if (!complete || phase.value !== 0) return;

    cursorTimer = setTimeout(beginCursorApproach, PROMPT_TAIL_MS);
});

/**
 * Maria's pointer. It flies in from the open chat area once her request is
 * typed, lands on the Enter key, and clicks it.
 */
const cursor = reactive({
    visible: false,
    pressing: false,
    /** Set while the pointer is dropped at a point, so it does not glide. */
    instant: false,
    /** How long the current glide takes, in ms. */
    travel: CURSOR_TRAVEL_MS,
    x: 0,
    y: 0,
});

/** The conversation the pointer is reaching for. */
const pointedIndex = ref<number | null>(null);

const sendButtonRef = ref<HTMLElement | null>(null);
const conversationNavRef = ref<HTMLElement | null>(null);
/** The pointer has been dropped into the window at least once. */
let cursorPlaced = false;
let cursorTimer: ReturnType<typeof setTimeout> | undefined;
let pressTimer: ReturnType<typeof setTimeout> | undefined;
let switchTimer: ReturnType<typeof setTimeout> | undefined;
let switchFireTimer: ReturnType<typeof setTimeout> | undefined;

/** Where the Enter key sits, in the demo window's own coordinates. */
function enterKeyPoint(): { x: number; y: number } {
    const frame = root.value?.getBoundingClientRect();
    const key = sendButtonRef.value?.getBoundingClientRect();

    if (!frame || !key || key.width === 0) {
        return { x: 0, y: 0 };
    }

    return {
        x: key.left - frame.left + key.width / 2,
        y: key.top - frame.top + key.height / 2,
    };
}

/**
 * The pointer's resting place: the hand waiting next to the keyboard, just
 * above and to the right of the Enter key.
 */
function restingPoint(): { x: number; y: number } {
    const key = enterKeyPoint();

    return { x: key.x + 92, y: key.y - 104 };
}

/** Where a conversation in the history sits, in the window's coordinates. */
function conversationPoint(index: number): { x: number; y: number } {
    const frame = root.value?.getBoundingClientRect();
    const row = conversationNavRef.value?.querySelector<HTMLElement>(
        `[data-conversation="${index}"]`,
    );

    if (!frame || !row) {
        return { x: 0, y: 0 };
    }

    const rect = row.getBoundingClientRect();

    return {
        x: rect.left - frame.left + 40,
        y: rect.top - frame.top + rect.height / 2,
    };
}

/** Sends the pointer to a point — the transition does the gliding. */
function glideTo(target: { x: number; y: number }) {
    cursor.x = target.x;
    cursor.y = target.y;
}

/** Drops the pointer at a point without gliding there. */
function placeAt(target: { x: number; y: number }) {
    cursor.instant = true;
    glideTo(target);

    nextTick(() => {
        cursor.instant = false;
    });
}

/** The pointer settles on the Enter key and hits it. */
function beginCursorApproach() {
    cursor.travel = CURSOR_TRAVEL_MS;
    cursor.pressing = false;
    cursor.visible = true;
    glideTo(enterKeyPoint());

    pressTimer = setTimeout(() => {
        cursor.pressing = true;
        pressing.value = true;
    }, CURSOR_TRAVEL_MS);
}

/** Fades the pointer out. */
function hideCursor() {
    cursor.visible = false;
    cursor.pressing = false;
    pressing.value = false;
}

/** Clears the press state, ready for the next run. */
function resetCursor() {
    cursor.pressing = false;
    pressing.value = false;
    pointedIndex.value = null;
}

/**
 * Maria moves on: the pointer leaves the Enter key, crosses the window to
 * the next conversation in the history and opens it. The demo never jumps
 * between threads on its own.
 */
function beginConversationSwitch() {
    const current = activeScenarioIndex.value;
    const next = (current + 1) % scenarios.length;

    cursor.travel = SWITCH_TRAVEL_MS;
    cursor.pressing = false;
    cursor.visible = true;
    pointedIndex.value = next;
    glideTo(conversationPoint(next));

    // The click lands when the pointer reaches the row.
    pressTimer = setTimeout(() => {
        cursor.pressing = true;
    }, SWITCH_TRAVEL_MS);

    switchFireTimer = setTimeout(() => {
        selectScenario(next);
    }, SWITCH_TRAVEL_MS + SWITCH_CLICK_MS);
}

/**
 * Reveals `counter` one character per step, spreading the work across
 * `budgetMs` and weighting each pause by punctuation, so the stream always
 * finishes exactly when its phase ends. Returns a cancel handle.
 */
function startTypewriter(
    weights: number[],
    counter: Ref<number>,
    budgetMs: number,
): () => void {
    const totalWeight = weights.reduce((sum, weight) => sum + weight, 0);
    let timer: ReturnType<typeof setTimeout> | undefined;
    let index = counter.value;

    const step = () => {
        if (index >= weights.length) {
            return;
        }

        counter.value = index + 1;
        index += 1;
        timer = setTimeout(step, (weights[index - 1] / totalWeight) * budgetMs);
    };

    timer = setTimeout(step, 0);

    return () => {
        if (timer !== undefined) {
            clearTimeout(timer);
            timer = undefined;
        }
    };
}

function clearTypingTimers() {
    if (greetingTimer !== undefined) {
        clearTimeout(greetingTimer);
        greetingTimer = undefined;
    }
    if (initialDelayTimer !== undefined) {
        clearTimeout(initialDelayTimer);
        initialDelayTimer = undefined;
    }
    cancelGreetingStream?.();
    cancelGreetingStream = undefined;
    cancelPromptStream?.();
    cancelPromptStream = undefined;
    cancelEchoStream?.();
    cancelEchoStream = undefined;
    if (cursorTimer !== undefined) {
        clearTimeout(cursorTimer);
        cursorTimer = undefined;
    }
    if (pressTimer !== undefined) {
        clearTimeout(pressTimer);
        pressTimer = undefined;
    }
    if (switchTimer !== undefined) {
        clearTimeout(switchTimer);
        switchTimer = undefined;
    }
    if (switchFireTimer !== undefined) {
        clearTimeout(switchFireTimer);
        switchFireTimer = undefined;
    }
}

function clearEntranceTimer() {
    if (entranceTimer !== undefined) {
        clearTimeout(entranceTimer);
        entranceTimer = undefined;
    }
}

/** Releases the script once the hero window has finished animating in. */
function releaseEntrance() {
    clearEntranceTimer();
    entranceDone.value = true;
    nextTick(() => {
        scrollToTop(false);
    });
}

/**
 * Only the window's own entrance counts — the nested badge/dot animations
 * bubble to this element too, and reduced motion never fires at all.
 */
function onEntranceEnd(event: AnimationEvent) {
    if (
        event.target !== root.value ||
        event.animationName !== ENTRANCE_ANIMATION
    ) {
        return;
    }

    releaseEntrance();
}

onMounted(() => {
    nextTick(() => {
        scrollToTop(false);
    });
    // Extra safety against browser scroll restoration on refresh
    setTimeout(() => {
        if (phase.value === 0) {
            scrollToTop(false);
        }
    }, 60);
    // Safety net: if the entrance animation never reports back (CSS disabled,
    // background tab throttling) the script still boots on a fixed delay.
    entranceTimer = setTimeout(releaseEntrance, ENTRANCE_FALLBACK_MS);
});

onBeforeUnmount(() => {
    clearTypingTimers();
    clearEntranceTimer();
});

const greetingWeights = computed(() =>
    weigh(activeScenario.value.echoGreeting),
);

const displayGreeting = computed(() => {
    if (!enabled.value || phase.value >= 1) {
        return activeScenario.value.echoGreeting;
    }
    return activeScenario.value.echoGreeting.slice(0, typedGreetingChars.value);
});

const isGreetingTyping = computed(() => {
    return (
        enabled.value &&
        phase.value === 0 &&
        greetingReady.value &&
        typedGreetingChars.value < activeScenario.value.echoGreeting.length
    );
});

const effectiveTypedPrompt = computed(() => {
    if (!enabled.value || phase.value >= 1) {
        return activeScenario.value.teacherRequest;
    }
    return activeScenario.value.teacherRequest.slice(0, typedPromptChars.value);
});

const isPromptTyping = computed(() => {
    return (
        enabled.value &&
        phase.value === 0 &&
        typedPromptChars.value > 0 &&
        typedPromptChars.value < activeScenario.value.teacherRequest.length
    );
});

const line1Text = computed(() => activeScenario.value.echoSteps.bold);
const line2Text = computed(() => activeScenario.value.echoSteps.detail);
const line3FullText = computed(() => {
    const s = activeScenario.value.echoSteps;
    return s.resultIntro + s.resultValues.join(RESULT_JOIN);
});
const line4Text = computed(() => activeScenario.value.echoSteps.note);

const totalEchoChars = computed(() => {
    return (
        line1Text.value.length +
        line2Text.value.length +
        line3FullText.value.length +
        line4Text.value.length
    );
});

/**
 * One weight per character of the reply, in the same order the lines are
 * sliced out of `currentEchoChars`. Each new line's first character carries
 * an extra beat so the caret visibly hops between lines.
 */
const echoWeights = computed(() => {
    const lines = [
        line1Text.value,
        line2Text.value,
        line3FullText.value,
        line4Text.value,
    ];

    return lines.flatMap((line, lineIndex) =>
        Array.from(line, (char, charIndex) =>
            lineIndex > 0 && charIndex === 0
                ? charWeight(char) + WEIGHT_LINE
                : charWeight(char),
        ),
    );
});

const promptWeights = computed(() => {
    return weigh(activeScenario.value.teacherRequest);
});

const currentEchoChars = computed(() => {
    if (!enabled.value || phase.value >= 4) {
        return totalEchoChars.value;
    }
    if (phase.value < 3) {
        return 0;
    }
    return typedEchoChars.value;
});

const isEchoTyping = computed(() => {
    return (
        enabled.value &&
        phase.value === 3 &&
        currentEchoChars.value < totalEchoChars.value
    );
});

// Line 1 calculations
const line1Chars = computed(() => {
    return Math.min(currentEchoChars.value, line1Text.value.length);
});
const displayLine1 = computed(() => {
    return line1Text.value.slice(0, line1Chars.value);
});
const isLine1Active = computed(() => {
    return (
        isEchoTyping.value && currentEchoChars.value <= line1Text.value.length
    );
});

// Line 2 calculations
const line2Chars = computed(() => {
    const prev = line1Text.value.length;
    if (currentEchoChars.value <= prev) {
        return 0;
    }
    return Math.min(currentEchoChars.value - prev, line2Text.value.length);
});
const displayLine2 = computed(() => {
    return line2Text.value.slice(0, line2Chars.value);
});
const isLine2Active = computed(() => {
    const prev = line1Text.value.length;
    return (
        isEchoTyping.value &&
        currentEchoChars.value > prev &&
        currentEchoChars.value <= prev + line2Text.value.length
    );
});

// Line 3 calculations
const line3Chars = computed(() => {
    const prev = line1Text.value.length + line2Text.value.length;
    if (currentEchoChars.value <= prev) {
        return 0;
    }
    return Math.min(currentEchoChars.value - prev, line3FullText.value.length);
});
const displayLine3 = computed(() => {
    return line3FullText.value.slice(0, line3Chars.value);
});
const isLine3Active = computed(() => {
    const prev = line1Text.value.length + line2Text.value.length;
    return (
        isEchoTyping.value &&
        currentEchoChars.value > prev &&
        currentEchoChars.value <= prev + line3FullText.value.length
    );
});

const line3Complete = computed(() => {
    return (
        line3Chars.value >= line3FullText.value.length ||
        phase.value >= 4 ||
        !enabled.value
    );
});

// Line 4 calculations
const line4Chars = computed(() => {
    const prev =
        line1Text.value.length +
        line2Text.value.length +
        line3FullText.value.length;
    if (currentEchoChars.value <= prev) {
        return 0;
    }
    return Math.min(currentEchoChars.value - prev, line4Text.value.length);
});
const displayLine4 = computed(() => {
    return line4Text.value.slice(0, line4Chars.value);
});
const isLine4Active = computed(() => {
    const prev =
        line1Text.value.length +
        line2Text.value.length +
        line3FullText.value.length;
    return isEchoTyping.value && currentEchoChars.value > prev;
});

watch(
    [phase, replay, enabled, booted],
    ([newPhase, , isEnabled, isBooted]) => {
        clearTypingTimers();
        resetCursor();

        if (!isEnabled) {
            hideCursor();
            greetingReady.value = true;
            typedGreetingChars.value = activeScenario.value.echoGreeting.length;
            typedPromptChars.value = activeScenario.value.teacherRequest.length;
            typedEchoChars.value = totalEchoChars.value;
            return;
        }

        // Still animating in — hold every timer until the script boots.
        if (!isBooted) {
            hideCursor();
            greetingReady.value = false;
            typedGreetingChars.value = 0;
            typedPromptChars.value = 0;
            typedEchoChars.value = 0;
            return;
        }

        if (newPhase === 0) {
            greetingReady.value = false;
            typedGreetingChars.value = 0;
            typedPromptChars.value = 0;
            typedEchoChars.value = 0;

            // The pointer goes back to the keyboard, ready to send the
            // request. It stays on screen from here on — the demo is one
            // continuous hand.
            cursor.travel = POINTER_SWEEP_MS;
            cursor.visible = true;

            if (cursorPlaced) {
                glideTo(restingPoint());
            } else {
                cursorPlaced = true;
                placeAt(restingPoint());
            }

            // 1. AI thinking briefly, then types its greeting to Maria
            greetingTimer = setTimeout(() => {
                greetingReady.value = true;
                cancelGreetingStream = startTypewriter(
                    greetingWeights.value,
                    typedGreetingChars,
                    GREETING_TYPE_MS,
                );
            }, GREETING_THINK_MS);

            // 2. Maria begins typing after Echo has greeted
            initialDelayTimer = setTimeout(() => {
                cancelPromptStream = startTypewriter(
                    promptWeights.value,
                    typedPromptChars,
                    SCENARIO_TIMINGS[activeScenarioIndex.value].prompt,
                );
            }, PROMPT_START_MS);

            // 3. Her pointer flies in and hits Enter once the request is done
        } else if (newPhase === 1 || newPhase === 2) {
            greetingReady.value = true;
            typedGreetingChars.value = activeScenario.value.echoGreeting.length;
            typedPromptChars.value = activeScenario.value.teacherRequest.length;
            typedEchoChars.value = 0;
        } else if (newPhase === 3) {
            greetingReady.value = true;
            typedGreetingChars.value = activeScenario.value.echoGreeting.length;
            typedPromptChars.value = activeScenario.value.teacherRequest.length;
            typedEchoChars.value = 0;

            // Echo holds the skeleton for a beat, then streams its reply.
            initialDelayTimer = setTimeout(() => {
                cancelEchoStream = startTypewriter(
                    echoWeights.value,
                    typedEchoChars,
                    SCENARIO_TIMINGS[activeScenarioIndex.value].echo,
                );
            }, ECHO_LEAD_MS);
        } else if (newPhase >= 4) {
            greetingReady.value = true;
            typedGreetingChars.value = activeScenario.value.echoGreeting.length;
            typedPromptChars.value = activeScenario.value.teacherRequest.length;
            typedEchoChars.value = totalEchoChars.value;

            // 4. Maria moves on: the pointer crosses to the next thread
            switchTimer = setTimeout(beginConversationSwitch, APPROVED_MS);
        }
    },
    { immediate: true },
);

watch(
    [inView, enabled, entranceDone],
    ([visible, on, entered]) => {
        if (!on) {
            stop();
            set(STATIC_PHASE);
            return;
        }

        // Still animating in — hold the script at its first frame.
        if (!entered) {
            stop();
            return;
        }

        if (visible) {
            start();
        } else {
            stop();
        }
    },
    { immediate: true },
);

const chatFeedRef = ref<HTMLElement | null>(null);

function scrollToTop(smooth = false) {
    if (!chatFeedRef.value) return;
    if (typeof chatFeedRef.value.scrollTo === 'function') {
        chatFeedRef.value.scrollTo({
            top: 0,
            behavior: smooth && enabled.value ? 'smooth' : 'auto',
        });
    } else {
        chatFeedRef.value.scrollTop = 0;
    }
}

function scrollToBottom(smooth = true) {
    if (!chatFeedRef.value) return;
    if (typeof chatFeedRef.value.scrollTo === 'function') {
        chatFeedRef.value.scrollTo({
            top: chatFeedRef.value.scrollHeight,
            behavior: smooth && enabled.value ? 'smooth' : 'auto',
        });
    } else {
        chatFeedRef.value.scrollTop = chatFeedRef.value.scrollHeight;
    }
}

/**
 * Switches the demo to another conversation and replays it from the first
 * frame: Echo thinks, greets Maria, then Maria types her request. The loop is
 * rescheduled by `set`, so the new scenario gets its own full opening beat
 * instead of inheriting the leftover of the phase it replaced.
 */
function selectScenario(index: number) {
    if (!enabled.value) return;
    cycle.value = index;
    replay.value += 1;
    set(0);
    nextTick(() => {
        scrollToTop(false);
    });
}

function handleSendPrompt() {
    if (!enabled.value) return;
    if (phase.value === 0) {
        clearTypingTimers();
        set(1);
    }
}

function handleConfirmSend() {
    if (!enabled.value) return;
    if (phase.value === 3) {
        set(4);
    }
}

watch(phase, (newPhase) => {
    nextTick(() => {
        if (newPhase === 0) {
            scrollToTop(true);
        } else {
            scrollToBottom(true);
        }
    });
});

watch(cycle, () => {
    nextTick(() => {
        scrollToTop(false);
    });
});

watch(
    () => [
        line1Chars.value,
        line2Chars.value,
        line3Chars.value,
        line4Chars.value,
    ],
    () => {
        if (phase.value === 3) {
            scrollToBottom(true);
        }
    },
);
</script>

<template>
    <div
        id="echo-demo"
        ref="root"
        class="hero-window-in relative w-full scroll-mt-32"
        :data-phase="phase"
        @animationend="onEntranceEnd"
        aria-label="Live preview: a teacher asks Echo to build a quiz, check the roster, read the scores, or export grades, then confirms it goes to the class"
    >
        <div
            class="overflow-hidden rounded-2xl border border-border/80 bg-card text-foreground shadow-[0_24px_70px_rgba(40,30,20,0.08),0_2px_8px_rgba(40,30,20,0.04)] sm:rounded-3xl dark:border-[#2c2520] dark:bg-[#14100d] dark:text-[#f5f0eb] dark:shadow-[0_24px_70px_rgba(0,0,0,0.45),0_2px_8px_rgba(0,0,0,0.3)]"
        >
            <!-- Window chrome (macOS style with classic traffic lights) -->
            <div
                class="flex items-center justify-between gap-3 border-b border-border/80 bg-secondary/80 px-4 py-3 sm:px-6 dark:border-[#2c2520] dark:bg-[#1a1410]"
            >
                <div class="flex items-center gap-3">
                    <!-- Classic macOS traffic lights -->
                    <span class="flex items-center gap-2" aria-hidden="true">
                        <span
                            class="h-3 w-3 rounded-full border border-[#E0443E]/60 bg-[#FF5F56] shadow-xs"
                        ></span>
                        <span
                            class="h-3 w-3 rounded-full border border-[#DEA123]/60 bg-[#FFBD2E] shadow-xs"
                        ></span>
                        <span
                            class="h-3 w-3 rounded-full border border-[#1AAB29]/60 bg-[#27C93F] shadow-xs"
                        ></span>
                    </span>
                    <div
                        class="hidden items-center gap-2 border-l border-border/80 pl-3 text-xs text-muted-foreground sm:flex dark:border-[#2c2520] dark:text-[#a1968a]"
                    >
                        <span
                            class="h-1.5 w-1.5 rounded-full bg-emerald-500"
                        ></span>
                        <span
                            class="font-medium text-foreground dark:text-[#f5f0eb]"
                            >LSI ·
                            {{ activeScenario.topic.split(' · ')[0] }}</span
                        >
                        <span
                            class="text-muted-foreground/60 dark:text-[#72695f]"
                            >/</span
                        >
                        <span
                            class="font-mono text-[11px] text-muted-foreground dark:text-[#a1968a]"
                            >Echo 2.5 Active</span
                        >
                    </div>
                </div>

                <!-- Status badge swap -->
                <div class="hdw-swap justify-items-end">
                    <span
                        v-for="(badge, index) in badges"
                        :key="badge.label"
                        class="hdw-badge inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[11px] font-medium whitespace-nowrap sm:text-xs"
                        :class="[badge.class, { 'is-on': phase === index }]"
                    >
                        <span
                            v-if="index === 2"
                            class="h-2.5 w-2.5 shrink-0 animate-spin rounded-full border-[1.5px] border-current border-t-transparent"
                            aria-hidden="true"
                        ></span>
                        <span
                            v-else
                            class="h-1.5 w-1.5 shrink-0 rounded-full bg-current"
                            aria-hidden="true"
                        ></span>
                        {{ badge.label }}
                    </span>
                </div>
            </div>

            <!-- Body split: Sidebar + Main Chat Canvas -->
            <div
                class="relative flex min-h-[480px] flex-col sm:min-h-[520px] md:h-[540px] md:flex-row"
            >
                <!-- Boot placeholder: the scripted chat only starts once the hero entrance settles -->
                <div
                    v-if="!booted"
                    class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2.5 bg-card px-6 dark:bg-[#14100d]"
                    aria-hidden="true"
                >
                    <div
                        class="h-2.5 w-44 animate-pulse rounded-full bg-muted/80 dark:bg-[#2c2520]"
                    ></div>
                    <div
                        class="h-2.5 w-28 animate-pulse rounded-full bg-muted/60 dark:bg-[#221b15]"
                    ></div>
                    <span
                        class="mt-2 font-mono text-[10px] tracking-wider text-muted-foreground uppercase dark:text-[#a1968a]"
                    >
                        Loading preview
                    </span>
                </div>

                <!-- Sidebar: previous conversations -->
                <aside
                    class="hdw-stage hidden w-full shrink-0 flex-col justify-between border-b border-border/80 bg-[#f0ebe1]/90 p-4 text-xs sm:flex md:w-56 md:border-r md:border-b-0 dark:border-[#2c2520] dark:bg-[#17120e]"
                    :class="{ 'is-hidden': !booted }"
                >
                    <div class="space-y-4">
                        <div class="flex items-center justify-between">
                            <span
                                class="font-mono text-[10px] font-semibold tracking-wider text-muted-foreground uppercase dark:text-[#a1968a]"
                            >
                                Conversations
                            </span>
                            <span
                                class="font-mono text-[10px] text-muted-foreground dark:text-[#a1968a]"
                                >{{ scenarios.length + 1 }}</span
                            >
                        </div>
                        <nav
                            ref="conversationNavRef"
                            class="space-y-1.5"
                            aria-label="Demo conversations"
                        >
                            <button
                                v-for="(conv, i) in scenarios"
                                :key="conv.id"
                                :data-conversation="i"
                                type="button"
                                class="flex w-full cursor-pointer items-start gap-2 rounded-lg px-3 py-2 text-left transition-all duration-300"
                                :class="[
                                    activeScenarioIndex === i
                                        ? 'border border-border/80 bg-card shadow-xs dark:border-[#2c2520] dark:bg-[#221b15] dark:shadow-none'
                                        : 'text-muted-foreground hover:bg-secondary/50 hover:text-foreground dark:text-[#a1968a] dark:hover:bg-[#1f1914] dark:hover:text-[#f5f0eb]',
                                    pointedIndex === i &&
                                    activeScenarioIndex !== i
                                        ? 'bg-secondary/50 text-foreground dark:bg-[#1f1914] dark:text-[#f5f0eb]'
                                        : '',
                                ]"
                                @click="selectScenario(i)"
                            >
                                <span class="min-w-0 flex-1">
                                    <span
                                        class="flex items-center gap-1.5 text-xs"
                                    >
                                        <span
                                            class="min-w-0 truncate font-medium"
                                            :class="
                                                activeScenarioIndex === i
                                                    ? 'text-foreground dark:text-[#f5f0eb]'
                                                    : ''
                                            "
                                            >{{ conv.topic }}</span
                                        >
                                        <!-- Echo is mid-turn in the open thread. -->
                                        <span
                                            v-if="
                                                activeScenarioIndex === i &&
                                                echoWorking
                                            "
                                            class="flex shrink-0 items-center gap-0.5"
                                            aria-hidden="true"
                                        >
                                            <span
                                                v-for="dot in 3"
                                                :key="dot"
                                                class="demo-dot h-1 w-1 rounded-full bg-brand"
                                                :style="{
                                                    '--dot-index': String(
                                                        dot - 1,
                                                    ),
                                                }"
                                            ></span>
                                        </span>
                                        <span
                                            v-else-if="
                                                activeScenarioIndex === i
                                            "
                                            class="h-1.5 w-1.5 shrink-0 animate-pulse rounded-full bg-emerald-500"
                                            aria-hidden="true"
                                        ></span>
                                    </span>
                                    <!-- The stacked sidebar below md has no room
                                         for a second line. -->
                                    <span
                                        class="mt-0.5 hidden truncate text-[11px] text-muted-foreground/80 md:block dark:text-[#7d736a]"
                                    >
                                        {{
                                            activeScenarioIndex === i
                                                ? activePreview
                                                : conv.historyPreview
                                        }}
                                    </span>
                                </span>
                            </button>
                            <div
                                class="flex items-center gap-2 rounded-lg px-3 py-2 text-xs text-muted-foreground hover:text-foreground dark:text-[#a1968a] dark:hover:text-[#f5f0eb]"
                            >
                                <span class="truncate">Review Queue</span>
                                <span
                                    class="py-0.2 ml-auto rounded-full bg-amber-500/20 px-1.5 font-mono text-[9px] text-amber-800 dark:text-amber-300"
                                    >1</span
                                >
                            </div>
                        </nav>
                    </div>

                    <div
                        class="space-y-1 border-t border-border/80 pt-3 text-[11px] text-muted-foreground dark:border-[#2c2520] dark:text-[#a1968a]"
                    >
                        <p
                            class="font-medium text-foreground dark:text-[#f5f0eb]"
                        >
                            Maria Santos · Teacher
                        </p>
                        <p
                            class="font-mono text-[10px] text-muted-foreground dark:text-[#72695f]"
                        >
                            Grade 8 · 3 sections · 84 learners
                        </p>
                    </div>
                </aside>

                <!-- Main Chat Canvas -->
                <div
                    class="hdw-stage flex flex-1 flex-col justify-between overflow-hidden bg-card p-4 text-left sm:p-6 dark:bg-[#120e0b]"
                    :class="{ 'is-hidden': !booted }"
                >
                    <!-- Top item metadata bar -->
                    <div
                        class="flex shrink-0 items-center justify-between border-b border-border/80 pb-3 text-xs dark:border-[#2c2520]"
                    >
                        <div class="flex items-center gap-2">
                            <span
                                class="font-mono text-[11px] font-medium text-brand uppercase"
                                >{{ activeScenario.contextLabel }}</span
                            >
                            <span
                                class="text-muted-foreground/60 dark:text-[#72695f]"
                                >·</span
                            >
                            <span
                                class="font-medium text-foreground dark:text-[#f5f0eb]"
                                >{{
                                    activeScenario.topic.split(' · ')[1] ||
                                    activeScenario.topic
                                }}</span
                            >
                        </div>
                        <span
                            class="font-mono text-[11px] text-muted-foreground dark:text-[#a1968a]"
                            >{{ activeScenario.alignment }}</span
                        >
                    </div>

                    <!-- Chat Messages Scroll Container -->
                    <div
                        ref="chatFeedRef"
                        :key="cycle"
                        class="chat-feed-scroll flex-1 space-y-4 overflow-y-auto overscroll-contain scroll-smooth py-3 pr-1 sm:space-y-5"
                    >
                        <!-- Turn 1: Echo Greeting (Starts with brief thinking, then greets Maria) -->
                        <div
                            class="flex items-start gap-3 transition-opacity duration-300"
                        >
                            <div
                                class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-brand/20 bg-brand/15 text-base leading-none shadow-xs"
                            >
                                <ChatAiOrb
                                    size="inline"
                                    variant="outline"
                                    :state="orbState"
                                    color="var(--color-brand)"
                                />
                            </div>

                            <div
                                class="w-full max-w-xl space-y-1.5 sm:max-w-2xl"
                            >
                                <div class="flex items-center gap-2">
                                    <span class="text-xs font-medium text-brand"
                                        >Echo</span
                                    >
                                    <span
                                        class="font-mono text-[10px] text-muted-foreground dark:text-[#a1968a]"
                                        >Teaching Copilot</span
                                    >
                                    <span
                                        class="text-[10px] text-muted-foreground/50 dark:text-[#524b44]"
                                        >·</span
                                    >
                                    <span
                                        class="font-mono text-[10px] text-muted-foreground/60 dark:text-[#72695f]"
                                        >Just now</span
                                    >
                                </div>

                                <!-- Greeting Bubble -->
                                <div
                                    class="rounded-2xl rounded-tl-xs border border-border/80 bg-white px-4 py-3 text-sm text-foreground shadow-xs transition-all duration-300 dark:border-[#2c2520] dark:bg-[#1a1410] dark:text-[#f5f0eb]"
                                >
                                    <div
                                        v-if="phase === 0 && !greetingReady"
                                        class="flex items-center gap-2 py-0.5 text-xs font-medium text-brand"
                                    >
                                        <span class="sr-only"
                                            >Echo is thinking</span
                                        >
                                        <span
                                            aria-hidden="true"
                                            class="flex items-center gap-1.5"
                                        >
                                            <span class="font-semibold"
                                                >Thinking...</span
                                            >
                                            <span
                                                class="inline-flex items-center gap-1 py-0.5"
                                            >
                                                <span
                                                    v-for="dot in 3"
                                                    :key="dot"
                                                    class="demo-dot h-1.5 w-1.5 rounded-full bg-brand"
                                                    :style="{
                                                        '--dot-index': String(
                                                            dot - 1,
                                                        ),
                                                    }"
                                                ></span>
                                            </span>
                                        </span>
                                    </div>
                                    <p
                                        v-else
                                        class="text-sm leading-relaxed text-foreground dark:text-[#f5f0eb]"
                                    >
                                        <span class="sr-only">{{
                                            activeScenario.echoGreeting
                                        }}</span>
                                        <span aria-hidden="true">
                                            <span data-testid="greeting-text">{{
                                                displayGreeting
                                            }}</span>
                                            <span
                                                v-if="isGreetingTyping"
                                                class="typing-cursor ml-0.5"
                                                aria-hidden="true"
                                            ></span>
                                        </span>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Teacher Message (appears in Phase 1+, right-aligned for teacher POV) -->
                        <div
                            class="hdw-answer hdw-turn-collapsible"
                            :class="{ 'is-on': answered }"
                        >
                            <div
                                class="hdw-turn-inner flex items-start justify-end gap-3 pt-1"
                            >
                                <div
                                    class="flex max-w-xl flex-col items-end space-y-1.5 sm:max-w-2xl"
                                >
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="font-mono text-[10px] text-muted-foreground/60 dark:text-[#72695f]"
                                            >Just now</span
                                        >
                                        <span
                                            class="text-[10px] text-muted-foreground/50 dark:text-[#524b44]"
                                            >·</span
                                        >
                                        <span
                                            class="font-mono text-[10px] text-muted-foreground dark:text-[#a1968a]"
                                            >Teacher</span
                                        >
                                        <span
                                            class="text-xs font-medium text-foreground dark:text-[#f5f0eb]"
                                            >Maria Santos</span
                                        >
                                    </div>
                                    <div
                                        class="rounded-2xl rounded-tr-xs border border-border/80 bg-stone-100/90 px-4 py-3 text-sm text-foreground shadow-xs transition-all duration-300 dark:border-[#382e26] dark:bg-[#251e18] dark:text-[#f5f0eb]"
                                    >
                                        <p class="text-sm leading-relaxed">
                                            {{ activeScenario.teacherRequest }}
                                        </p>
                                    </div>
                                    <!-- Result confirmation pill -->
                                    <div
                                        class="hdw-score inline-flex items-center gap-1.5 rounded-full border border-emerald-500/30 bg-emerald-500/15 px-3 py-1 text-xs font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-400"
                                        :class="{ 'is-on': answered }"
                                    >
                                        <svg
                                            viewBox="0 0 16 16"
                                            fill="none"
                                            stroke="currentColor"
                                            stroke-width="2"
                                            stroke-linecap="round"
                                            stroke-linejoin="round"
                                            class="h-3 w-3"
                                            aria-hidden="true"
                                        >
                                            <path d="m3.5 8.5 3 3 6-6" />
                                        </svg>
                                        {{ activeScenario.resultLabel }}
                                        <span
                                            class="hdw-answer-text font-mono font-medium"
                                            >{{
                                                activeScenario.resultValue
                                            }}</span
                                        >
                                    </div>
                                </div>
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-border/80 bg-secondary text-xs font-semibold text-foreground shadow-xs dark:border-[#382e26] dark:bg-[#251e18] dark:text-[#f5f0eb]"
                                >
                                    MS
                                </div>
                            </div>
                        </div>

                        <!-- Echo Response Flow (Phase 2 thinking, Phase 3+ response & approval) -->
                        <div
                            class="hdw-turn-collapsible"
                            :class="{ 'is-on': phase >= 2 }"
                        >
                            <div
                                class="hdw-turn-inner flex items-start gap-3 pt-1"
                            >
                                <div
                                    class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full border border-brand/20 bg-brand/15 text-base leading-none shadow-xs"
                                >
                                    <ChatAiOrb
                                        size="inline"
                                        variant="outline"
                                        :state="orbState"
                                        color="var(--color-brand)"
                                    />
                                </div>

                                <div class="w-full max-w-2xl space-y-2">
                                    <div class="flex items-center gap-2">
                                        <span
                                            class="text-xs font-medium text-brand"
                                            >Echo</span
                                        >
                                        <span
                                            class="font-mono text-[10px] text-muted-foreground dark:text-[#a1968a]"
                                            >Teaching Copilot</span
                                        >
                                        <span
                                            class="text-[10px] text-muted-foreground/50 dark:text-[#524b44]"
                                            >·</span
                                        >
                                        <span
                                            class="font-mono text-[10px] text-muted-foreground/60 dark:text-[#72695f]"
                                            >Just now</span
                                        >
                                    </div>

                                    <!-- Skeleton → answer crossfade -->
                                    <div class="hdw-swap min-h-24">
                                        <!-- Working indicator (Phase 2) -->
                                        <div
                                            class="hdw-skeleton space-y-2.5 rounded-2xl rounded-tl-xs border border-border/80 bg-white p-4 text-xs shadow-xs transition-opacity duration-300 dark:border-[#2c2520] dark:bg-[#1a1410]"
                                            :class="{
                                                'is-on': draftingSkeleton,
                                                'is-hidden': !draftingSkeleton,
                                            }"
                                            aria-hidden="true"
                                        >
                                            <div
                                                class="flex items-center gap-2 font-medium text-brand"
                                            >
                                                <span class="font-semibold"
                                                    >Thinking...</span
                                                >
                                                <span class="text-brand/40"
                                                    >·</span
                                                >
                                                <span
                                                    class="text-xs font-normal text-muted-foreground dark:text-[#a1968a]"
                                                    >{{
                                                        activeScenario.echoThinking
                                                    }}</span
                                                >
                                                <span
                                                    class="inline-flex items-center gap-1 py-0.5"
                                                >
                                                    <span
                                                        v-for="dot in 3"
                                                        :key="dot"
                                                        class="demo-dot h-1.5 w-1.5 rounded-full bg-brand"
                                                        :style="{
                                                            '--dot-index':
                                                                String(dot - 1),
                                                        }"
                                                    ></span>
                                                </span>
                                            </div>
                                            <div
                                                class="h-2.5 w-full animate-pulse rounded-full bg-muted dark:bg-[#2c2520]"
                                            ></div>
                                            <div
                                                class="h-2.5 w-4/5 animate-pulse rounded-full bg-muted dark:bg-[#2c2520]"
                                            ></div>
                                        </div>

                                        <!-- Final Echo answer (Phase 3+) -->
                                        <div
                                            class="hdw-draft rounded-2xl rounded-tl-xs border border-border/80 bg-white p-4 text-sm leading-relaxed text-foreground shadow-xs dark:border-[#2c2520] dark:bg-[#1a1410] dark:text-[#f5f0eb]"
                                            :class="{ 'is-on': draftVisible }"
                                        >
                                            <!-- Line 1: Bold key takeaway -->
                                            <p
                                                v-if="line1Chars > 0"
                                                class="hdw-type-line hdw-type-line-1 font-medium text-foreground dark:text-[#f5f0eb]"
                                            >
                                                <span>{{ displayLine1 }}</span>
                                                <span
                                                    v-if="isLine1Active"
                                                    class="typing-cursor ml-0.5"
                                                    aria-hidden="true"
                                                ></span>
                                            </p>

                                            <!-- Line 2: Supporting detail -->
                                            <p
                                                v-if="line2Chars > 0"
                                                class="hdw-type-line hdw-type-line-2 mt-1.5 text-foreground/80 dark:text-[#d3ccc2]"
                                            >
                                                <span>{{ displayLine2 }}</span>
                                                <span
                                                    v-if="isLine2Active"
                                                    class="typing-cursor ml-0.5"
                                                    aria-hidden="true"
                                                ></span>
                                            </p>

                                            <!-- Line 3: Highlighted results -->
                                            <p
                                                v-if="line3Chars > 0"
                                                class="hdw-type-line hdw-type-line-3 mt-1.5 text-foreground/80 dark:text-[#d3ccc2]"
                                            >
                                                <template v-if="line3Complete">
                                                    <span>{{
                                                        activeScenario.echoSteps
                                                            .resultIntro
                                                    }}</span>
                                                    <template
                                                        v-for="(
                                                            val, idx
                                                        ) in activeScenario
                                                            .echoSteps
                                                            .resultValues"
                                                        :key="idx"
                                                    >
                                                        <span
                                                            class="hdw-result-chip inline-block rounded border border-emerald-500/20 bg-emerald-500/10 px-1.5 py-0.5 font-mono text-[11px] font-medium text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-400"
                                                            >{{ val }}</span
                                                        >
                                                        <span
                                                            v-if="
                                                                idx <
                                                                activeScenario
                                                                    .echoSteps
                                                                    .resultValues
                                                                    .length -
                                                                    1
                                                            "
                                                        >
                                                            {{ RESULT_JOIN }}
                                                        </span>
                                                    </template>
                                                </template>
                                                <template v-else>
                                                    <span>{{
                                                        displayLine3
                                                    }}</span>
                                                </template>
                                                <span
                                                    v-if="isLine3Active"
                                                    class="typing-cursor ml-0.5"
                                                    aria-hidden="true"
                                                ></span>
                                            </p>

                                            <!-- Line 4: Provenance / next step -->
                                            <p
                                                v-if="line4Chars > 0"
                                                class="hdw-type-line hdw-type-line-4 mt-3 border-t border-border/70 pt-2 text-xs text-muted-foreground italic dark:border-[#2c2520] dark:text-[#a1968a]"
                                            >
                                                <span>{{ displayLine4 }}</span>
                                                <span
                                                    v-if="isLine4Active"
                                                    class="typing-cursor ml-0.5"
                                                    aria-hidden="true"
                                                ></span>
                                            </p>
                                        </div>
                                    </div>

                                    <!-- Teacher confirmation action swap -->
                                    <div
                                        class="hdw-swap justify-items-start pt-1"
                                    >
                                        <button
                                            type="button"
                                            class="hdw-approve hdw-approve--pending inline-flex min-h-9 cursor-pointer items-center gap-2 rounded-full border border-amber-500/30 bg-amber-500/15 px-4 text-xs font-semibold text-amber-800 transition-transform active:scale-95 dark:border-amber-500/30 dark:bg-amber-500/15 dark:text-amber-300"
                                            :class="{
                                                'is-on':
                                                    draftReady && !confirmed,
                                            }"
                                            @click="handleConfirmSend"
                                        >
                                            <span>Needs your OK</span>
                                            <span
                                                class="text-muted-foreground/60 dark:text-[#72695f]"
                                                >·</span
                                            >
                                            <span>Confirm &amp; send</span>
                                            <span
                                                class="hdw-nudge h-1.5 w-1.5 rounded-full bg-current"
                                                aria-hidden="true"
                                            ></span>
                                        </button>
                                        <span
                                            class="hdw-approve hdw-approve--done inline-flex min-h-9 items-center gap-2 rounded-full border border-emerald-500/30 bg-emerald-500/15 px-4 text-xs font-semibold text-emerald-800 dark:border-emerald-500/30 dark:bg-emerald-500/15 dark:text-emerald-400"
                                            :class="{ 'is-on': confirmed }"
                                        >
                                            <svg
                                                viewBox="0 0 16 16"
                                                fill="none"
                                                stroke="currentColor"
                                                stroke-width="2"
                                                stroke-linecap="round"
                                                stroke-linejoin="round"
                                                class="h-3.5 w-3.5"
                                                aria-hidden="true"
                                            >
                                                <path d="m3.5 8.5 3 3 6-6" />
                                            </svg>
                                            Sent to class
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Bottom chat prompt with active typing preview during phase 0 -->
                    <div
                        class="mt-4 flex shrink-0 items-center justify-between gap-3 border-t border-border/80 pt-3 dark:border-[#2c2520]"
                    >
                        <div class="relative flex-1">
                            <div
                                data-testid="prompt-input"
                                class="flex min-h-[40px] w-full items-center overflow-hidden rounded-full border border-border/80 bg-secondary/50 py-2 pr-20 pl-4 text-xs text-foreground transition-all duration-300 select-none dark:border-[#2c2520] dark:bg-[#17120e] dark:text-[#f5f0eb]"
                                :class="{
                                    'border-brand/40 bg-brand/[0.04] shadow-[0_0_12px_color-mix(in_srgb,var(--color-brand)_15%,transparent)] dark:border-brand/40 dark:bg-brand/[0.06]':
                                        phase === 0,
                                }"
                                @click="handleSendPrompt"
                            >
                                <span
                                    v-if="phase === 0 && typedPromptChars > 0"
                                    class="flex items-center truncate text-foreground dark:text-[#f5f0eb]"
                                >
                                    <span>{{ effectiveTypedPrompt }}</span>
                                    <span
                                        v-if="isPromptTyping"
                                        class="typing-cursor ml-0.5"
                                        aria-hidden="true"
                                    ></span>
                                </span>
                                <span
                                    v-else
                                    class="truncate text-muted-foreground dark:text-[#72695f]"
                                >
                                    Ask Echo anything about your class...
                                </span>
                            </div>
                            <button
                                ref="sendButtonRef"
                                type="button"
                                class="hdw-enter absolute top-1/2 right-1.5 flex -translate-y-1/2 cursor-pointer items-center gap-1 rounded-full px-2.5 py-1 font-mono text-[10px] transition-all duration-300"
                                :class="[
                                    phase === 0 && promptComplete
                                        ? 'scale-105 bg-brand font-semibold text-white shadow-xs hover:brightness-110 active:scale-95'
                                        : 'bg-secondary text-muted-foreground/60 dark:bg-[#221b15] dark:text-[#a1968a]',
                                    { 'is-pressing': pressing },
                                ]"
                                aria-label="Send message"
                                @click="handleSendPrompt"
                            >
                                <span class="hidden text-[9px] sm:inline"
                                    >Enter</span
                                >
                                <svg
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    stroke="currentColor"
                                    stroke-width="2"
                                    stroke-linecap="round"
                                    stroke-linejoin="round"
                                    class="hdw-enter-arrow h-3 w-3"
                                    aria-hidden="true"
                                >
                                    <path d="M8 12V4M4 8l4-4 4 4" />
                                </svg>
                            </button>
                        </div>
                        <span
                            class="hdw-delivered shrink-0 text-xs font-medium text-emerald-800 transition-opacity duration-300 dark:text-emerald-400"
                            :class="{ 'is-on': confirmed }"
                        >
                            Delivered to class ✓
                        </span>
                    </div>
                </div>
            </div>

            <!-- Footer telemetry -->
            <div
                class="flex flex-wrap items-center justify-between gap-3 border-t border-border/80 bg-secondary/80 px-4 py-2.5 text-xs text-muted-foreground sm:px-6 dark:border-[#2c2520] dark:bg-[#1a1410] dark:text-[#a1968a]"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="h-1.5 w-24 overflow-hidden rounded-full bg-muted sm:w-36 dark:bg-[#251e18]"
                    >
                        <div
                            class="hdw-progress h-full rounded-full bg-brand transition-all duration-500"
                            :style="{
                                width: confirmed
                                    ? '98%'
                                    : activeScenario.progressWidth,
                            }"
                        ></div>
                    </div>
                    <span>{{ activeScenario.footNote }}</span>
                </div>
                <span
                    class="font-mono text-[11px] text-muted-foreground dark:text-[#a1968a]"
                >
                    100% human-verified AI actions
                </span>
            </div>
        </div>

        <!-- Scripted pointer — Maria's hand on the keyboard. -->
        <div
            data-testid="demo-cursor"
            class="hdw-cursor pointer-events-none absolute top-0 left-0 z-20"
            :class="{
                'is-visible': cursor.visible,
                'is-pressing': cursor.pressing,
                'is-instant': cursor.instant,
            }"
            :style="{
                '--cursor-x': `${cursor.x}px`,
                '--cursor-y': `${cursor.y}px`,
                '--cursor-travel': `${cursor.travel}ms`,
            }"
            aria-hidden="true"
        >
            <svg
                viewBox="0 0 15 21"
                class="h-[21px] w-[15px] overflow-visible text-neutral-900 drop-shadow-[0_2px_3px_rgba(0,0,0,0.35)] dark:text-neutral-100"
            >
                <path
                    d="M0 0 L0 17 L4.4 12.8 L7 19 L10 17.8 L7.4 11.8 L13.4 11.5 Z"
                    fill="currentColor"
                    stroke="white"
                    stroke-width="1.3"
                    stroke-linejoin="round"
                />
            </svg>
        </div>
    </div>
</template>

<style scoped>
.hdw-swap {
    display: grid;
}

.hdw-swap > * {
    grid-area: 1 / 1;
    transition: opacity var(--dur-hover) var(--ease-out-expo);
}

/* ── Entrance gate ──
   The scripted conversation stays hidden until the hero window has finished
   its entrance animation, then crossfades in under the boot placeholder. */
.hdw-stage {
    transition: opacity 320ms var(--ease-out-expo);
}

.hdw-stage.is-hidden {
    opacity: 0;
}

@media (prefers-reduced-motion: reduce) {
    .hdw-stage {
        transition: none;
    }
}

/* ── Status badges ── */
.hdw-badge {
    opacity: 0;
}

.hdw-badge.is-on {
    opacity: 1;
}

/* ── Chat feed scrollbar ── */
.chat-feed-scroll {
    scrollbar-width: thin;
    scrollbar-color: color-mix(
            in srgb,
            var(--color-foreground) 15%,
            transparent
        )
        transparent;
}

.chat-feed-scroll::-webkit-scrollbar {
    width: 4px;
}

.chat-feed-scroll::-webkit-scrollbar-track {
    background: transparent;
}

.chat-feed-scroll::-webkit-scrollbar-thumb {
    background: color-mix(in srgb, var(--color-foreground) 15%, transparent);
    border-radius: 4px;
}

/* ── Collapsible turn transitions ── */
.hdw-turn-collapsible {
    display: grid;
    grid-template-rows: 0fr;
    opacity: 0;
    transform: translateY(8px);
    transition:
        grid-template-rows 360ms cubic-bezier(0.16, 1, 0.3, 1),
        opacity 280ms cubic-bezier(0.16, 1, 0.3, 1),
        transform 320ms cubic-bezier(0.16, 1, 0.3, 1);
}

.hdw-turn-collapsible > .hdw-turn-inner {
    overflow: hidden;
}

.hdw-turn-collapsible.is-on {
    grid-template-rows: 1fr;
    opacity: 1;
    transform: translateY(0);
}

/* ── Student answer type-in ── */
.hdw-answer {
    opacity: 0;
    transform: translateY(6px);
    transition:
        opacity 280ms cubic-bezier(0.16, 1, 0.3, 1),
        transform 280ms cubic-bezier(0.16, 1, 0.3, 1);
}

.hdw-answer.is-on {
    opacity: 1;
    transform: translateY(0);
}

.hdw-answer-text {
    display: inline-block;
    clip-path: inset(0 100% 0 0);
}

.hdw-answer.is-on .hdw-answer-text {
    clip-path: inset(0 0 0 0);
    transition: clip-path 700ms steps(16, end);
}

/* ── Auto-score chip pop ── */
.hdw-score {
    opacity: 0;
    transform: scale(0.7) translateY(3px);
    transition:
        opacity var(--dur-pop) var(--ease-pop),
        transform var(--dur-pop) var(--ease-pop);
}

.hdw-score.is-on {
    opacity: 1;
    transform: scale(1) translateY(0);
}

/* ── Draft skeleton → text ── */
.hdw-swap > .is-hidden {
    opacity: 0 !important;
    pointer-events: none;
}

.hdw-skeleton {
    opacity: 0;
    transform: translateY(4px);
    transition:
        opacity 300ms var(--ease-out-expo),
        transform 300ms var(--ease-out-expo);
}

.hdw-skeleton.is-on {
    opacity: 1;
    transform: translateY(0);
}

.hdw-draft {
    opacity: 0;
    transform: translateY(4px);
    transition:
        opacity 300ms var(--ease-out-expo),
        transform 300ms var(--ease-out-expo);
}

.hdw-draft.is-on {
    opacity: 1;
    transform: translateY(0);
}

/* ── Result chip in Echo line 3 ── */
.hdw-result-chip {
    animation: hdw-chip-in 0.25s var(--ease-pop) forwards;
}

@keyframes hdw-chip-in {
    from {
        opacity: 0.6;
        transform: scale(0.96);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

/* ── Echo thinking indicator & dots ── */
.demo-thinking-text {
    display: inline-block;
    animation: demo-thinking-pulse 2.2s ease-in-out infinite;
}

@keyframes demo-thinking-pulse {
    0%,
    100% {
        opacity: 0.85;
    }
    50% {
        opacity: 1;
    }
}

.demo-dot {
    display: inline-block;
    animation: demo-dot-bounce 1.1s cubic-bezier(0.4, 0, 0.2, 1) infinite;
    animation-delay: calc(var(--dot-index) * 0.18s);
}

@keyframes demo-dot-bounce {
    0%,
    80%,
    100% {
        transform: translateY(0) scale(0.8);
        opacity: 0.35;
    }
    40% {
        transform: translateY(-4px) scale(1.2);
        opacity: 1;
    }
}

/* ── Echo streaming response lines ──
   Each line is created by `v-if` as its first characters land, so the
   entrance plays per line instead of firing once for the whole bubble. */
.hdw-type-line {
    animation: hdw-stream-in 0.4s var(--ease-out-expo) forwards;
}

@keyframes hdw-stream-in {
    from {
        opacity: 0;
        transform: translateY(4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

/* ── Approval swap ── */
.hdw-approve {
    opacity: 0;
    transform: translateY(4px) scale(0.96);
    transition:
        opacity var(--dur-hover) var(--ease-out-expo),
        transform var(--dur-hover) var(--ease-out-expo);
}

.hdw-approve.is-on {
    opacity: 1;
    transform: translateY(0) scale(1);
}

.hdw-approve--done.is-on {
    transition-timing-function: var(--ease-pop);
}

.hdw-nudge {
    animation: hdw-nudge 1.6s ease-in-out infinite;
}

@keyframes hdw-nudge {
    0%,
    100% {
        transform: scale(1);
        opacity: 1;
    }
    50% {
        transform: scale(1.5);
        opacity: 0.5;
    }
}

/* ── Footer progress + delivery note ── */
.hdw-progress {
    width: 86%;
    transition: width 600ms var(--ease-out-expo);
}

[data-phase='4'] .hdw-progress {
    width: 93%;
}

.hdw-delivered {
    opacity: 0;
    transform: translateY(2px);
    transition:
        opacity var(--dur-hover) var(--ease-out-expo),
        transform var(--dur-hover) var(--ease-out-expo);
}

.hdw-delivered.is-on {
    opacity: 1;
    transform: translateY(0);
}

/* ── Scripted pointer ──
   Maria's pointer flies in from the open chat area, settles on the Enter
   key and clicks it. The travel is a transition on the position, so the
   pointer fades in where it starts and lands exactly on the key. */
.hdw-cursor {
    opacity: 0;
    transform: translate3d(var(--cursor-x), var(--cursor-y), 0);
    transition:
        transform var(--cursor-travel) var(--ease-out-expo),
        opacity 180ms var(--ease-out-expo);
}

.hdw-cursor.is-visible {
    opacity: 1;
}

/* The pointer is teleported to its start before the travel — no drift. */
.hdw-cursor.is-instant {
    transition: none;
}

.hdw-cursor.is-pressing svg {
    transform-origin: top left;
    animation: hdw-cursor-click 220ms var(--ease-out-expo);
}

@keyframes hdw-cursor-click {
    0% {
        transform: scale(1);
    }
    40% {
        transform: scale(0.8);
    }
    100% {
        transform: scale(1);
    }
}

/* ── Enter keypress ──
   Maria's thumb lands on Enter: the key dips, the arrow springs up, and a
   ring pings out of it. The scale and translate Tailwind utilities use their
   own properties, so animating `transform` here composes with them. */
.hdw-enter {
    isolation: isolate;
}

.hdw-enter::after {
    content: '';
    position: absolute;
    inset: -3px;
    border-radius: 9999px;
    border: 1px solid var(--color-brand);
    opacity: 0;
    pointer-events: none;
}

.hdw-enter.is-pressing {
    animation: hdw-enter-press 280ms var(--ease-out-expo);
}

@keyframes hdw-enter-press {
    0% {
        transform: scale(1);
    }
    40% {
        transform: scale(0.86);
    }
    100% {
        transform: scale(1);
    }
}

.hdw-enter.is-pressing .hdw-enter-arrow {
    animation: hdw-enter-arrow 280ms var(--ease-out-expo);
}

@keyframes hdw-enter-arrow {
    0% {
        transform: translateY(0);
    }
    40% {
        transform: translateY(2px);
    }
    72% {
        transform: translateY(-1.5px);
    }
    100% {
        transform: translateY(0);
    }
}

.hdw-enter.is-pressing::after {
    animation: hdw-enter-ring 420ms var(--ease-out-expo);
}

@keyframes hdw-enter-ring {
    0% {
        opacity: 0.5;
        transform: scale(0.92);
    }
    100% {
        opacity: 0;
        transform: scale(1.35);
    }
}

/* ── Letter-by-letter typing cursor ──
   A slim caret with a soft glow, blinking on a long beat so it reads as a
   text field waiting for input rather than a blinking cursor gimmick. */
.typing-cursor {
    display: inline-block;
    width: 2px;
    height: 1.1em;
    margin-left: 1px;
    vertical-align: -0.16em;
    border-radius: 1px;
    background-color: var(--color-brand);
    box-shadow: 0 0 6px color-mix(in srgb, var(--color-brand) 45%, transparent);
    animation: typing-blink 1.05s cubic-bezier(0.4, 0, 0.6, 1) infinite;
}

@keyframes typing-blink {
    0%,
    100% {
        opacity: 1;
    }
    50% {
        opacity: 0;
    }
}

@media (prefers-reduced-motion: reduce) {
    .hdw-swap > *,
    .hdw-answer,
    .hdw-answer-text,
    .hdw-score,
    .hdw-approve,
    .hdw-progress,
    .hdw-delivered,
    .hdw-skeleton,
    .hdw-draft {
        transition: none;
    }

    .hdw-turn-collapsible {
        display: block !important;
        grid-template-rows: none !important;
        opacity: 1 !important;
        transform: none !important;
        transition: none !important;
    }

    .hdw-turn-collapsible > .hdw-turn-inner {
        overflow: visible !important;
    }

    .demo-dot,
    .demo-thinking-text {
        animation: none;
    }

    .typing-cursor {
        display: none !important;
    }

    .hdw-type-line {
        opacity: 1;
        transform: none;
        animation: none !important;
    }

    .hdw-nudge,
    .hdw-result-chip {
        animation: none;
    }

    /* `:root` keeps these above the app-level low-end guard, which reverts
       the durations with `!important` on the very devices that need the
       reduced-motion treatment the most. */
    :root .hdw-enter,
    :root .hdw-enter .hdw-enter-arrow,
    :root .hdw-enter::after {
        animation: none !important;
    }

    :root .hdw-cursor,
    :root .hdw-cursor svg {
        animation: none !important;
        transition: none !important;
    }
}
</style>
