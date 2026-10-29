<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
import { ArrowRight, ArrowUp } from 'lucide-vue-next';
import { computed, ref, watch } from 'vue';
import ChatAiOrb from '@/components/ChatAiOrb.vue';
import RevealGroup from '@/components/welcome/RevealGroup.vue';
import { useDemoLoop, useInView } from '@/composables/useDemoLoop';

const props = withDefaults(
    defineProps<{
        auth: { user: any };
        canRegister?: boolean;
        dashboard: () => string;
        login: () => string;
        register: () => string;
        prefersReducedMotion?: boolean;
    }>(),
    { canRegister: true, prefersReducedMotion: false },
);

const suggestions = [
    'Build a 15-item quiz on quadratic equations for Section Diamond',
    'Draft feedback for the essays that scored below 75%',
    'Which learners missed the zero-product property?',
    'Export Grade 8 scores for the first grading term',
];

/**
 * The placeholder rotates through example prompts while the composer is on
 * screen. One phase per suggestion; it parks on the first prompt when
 * motion is reduced so the static frame is predictable.
 */
const root = ref<HTMLElement | null>(null);
const inView = useInView(root);
const { phase, start, stop, set } = useDemoLoop(suggestions.map(() => 3200));

watch(
    [inView, () => props.prefersReducedMotion],
    ([visible, reduced]) => {
        if (reduced) {
            stop();
            set(0);

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

const prompt = ref('');
const placeholder = computed(() => suggestions[phase.value]);

const destination = computed(() => {
    if (props.auth?.user) {
        return props.dashboard();
    }

    return props.canRegister ? props.register() : props.login();
});

const primaryLabel = computed(() => {
    if (props.auth?.user) {
        return 'Open dashboard';
    }

    return props.canRegister ? 'Create free account' : 'Sign in';
});

const useSuggestion = (suggestion: string) => {
    prompt.value = suggestion;
};

const submit = () => {
    router.visit(destination.value);
};
</script>

<template>
    <RevealGroup
        as="section"
        :stagger="90"
        class="welcome-cta relative mx-auto w-full max-w-6xl overflow-hidden rounded-3xl border border-border/80 bg-card px-6 py-14 text-foreground shadow-xs sm:px-10 sm:py-20 lg:px-14 xl:max-w-7xl"
        aria-labelledby="cta-heading"
    >
        <!-- Giant outline Echo watermark -->
        <div
            class="pointer-events-none absolute -right-6 -bottom-16 z-0 origin-bottom-right scale-[1.8] opacity-[0.08] select-none sm:-bottom-20"
            aria-hidden="true"
        >
            <ChatAiOrb
                size="lg"
                variant="outline"
                state="micro"
                :delay="1500"
                color="var(--color-brand)"
            />
        </div>

        <div
            ref="root"
            class="relative z-10 mx-auto flex max-w-3xl flex-col items-center text-center"
        >
            <p
                class="rv font-mono text-xs font-medium tracking-widest text-brand uppercase"
                :style="{ '--rv-i': 0 }"
            >
                Get started
            </p>
            <h2
                id="cta-heading"
                class="rv mt-3 font-sans text-3xl font-semibold tracking-[-0.035em] text-balance text-foreground sm:text-4xl lg:text-5xl"
                :style="{ '--rv-i': 1 }"
            >
                What should Echo do for your class?
            </h2>
            <p
                class="rv mt-4 text-sm text-muted-foreground sm:text-base"
                :style="{ '--rv-i': 2 }"
            >
                Start with a teacher, a class, or a whole school.
            </p>

            <form
                class="rv cta-composer mt-8 flex w-full items-center gap-2 rounded-full border border-border/80 bg-background py-2 pr-2 pl-5 shadow-xs transition-shadow focus-within:border-foreground/30 focus-within:shadow-md"
                :style="{ '--rv-i': 3 }"
                data-testid="echo-cta-form"
                @submit.prevent="submit"
            >
                <span class="shrink-0" aria-hidden="true">
                    <ChatAiOrb
                        size="inline"
                        animate-idle
                        color="var(--color-brand)"
                    />
                </span>
                <label for="echo-cta-input" class="sr-only">Ask Echo</label>
                <input
                    id="echo-cta-input"
                    v-model="prompt"
                    type="text"
                    autocomplete="off"
                    :placeholder="placeholder"
                    class="min-w-0 flex-1 bg-transparent py-2 text-sm text-foreground placeholder:text-muted-foreground focus:outline-none sm:text-base"
                    data-testid="echo-cta-input"
                />
                <button
                    type="submit"
                    class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full bg-foreground text-background transition-all hover:opacity-90 active:scale-95 sm:h-11 sm:w-11"
                    :aria-label="primaryLabel"
                >
                    <ArrowUp class="h-4 w-4" aria-hidden="true" />
                </button>
            </form>

            <div
                class="rv mt-4 flex flex-wrap justify-center gap-2"
                :style="{ '--rv-i': 4 }"
            >
                <button
                    v-for="suggestion in suggestions.slice(0, 3)"
                    :key="suggestion"
                    type="button"
                    class="max-w-full truncate rounded-full border border-border/80 bg-background px-3 py-1.5 text-xs text-muted-foreground transition-colors hover:border-foreground/30 hover:text-foreground"
                    @click="useSuggestion(suggestion)"
                >
                    {{ suggestion }}
                </button>
            </div>

            <div
                class="rv mt-10 flex flex-col items-center gap-3 sm:flex-row"
                :style="{ '--rv-i': 5 }"
            >
                <Link
                    :href="destination"
                    class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full bg-foreground px-8 text-sm font-semibold text-background shadow-xs transition-all hover:opacity-90 hover:shadow-md focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]"
                >
                    {{ primaryLabel }}
                    <ArrowRight class="h-4 w-4" aria-hidden="true" />
                </Link>
                <Link
                    v-if="!auth?.user && canRegister"
                    :href="login()"
                    class="inline-flex min-h-12 items-center justify-center rounded-full border border-border/80 bg-card px-6 text-sm font-medium text-foreground shadow-xs transition-all hover:border-foreground/30 focus-visible:ring-2 focus-visible:ring-offset-2 active:scale-[0.98]"
                >
                    Sign in
                </Link>
                <a
                    href="mailto:poweredbyrazer022@dccp.edu.ph?subject=LSI%20for%20our%20school"
                    class="inline-flex min-h-12 items-center justify-center px-4 text-sm font-medium text-muted-foreground underline-offset-4 transition-colors hover:text-foreground hover:underline"
                >
                    Talk to us
                </a>
            </div>
        </div>
    </RevealGroup>
</template>
