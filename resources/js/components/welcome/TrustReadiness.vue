<script setup lang="ts">
import {
    ArchiveRestore,
    Eye,
    KeyRound,
    LockKeyhole,
    ServerCog,
    ShieldCheck,
} from 'lucide-vue-next';

const readinessItems = [
    {
        title: 'Role-Based Access',
        detail: 'Different surfaces for students, teachers, and administrators.',
        icon: ShieldCheck,
    },
    {
        title: 'Section Protection',
        detail: 'Class enrollment can be guarded with section passwords.',
        icon: KeyRound,
    },
    {
        title: 'Secure Sign-In',
        detail: 'Authentication flows include verified accounts and two-factor support.',
        icon: LockKeyhole,
    },
    {
        title: 'Admin Monitoring',
        detail: 'School staff can review usage, submissions, and live assessment activity.',
        icon: Eye,
    },
    {
        title: 'Backup Controls',
        detail: 'Restore workflows help protect school data and operations.',
        icon: ArchiveRestore,
    },
    {
        title: 'System Settings',
        detail: 'AI and platform controls stay in an admin-managed area.',
        icon: ServerCog,
    },
];
</script>

<template>
    <section class="reveal-section mt-24 lg:mt-40">
        <div
            class="grid gap-8 border-y border-border/20 py-12 lg:grid-cols-[0.85fr_1.15fr] lg:gap-14 lg:py-16"
        >
            <div>
                <div class="mb-5 flex items-center gap-4">
                    <div class="h-px w-12 bg-primary"></div>
                    <p
                        class="text-[10px] font-black tracking-[0.4em] text-primary uppercase"
                        data-scramble
                    >
                        School Readiness
                    </p>
                </div>
                <h2
                    class="text-3xl font-black tracking-tight uppercase sm:text-4xl lg:text-5xl"
                >
                    Built to feel safe before it feels flashy.
                </h2>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <div
                    v-for="item in readinessItems"
                    :key="item.title"
                    class="group border border-border/25 bg-card/50 p-5 transition-colors hover:border-primary/35 hover:bg-muted/30"
                >
                    <div
                        class="mb-4 flex h-10 w-10 items-center justify-center border border-primary/20 bg-primary/10"
                    >
                        <component
                            :is="item.icon"
                            class="h-5 w-5 text-primary"
                        />
                    </div>
                    <h3
                        class="mb-2 text-sm font-black tracking-widest uppercase"
                    >
                        {{ item.title }}
                    </h3>
                    <p class="text-sm leading-relaxed text-muted-foreground">
                        {{ item.detail }}
                    </p>
                </div>
            </div>
        </div>
    </section>
</template>
