<script setup lang="ts">
import { LayoutDashboard, BookOpen, Activity, ClipboardCheck, ArrowRight } from 'lucide-vue-next';

const quickLinks = [
    { label: 'Exam Directory', href: '#', icon: BookOpen },
    { label: 'Assessment Analytics', href: '#', icon: Activity },
    { label: 'Evaluation Logs', href: '#', icon: ClipboardCheck },
];
</script>

<template>
    <section class="pulse-panel relative overflow-hidden rounded-2xl border border-border/30 dark:border-border/15 bg-card/60 dark:bg-background/50 p-6 sm:p-8 lg:p-10 backdrop-blur-2xl flex flex-col group/env">
        <div class="pointer-events-none absolute -right-20 -top-20 h-60 w-60 rounded-full bg-primary/5 dark:bg-primary/10 blur-3xl transition-transform duration-1000 group-hover/env:scale-150"></div>
        
        <div class="relative z-10 mb-8">
            <div class="flex items-center gap-2 mb-2">
                <LayoutDashboard class="h-3 w-3 text-primary/60" />
                <p class="text-[10px] font-black uppercase tracking-[0.25em] text-primary/80">Control Center</p>
            </div>
            <h3 class="text-xl sm:text-2xl font-black tracking-tight uppercase">Operational <br/> Nodes</h3>
            <p class="mt-4 text-xs leading-relaxed text-muted-foreground/80 max-w-[280px]">AI-powered evaluation and secure exam handling integrated into your classroom.</p>
        </div>

        <div class="mt-auto space-y-3 relative z-10">
            <a
                v-for="quickLink in quickLinks"
                :key="quickLink.label"
                :href="quickLink.href"
                class="quick-link-row group/link relative flex items-center justify-between rounded-xl border border-border/20 dark:border-border/10 px-5 py-4 transition-all hover:bg-muted/40 dark:hover:bg-foreground/[0.06] hover:border-primary/30 overflow-hidden"
            >
                <div class="absolute inset-0 -translate-x-full group-hover/link:translate-x-full transition-transform duration-700 bg-gradient-to-r from-transparent via-primary/5 to-transparent"></div>
                
                <span class="flex items-center gap-4 relative z-10">
                    <div class="flex h-8 w-8 items-center justify-center rounded-lg bg-muted/30 group-hover/link:bg-primary/10 transition-colors">
                        <component :is="quickLink.icon" class="h-4 w-4 text-muted-foreground group-hover/link:text-primary transition-all duration-300" />
                    </div>
                    <div class="flex flex-col">
                        <span class="text-[10px] font-black uppercase tracking-[0.18em] text-foreground/80 group-hover/link:text-foreground transition-colors">{{ quickLink.label }}</span>
                        <span class="text-[7px] font-bold text-muted-foreground/40 uppercase tracking-widest group-hover/link:text-primary/40 transition-colors">Protocol_Enabled</span>
                    </div>
                </span>
                <div class="flex items-center gap-2">
                    <div class="h-1 w-1 rounded-full bg-primary opacity-0 group-hover/link:opacity-100 transition-opacity animate-pulse"></div>
                    <ArrowRight class="h-4 w-4 text-muted-foreground/40 group-hover/link:text-primary transition-all group-hover/link:translate-x-1" />
                </div>
            </a>
        </div>
        
        <div class="mt-8 pt-4 border-t border-border/10 flex items-center justify-between">
            <span class="text-[8px] font-black text-muted-foreground/40 uppercase tracking-widest">Environment_Secure</span>
            <div class="flex gap-1">
                <div v-for="i in 3" :key="i" class="h-1 w-4 rounded-full bg-emerald-500/20 group-hover/env:bg-emerald-500/40 transition-colors"></div>
            </div>
        </div>
    </section>
</template>
