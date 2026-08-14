<script setup lang="ts"></script>

<template>
    <div class="flex flex-col gap-3 sm:gap-6">
        <div class="flex items-center gap-3 py-0.5 sm:gap-4 sm:py-1">
            <div
                class="h-11 w-11 shrink-0 animate-pulse rounded-full bg-muted sm:h-16 sm:w-16"
            ></div>
            <div class="flex flex-1 flex-col gap-2">
                <div
                    class="h-7 w-48 max-w-full animate-pulse rounded-full bg-muted"
                ></div>
                <div
                    class="h-4 w-72 max-w-full animate-pulse rounded-full bg-muted/70"
                ></div>
            </div>
        </div>

        <div
            class="flex flex-col gap-2 rounded-xl border border-border/40 bg-card p-3 sm:flex-row sm:items-stretch sm:gap-4 sm:rounded-[1.25rem] sm:p-5"
        >
            <div class="grid flex-1 grid-cols-3 gap-1.5 sm:gap-3">
                <div
                    v-for="m in 3"
                    :key="m"
                    class="flex h-[72px] flex-col justify-between rounded-xl bg-muted/40 p-2 sm:h-[92px] sm:rounded-[1.1rem] sm:p-3"
                >
                    <div
                        class="h-3 w-12 animate-pulse rounded-full bg-muted"
                    ></div>
                    <div
                        class="h-7 w-8 animate-pulse rounded-lg bg-muted"
                    ></div>
                </div>
            </div>
            <div
                class="hidden h-auto min-h-[88px] flex-col justify-center gap-2 rounded-[1.1rem] bg-muted/40 px-4 py-3 lg:flex lg:w-[32%] lg:min-w-[260px]"
            >
                <div class="h-3 w-24 animate-pulse rounded-full bg-muted"></div>
                <div class="h-4 w-40 animate-pulse rounded-full bg-muted"></div>
            </div>
        </div>

        <div
            class="h-16 w-full animate-pulse rounded-[1.25rem] border border-border/40 bg-card"
        ></div>

        <div
            class="grid grid-cols-1 gap-2.5 sm:gap-4 md:grid-cols-2 lg:grid-cols-4"
        >
            <div
                class="flex h-36 flex-col justify-between rounded-xl border border-border/40 bg-card p-3.5 sm:h-44 sm:rounded-[1.25rem] sm:p-5 lg:col-span-2"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="h-10 w-10 animate-pulse rounded-full bg-muted"
                    ></div>
                    <div class="flex flex-col gap-2">
                        <div
                            class="h-3 w-16 animate-pulse rounded-full bg-muted"
                        ></div>
                        <div
                            class="h-7 w-20 animate-pulse rounded-lg bg-muted"
                        ></div>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <div
                        class="h-2 w-full animate-pulse rounded-full bg-muted"
                    ></div>
                    <div
                        class="h-3 w-2/3 animate-pulse rounded-full bg-muted"
                    ></div>
                </div>
            </div>
            <div
                class="flex h-36 flex-col justify-between rounded-xl border border-border/40 bg-card p-3.5 sm:h-44 sm:rounded-[1.25rem] sm:p-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="h-10 w-10 animate-pulse rounded-full bg-muted"
                    ></div>
                    <div class="flex flex-col gap-2">
                        <div
                            class="h-3 w-16 animate-pulse rounded-full bg-muted"
                        ></div>
                        <div
                            class="h-7 w-14 animate-pulse rounded-lg bg-muted"
                        ></div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <div
                        v-for="i in 7"
                        :key="i"
                        class="h-6 flex-1 animate-pulse rounded-full bg-muted"
                    ></div>
                </div>
            </div>
            <div
                class="flex h-36 flex-col justify-between rounded-xl border border-border/40 bg-card p-3.5 sm:h-44 sm:rounded-[1.25rem] sm:p-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="h-10 w-10 animate-pulse rounded-full bg-muted"
                    ></div>
                    <div class="flex flex-col gap-2">
                        <div
                            class="h-3 w-16 animate-pulse rounded-full bg-muted"
                        ></div>
                        <div
                            class="h-6 w-24 animate-pulse rounded-lg bg-muted"
                        ></div>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <div
                        class="h-2 w-full animate-pulse rounded-full bg-muted"
                    ></div>
                    <div
                        class="h-3 w-1/2 animate-pulse rounded-full bg-muted"
                    ></div>
                </div>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <div class="flex flex-col gap-8 lg:col-span-2">
                <div
                    class="flex flex-col gap-4 rounded-[1.25rem] border border-border/40 bg-card p-5"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="h-5 w-36 animate-pulse rounded-full bg-muted"
                        ></div>
                        <div
                            class="h-5 w-20 animate-pulse rounded-full bg-muted"
                        ></div>
                    </div>
                    <div
                        v-for="j in 4"
                        :key="j"
                        class="flex items-center gap-3 rounded-xl p-2"
                    >
                        <div
                            class="h-8 w-8 animate-pulse rounded-full bg-muted"
                        ></div>
                        <div class="flex flex-1 flex-col gap-1.5">
                            <div
                                class="h-3.5 w-28 animate-pulse rounded-full bg-muted"
                            ></div>
                            <div
                                class="h-2.5 w-20 animate-pulse rounded-full bg-muted"
                            ></div>
                        </div>
                        <div
                            class="h-3.5 w-16 animate-pulse rounded-full bg-muted"
                        ></div>
                    </div>
                </div>
            </div>

            <div class="flex flex-col gap-6">
                <div
                    class="flex flex-col gap-3 rounded-[1.25rem] border border-border/40 bg-card p-5"
                >
                    <div
                        class="h-4 w-24 animate-pulse rounded-full bg-muted"
                    ></div>
                    <div class="grid grid-cols-7 gap-1.5">
                        <div
                            v-for="l in 35"
                            :key="l"
                            class="aspect-square animate-pulse rounded-md bg-muted"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
