import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { defineComponent, h } from 'vue';
import FeatureCards from '@/components/welcome/FeatureCards.vue';
import TechStackCarousel from '@/components/welcome/TechStackCarousel.vue';

const root = process.cwd();
const read = (rel: string) => readFileSync(join(root, rel), 'utf8');

const LinkStub = defineComponent({
    props: { href: { type: String, default: '#' } },
    setup(props, { slots }) {
        return () => h('a', { href: props.href }, slots.default?.());
    },
});

const Passthrough = defineComponent({
    setup(_, { slots }) {
        return () => h('div', slots.default?.());
    },
});

describe('welcome mobile performance', () => {
    it('stamps data-low-end in the HTML boot script before assets load', () => {
        const blade = read('resources/views/app.blade.php');

        expect(blade).toContain("setAttribute('data-low-end'");
        expect(blade).toContain('(pointer: coarse)');
        expect(blade).toContain('deviceMemory');
    });

    it('applies low-end CSS that kills backdrop-filter and looping animation', () => {
        const css = read('resources/css/app.css');

        expect(css).toContain('html[data-low-end] *');
        expect(css).toContain('backdrop-filter: none !important');
        expect(css).toContain('content-visibility: auto');
        expect(css).toContain('.welcome-bg-grid');
        expect(css).toContain('touch-action: manipulation');
    });

    it('keeps the welcome page free of autoplay media and expensive hero effects', () => {
        const page = read('resources/js/pages/Welcome.vue');
        const hero = read('resources/js/components/welcome/WelcomeHero.vue');

        expect(hero).toContain('Make every assessment count.');
        expect(page).not.toContain('walkthroughUnlocked');
        expect(page).not.toContain('how-it-works.mp4');
        expect(page).not.toContain('welcome-bg-grid');
        expect(page).not.toContain('NeuralParticleNetwork');
        expect(page).not.toContain('DemoVideoModal');
    });

    it('skips the decorative feature-card bars when motion is reduced', () => {
        const wrapper = mount(FeatureCards, {
            props: {
                isCoarsePointer: true,
                prefersReducedMotion: true,
                auth: { user: null },
                dashboard: () => '/dashboard',
                login: () => '/login',
            },
            global: { stubs: { Link: LinkStub, Motion: Passthrough } },
        });

        expect(wrapper.classes()).toContain('lite-motion');
        expect(wrapper.findAll('.fragment-bar')).toHaveLength(0);
        expect(wrapper.text()).toContain('The work stays clear.');
        wrapper.unmount();
    });

    it('keeps the welcome page content practical and link-safe', () => {
        const page = read('resources/js/pages/Welcome.vue');
        const hero = read('resources/js/components/welcome/WelcomeHero.vue');
        const footer = read(
            'resources/js/components/welcome/WelcomeFooter.vue',
        );

        expect(hero).toContain('Make every assessment count.');
        expect(page).toContain('From response to next step.');
        expect(page).toContain('id="how-it-works"');
        expect(page).toContain('id="contact"');
        expect(page).not.toContain('TechStackCarousel');
        expect(page).not.toContain('NeuralParticleNetwork');
        expect(page).not.toContain('DemoVideoModal');
        expect(footer).not.toContain("href: '#'");
        expect(footer).not.toContain('LSI Academic Engine');
    });

    it('renders a static tech-stack grid on coarse-pointer devices', () => {
        const wrapper = mount(TechStackCarousel, {
            props: {
                isCoarsePointer: true,
                prefersReducedMotion: true,
            },
        });

        expect(wrapper.find('.carousel-mask').exists()).toBe(false);
        expect(wrapper.text()).toContain('Laravel 12');
        expect(wrapper.text()).toContain('Vue 3');
        // One of each chip — the desktop marquee duplicates every row.
        expect(wrapper.text().match(/Laravel 12/g)?.length).toBe(1);
        wrapper.unmount();
    });

    it('skips GSAP on the about page and auth layouts for low-end devices', () => {
        const about = read('resources/js/pages/About.vue');
        const footer = read(
            'resources/js/components/welcome/WelcomeFooter.vue',
        );
        const split = read('resources/js/layouts/auth/AuthSplitLayout.vue');
        const simple = read('resources/js/layouts/auth/AuthSimpleLayout.vue');
        const header = read(
            'resources/js/components/welcome/WelcomeHeader.vue',
        );
        const nav = read('resources/js/components/MobileNav.vue');

        expect(about).toContain('reduceMotion');
        expect(about).toContain('while-in-view');
        expect(footer).toContain('isLowEndDeviceSignal()');
        expect(split).toContain('isLowEndDeviceSignal()');
        expect(simple).toContain('isLowEndDeviceSignal()');
        expect(header).toContain('liteMotion');
        expect(header).toContain('md:backdrop-blur-xl');
        expect(nav).not.toContain('backdrop-blur-3xl');
    });
});
