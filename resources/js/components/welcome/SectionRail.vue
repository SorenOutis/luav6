<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';

const sections = [
    { id: 'top', label: 'Intro' },
    { id: 'manifesto', label: 'Why LSI' },
    { id: 'how-it-works', label: 'The loop' },
    { id: 'capabilities', label: 'Capabilities' },
    { id: 'safety', label: 'Human in the loop' },
    { id: 'pricing', label: 'Pricing' },
    { id: 'faq', label: 'FAQ' },
    { id: 'updates', label: 'Updates' },
    { id: 'contact', label: 'Get started' },
];

const activeId = ref('top');
let observer: IntersectionObserver | undefined;

/**
 * Highlights the section crossing the middle of the viewport. Desktop-only
 * (the rail is hidden below xl), and it simply stays on "Intro" where
 * IntersectionObserver is unavailable.
 */
onMounted(() => {
    if (typeof IntersectionObserver === 'undefined') {
        return;
    }

    observer = new IntersectionObserver(
        (entries) => {
            const visible = entries.find((entry) => entry.isIntersecting);

            if (visible) {
                activeId.value = visible.target.id;
            }
        },
        { rootMargin: '-45% 0px -50% 0px' },
    );

    sections.forEach(({ id }) => {
        const el = document.getElementById(id);

        if (el) {
            observer?.observe(el);
        }
    });
});

onBeforeUnmount(() => observer?.disconnect());

const jumpTo = (event: MouseEvent, id: string) => {
    const el = document.getElementById(id);

    if (!el) {
        return;
    }

    event.preventDefault();
    el.scrollIntoView({ behavior: 'smooth', block: 'start' });
    history.replaceState(
        null,
        '',
        id === 'top' ? window.location.pathname : `#${id}`,
    );
};
</script>

<template>
    <nav
        aria-label="Page sections"
        class="section-rail fixed top-1/2 right-5 z-40 hidden -translate-y-1/2 xl:block"
        data-testid="section-rail"
    >
        <ol class="flex flex-col items-end gap-3">
            <li v-for="(section, index) in sections" :key="section.id">
                <a
                    :href="`#${section.id}`"
                    class="rail-link group flex items-center gap-3 text-white"
                    :class="{ 'is-active': activeId === section.id }"
                    :aria-current="activeId === section.id ? 'true' : undefined"
                    @click="jumpTo($event, section.id)"
                >
                    <span
                        class="rail-label font-mono text-[10px] tracking-wider uppercase"
                        >{{ String(index).padStart(2, '0') }} ·
                        {{ section.label }}</span
                    >
                    <span
                        class="rail-dot block h-1.5 rounded-full bg-current"
                    ></span>
                </a>
            </li>
        </ol>
    </nav>
</template>

<style scoped>
.section-rail {
    mix-blend-mode: difference;
}

.rail-link {
    opacity: 0.4;
    transition: opacity var(--dur-hover, 260ms) ease;
}

.rail-link:hover,
.rail-link:focus-visible,
.rail-link.is-active {
    opacity: 1;
}

.rail-dot {
    width: 0.375rem;
    transition: width 0.4s var(--ease-out-expo);
}

.rail-link.is-active .rail-dot {
    width: 1.25rem;
}

.rail-label {
    opacity: 0;
    transform: translateX(4px);
    transition:
        opacity var(--dur-hover, 260ms) ease,
        transform var(--dur-hover, 260ms) var(--ease-out-expo);
}

.rail-link:hover .rail-label,
.rail-link:focus-visible .rail-label {
    opacity: 1;
    transform: none;
}

@media (prefers-reduced-motion: reduce) {
    .rail-link,
    .rail-dot,
    .rail-label {
        transition: none;
    }
}
</style>
