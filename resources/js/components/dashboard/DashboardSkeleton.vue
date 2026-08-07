<script setup lang="ts"></script>

<template>
    <div class="flex flex-col gap-6">
        <!-- Hero Skeleton -->
        <div
            class="flex flex-col gap-4 rounded-2xl border border-border/10 bg-card/30 p-5 sm:p-6 md:p-8"
        >
            <div class="flex items-start justify-between">
                <div class="flex flex-col gap-3">
                    <div
                        class="h-5 w-48 animate-pulse rounded-md bg-primary/10"
                    ></div>
                    <div
                        class="h-4 w-72 animate-pulse rounded-md bg-primary/10"
                    ></div>
                </div>
                <div
                    class="hidden h-10 w-10 animate-pulse rounded-full bg-primary/10 sm:block"
                ></div>
            </div>
            <div class="mt-2 flex flex-wrap gap-3">
                <div
                    class="h-8 w-24 animate-pulse rounded-lg bg-primary/10"
                ></div>
                <div
                    class="h-8 w-28 animate-pulse rounded-lg bg-primary/10"
                ></div>
                <div
                    class="h-8 w-20 animate-pulse rounded-lg bg-primary/10"
                ></div>
            </div>
        </div>

        <!-- Focus Strip Skeleton: today / overdue / next 24h -->
        <div
            class="flex flex-col gap-3 rounded-2xl border border-border/10 bg-card/30 p-4 sm:flex-row sm:items-stretch sm:gap-4 sm:p-5"
        >
            <div class="grid flex-1 grid-cols-3 gap-3">
                <div
                    v-for="m in 3"
                    :key="m"
                    class="flex h-16 flex-col justify-between rounded-xl border border-border/10 bg-primary/[0.03] p-3"
                >
                    <div
                        class="h-3 w-10 animate-pulse rounded bg-primary/10"
                    ></div>
                    <div
                        class="h-6 w-8 animate-pulse rounded bg-primary/10"
                    ></div>
                </div>
            </div>
            <div
                class="hidden flex-col justify-center gap-2 rounded-xl border border-border/10 bg-primary/[0.03] px-4 py-3 lg:flex lg:w-[32%] lg:min-w-[280px]"
            >
                <div class="h-3 w-24 animate-pulse rounded bg-primary/10"></div>
                <div class="h-4 w-40 animate-pulse rounded bg-primary/10"></div>
            </div>
        </div>

        <!-- Daily Reward Skeleton (only shows while booting) -->
        <div
            class="h-16 w-full animate-pulse rounded-2xl border border-border/10 bg-card/30"
        ></div>

        <!-- Progress Row Skeleton: Level / Streak / Season -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2 lg:grid-cols-4">
            <div
                class="flex h-44 flex-col justify-between rounded-2xl border border-border/10 bg-card/30 p-5 lg:col-span-2"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="h-10 w-10 animate-pulse rounded-xl bg-primary/10"
                    ></div>
                    <div class="flex flex-col gap-2">
                        <div
                            class="h-3 w-16 animate-pulse rounded bg-primary/10"
                        ></div>
                        <div
                            class="h-7 w-20 animate-pulse rounded bg-primary/10"
                        ></div>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <div
                        class="h-3 w-full animate-pulse rounded-full bg-primary/10"
                    ></div>
                    <div
                        class="h-3 w-2/3 animate-pulse rounded bg-primary/10"
                    ></div>
                </div>
            </div>
            <div
                class="flex h-44 flex-col justify-between rounded-2xl border border-border/10 bg-card/30 p-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="h-10 w-10 animate-pulse rounded-xl bg-primary/10"
                    ></div>
                    <div class="flex flex-col gap-2">
                        <div
                            class="h-3 w-16 animate-pulse rounded bg-primary/10"
                        ></div>
                        <div
                            class="h-7 w-14 animate-pulse rounded bg-primary/10"
                        ></div>
                    </div>
                </div>
                <div class="flex gap-2">
                    <div
                        v-for="i in 7"
                        :key="i"
                        class="h-6 flex-1 animate-pulse rounded-md bg-primary/10"
                    ></div>
                </div>
            </div>
            <div
                class="flex h-44 flex-col justify-between rounded-2xl border border-border/10 bg-card/30 p-5"
            >
                <div class="flex items-center gap-3">
                    <div
                        class="h-10 w-10 animate-pulse rounded-xl bg-primary/10"
                    ></div>
                    <div class="flex flex-col gap-2">
                        <div
                            class="h-3 w-16 animate-pulse rounded bg-primary/10"
                        ></div>
                        <div
                            class="h-6 w-24 animate-pulse rounded bg-primary/10"
                        ></div>
                    </div>
                </div>
                <div class="flex flex-col gap-2">
                    <div
                        class="h-2 w-full animate-pulse rounded-full bg-primary/10"
                    ></div>
                    <div
                        class="h-3 w-1/2 animate-pulse rounded bg-primary/10"
                    ></div>
                </div>
            </div>
        </div>

        <!-- Main Content Grid Skeleton -->
        <div class="grid grid-cols-1 gap-8 lg:grid-cols-3">
            <!-- Left Column: Leaderboard -->
            <div class="flex flex-col gap-8 lg:col-span-2">
                <div
                    class="flex flex-col gap-4 rounded-xl border border-border/10 bg-card/30 p-5"
                >
                    <div class="flex items-center justify-between">
                        <div
                            class="h-5 w-36 animate-pulse rounded bg-primary/10"
                        ></div>
                        <div
                            class="h-5 w-20 animate-pulse rounded bg-primary/10"
                        ></div>
                    </div>
                    <div
                        v-for="j in 4"
                        :key="j"
                        class="flex items-center gap-3 rounded-lg p-2"
                    >
                        <div
                            class="h-8 w-8 animate-pulse rounded-full bg-primary/10"
                        ></div>
                        <div class="flex flex-1 flex-col gap-1.5">
                            <div
                                class="h-3.5 w-28 animate-pulse rounded bg-primary/10"
                            ></div>
                            <div
                                class="h-2.5 w-20 animate-pulse rounded bg-primary/10"
                            ></div>
                        </div>
                        <div
                            class="h-3.5 w-16 animate-pulse rounded bg-primary/10"
                        ></div>
                    </div>
                </div>
            </div>

            <!-- Right Column: Activity -->
            <div class="flex flex-col gap-6">
                <div
                    class="flex flex-col gap-3 rounded-xl border border-border/10 bg-card/30 p-5"
                >
                    <div
                        class="h-4 w-24 animate-pulse rounded bg-primary/10"
                    ></div>
                    <div class="grid grid-cols-7 gap-1">
                        <div
                            v-for="l in 35"
                            :key="l"
                            class="aspect-square animate-pulse rounded-sm bg-primary/10"
                        ></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>
