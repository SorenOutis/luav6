<template>
    <div
        class="relative mx-auto w-full max-w-[470px] rotate-[1deg] px-5 py-3 sm:px-8"
    >
        <div
            class="absolute inset-0 rounded-[2px] border border-border/50 bg-card shadow-[0_18px_28px_-20px_rgba(26,26,30,0.5)]"
            aria-hidden="true"
        ></div>
        <div
            class="relative min-h-[340px] border border-border/35 bg-card/95 px-7 py-8 sm:px-10 sm:py-10"
        >
            <div
                class="absolute top-4 left-3 flex flex-col gap-4 sm:left-4"
                aria-hidden="true"
            >
                <span
                    v-for="dot in 5"
                    :key="dot"
                    class="h-2 w-2 rounded-full bg-border/70"
                ></span>
            </div>
            <div class="ml-4 sm:ml-5">
                <p
                    class="font-serif text-lg tracking-[0.04em] text-foreground sm:text-xl"
                >
                    TODAY'S CLASS
                </p>
                <div class="mt-2 h-px w-32 bg-foreground/70"></div>

                <div class="relative mt-8 space-y-8 pl-8">
                    <div
                        class="absolute top-2 bottom-2 left-[5px] w-px bg-primary/70"
                    ></div>
                    <div
                        v-for="item in [
                            'What did learners understand?',
                            'Who needs support?',
                            'What should we teach next?',
                        ]"
                        :key="item"
                        class="relative"
                    >
                        <span
                            class="absolute top-1.5 -left-8 h-3 w-3 rounded-full border-2 border-primary bg-card"
                        ></span>
                        <p
                            class="max-w-[250px] font-serif text-lg leading-snug text-foreground italic sm:text-xl"
                        >
                            {{ item }}
                        </p>
                    </div>
                </div>

                <div
                    class="mt-10 flex items-center justify-between border-t border-border/60 pt-4 text-xs text-muted-foreground"
                >
                    <span>Grade 8 · Mathematics</span>
                    <span class="font-medium text-primary"
                        >Ready for review</span
                    >
                </div>
            </div>
        </div>
    </div>
</template>
