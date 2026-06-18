<script setup lang="ts">
import { Check, LayoutTemplate, Palette, Type } from 'lucide-vue-next';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { useAccessibility } from '@/composables/useAccessibility';
import { useAppearance } from '@/composables/useAppearance';

const {
    themePreset,
    fontPreset,
    cardStylePreset,
    themePresets,
    fontPresets,
    cardStylePresets,
    updateThemePreset,
    updateFontPreset,
    updateCardStylePreset,
} = useAppearance();

const { isDyslexiaFriendly, toggleDyslexiaMode } = useAccessibility();
</script>

<template>
    <DropdownMenu>
        <DropdownMenuTrigger as-child>
            <button
                class="inline-flex items-center justify-center rounded-md p-1.5 text-sm font-medium text-muted-foreground transition-colors hover:bg-neutral-100 hover:text-foreground sm:p-2 dark:hover:bg-neutral-800"
                :class="isDyslexiaFriendly ? 'text-primary' : ''"
                aria-label="Open appearance settings"
            >
                <span class="relative text-[10px] font-black sm:text-xs">
                    Aa
                    <span
                        v-if="isDyslexiaFriendly"
                        class="absolute -bottom-1 left-0 h-0.5 w-full bg-primary"
                    ></span>
                </span>
            </button>
        </DropdownMenuTrigger>

        <DropdownMenuContent
            align="end"
            class="w-80 border-border/60 bg-background/95 p-3 backdrop-blur-xl"
        >
            <div class="mb-3 flex items-start justify-between gap-3 px-1">
                <div>
                    <p
                        class="text-xs font-black tracking-[0.22em] text-foreground uppercase"
                    >
                        Appearance
                    </p>
                    <p
                        class="mt-1 text-[11px] leading-relaxed text-muted-foreground"
                    >
                        Choose a dashboard theme, font, and card treatment.
                    </p>
                </div>
            </div>

            <section class="space-y-2">
                <div
                    class="flex items-center gap-2 px-1 text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase"
                >
                    <Palette class="h-3.5 w-3.5" />
                    Theme
                </div>

                <div class="grid gap-2">
                    <button
                        v-for="preset in themePresets"
                        :key="preset.id"
                        type="button"
                        class="group flex items-center justify-between gap-3 rounded-xl border p-2.5 text-left transition-all hover:border-primary/40 hover:bg-muted/50"
                        :class="
                            themePreset === preset.id
                                ? 'border-primary/50 bg-primary/10 text-foreground'
                                : 'border-border/50 bg-card/40 text-muted-foreground'
                        "
                        @click="updateThemePreset(preset.id)"
                    >
                        <span class="min-w-0">
                            <span
                                class="block text-xs font-black tracking-[0.16em] uppercase"
                                >{{ preset.name }}</span
                            >
                            <span class="mt-0.5 block truncate text-[10px]">{{
                                preset.description
                            }}</span>
                        </span>

                        <span class="flex shrink-0 items-center gap-1.5">
                            <span
                                class="flex overflow-hidden rounded-full border border-border/60"
                            >
                                <span
                                    v-for="swatch in preset.swatches"
                                    :key="swatch"
                                    class="h-4 w-4"
                                    :style="{ backgroundColor: swatch }"
                                ></span>
                            </span>
                            <Check
                                v-if="themePreset === preset.id"
                                class="h-4 w-4 text-primary"
                            />
                        </span>
                    </button>
                </div>
            </section>

            <DropdownMenuSeparator class="my-3" />

            <section class="space-y-2">
                <div
                    class="flex items-center gap-2 px-1 text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase"
                >
                    <Type class="h-3.5 w-3.5" />
                    Font
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="preset in fontPresets"
                        :key="preset.id"
                        type="button"
                        class="flex items-center justify-between rounded-xl border px-3 py-2 transition-all hover:border-primary/40 hover:bg-muted/50"
                        :class="
                            fontPreset === preset.id
                                ? 'border-primary/50 bg-primary/10 text-foreground'
                                : 'border-border/50 bg-card/40 text-muted-foreground'
                        "
                        @click="updateFontPreset(preset.id)"
                    >
                        <span class="text-[11px] font-bold">{{
                            preset.name
                        }}</span>
                        <span
                            class="text-sm font-black"
                            :class="{
                                'font-serif': preset.id === 'academic',
                                'font-mono': preset.id === 'mono',
                            }"
                            :style="
                                preset.id === 'rounded'
                                    ? {
                                          fontFamily:
                                              'Nunito, Aptos Rounded, Arial Rounded MT Bold, ui-sans-serif, system-ui, sans-serif',
                                      }
                                    : undefined
                            "
                        >
                            {{ preset.sample }}
                        </span>
                    </button>
                </div>
            </section>

            <DropdownMenuSeparator class="my-3" />

            <section class="space-y-2">
                <div
                    class="flex items-center gap-2 px-1 text-[10px] font-black tracking-[0.2em] text-muted-foreground uppercase"
                >
                    <LayoutTemplate class="h-3.5 w-3.5" />
                    Cards
                </div>

                <div class="grid grid-cols-2 gap-2">
                    <button
                        v-for="preset in cardStylePresets"
                        :key="preset.id"
                        type="button"
                        class="rounded-xl border p-2 text-left transition-all hover:border-primary/40 hover:bg-muted/50"
                        :class="
                            cardStylePreset === preset.id
                                ? 'border-primary/50 bg-primary/10 text-foreground'
                                : 'border-border/50 bg-card/40 text-muted-foreground'
                        "
                        @click="updateCardStylePreset(preset.id)"
                    >
                        <span
                            class="block text-[11px] font-black tracking-[0.14em] uppercase"
                            >{{ preset.name }}</span
                        >
                        <span class="mt-1 block text-[10px] leading-snug">{{
                            preset.description
                        }}</span>
                    </button>
                </div>
            </section>

            <DropdownMenuSeparator class="my-3" />

            <button
                type="button"
                class="flex w-full items-center justify-between rounded-xl border border-border/50 bg-card/40 px-3 py-2 text-left transition-colors hover:border-primary/40 hover:bg-muted/50"
                @click="toggleDyslexiaMode"
            >
                <span>
                    <span
                        class="block text-[11px] font-black tracking-[0.16em] text-foreground uppercase"
                        >Dyslexia friendly</span
                    >
                    <span class="mt-0.5 block text-[10px] text-muted-foreground"
                        >Simpler text casing and spacing.</span
                    >
                </span>
                <span
                    class="relative h-5 w-9 rounded-full transition-colors"
                    :class="isDyslexiaFriendly ? 'bg-primary' : 'bg-muted'"
                >
                    <span
                        class="absolute top-0.5 h-4 w-4 rounded-full bg-background shadow transition-transform"
                        :class="
                            isDyslexiaFriendly
                                ? 'translate-x-4'
                                : 'translate-x-0.5'
                        "
                    ></span>
                </span>
            </button>
        </DropdownMenuContent>
    </DropdownMenu>
</template>
