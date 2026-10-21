import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { mount } from '@vue/test-utils';
import type { VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import AssessmentArtifact from '@/components/welcome/AssessmentArtifact.vue';
import HeroDemoWindow from '@/components/welcome/HeroDemoWindow.vue';
import WelcomeHero from '@/components/welcome/WelcomeHero.vue';
import heroSource from '@/components/welcome/WelcomeHero.vue?raw';
import welcomeSource from '@/pages/Welcome.vue?raw';

const appCss = readFileSync(
    resolve(process.cwd(), 'resources/css/app.css'),
    'utf8',
);

describe('Welcome page Echo AI integration', () => {
    /** Echo thinks, then greets, before Maria's cursor appears. */
    const GREETING_PLUS_START_MS = 1700;

    /**
     * Phase budgets are sized from each scenario's own copy, so the tests step
     * forward until the phase flips instead of hardcoding millisecond counts.
     */
    const advanceToPhase = async (
        wrapper: VueWrapper,
        target: number,
        budgetMs = 30000,
    ) => {
        let elapsed = 0;

        while (elapsed < budgetMs) {
            if (Number(wrapper.attributes('data-phase')) === target) {
                return elapsed;
            }

            await vi.advanceTimersByTimeAsync(50);
            elapsed += 50;
        }

        expect(wrapper.attributes('data-phase')).toBe(String(target));
    };

    const releaseEntrance = (wrapper: VueWrapper) =>
        wrapper
            .find('#echo-demo')
            .trigger('animationend', { animationName: 'hero-window-in' });

    /** Steps the clock forward until the predicate holds. */
    const advanceUntil = async (predicate: () => boolean, budgetMs = 15000) => {
        let elapsed = 0;

        while (!predicate() && elapsed < budgetMs) {
            await vi.advanceTimersByTimeAsync(50);
            elapsed += 50;
        }

        expect(predicate()).toBe(true);
    };

    beforeEach(() => {
        vi.stubGlobal('matchMedia', () => ({
            matches: false,
            addEventListener: vi.fn(),
            removeEventListener: vi.fn(),
        }));
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('renders animated Echo pill in WelcomeHero', () => {
        const wrapper = mount(WelcomeHero, {
            props: {
                canRegister: true,
                auth: { user: null },
                dashboard: () => '/dashboard',
                login: () => '/login',
                register: () => '/register',
            },
            global: {
                stubs: {
                    Link: { template: '<a><slot /></a>' },
                    Motion: { template: '<div><slot /></div>' },
                    FoxCompanion: { template: '<div data-fox />' },
                    AssessmentArtifact: { template: '<div data-artifact />' },
                },
            },
        });

        expect(wrapper.find('a[href="#how-it-works"]').exists()).toBe(true);
        expect(wrapper.text()).not.toContain('Interactive Demo');
        expect(wrapper.text()).toContain(
            'Meet Echo · Intelligent Study Companion',
        );
        expect(wrapper.find('.wolf-persona').exists()).toBe(true);
        expect(wrapper.text()).toContain('Make every assessment count.');
        wrapper.unmount();
    });

    it.each([true, false])(
        'shows guest login when canRegister is %s',
        (canRegister) => {
            const wrapper = mount(WelcomeHero, {
                props: {
                    canRegister,
                    auth: { user: null },
                    dashboard: () => '/dashboard',
                    login: () => '/login',
                    register: () => '/register',
                },
                global: {
                    stubs: {
                        Link: {
                            props: ['href'],
                            template: '<a :href="href"><slot /></a>',
                        },
                        Motion: { template: '<div><slot /></div>' },
                        ChatAiOrb: true,
                    },
                },
            });

            expect(wrapper.get('a[href="/login"]').text()).toBe('Log in');
            expect(wrapper.find('a[href="/register"]').exists()).toBe(
                canRegister,
            );
            if (canRegister) {
                expect(wrapper.get('a[href="/register"]').text()).toBe(
                    'Create free account',
                );
            }
            expect(wrapper.find('a[href="/dashboard"]').exists()).toBe(false);
            wrapper.unmount();
        },
    );

    it('shows dashboard instead of guest actions for authenticated users', () => {
        const wrapper = mount(WelcomeHero, {
            props: {
                canRegister: true,
                auth: { user: { id: 1 } },
                dashboard: () => '/dashboard',
                login: () => '/login',
                register: () => '/register',
            },
            global: {
                stubs: {
                    Link: {
                        props: ['href'],
                        template: '<a :href="href"><slot /></a>',
                    },
                    Motion: { template: '<div><slot /></div>' },
                    ChatAiOrb: true,
                },
            },
        });

        expect(wrapper.get('a[href="/dashboard"]').text()).toBe(
            'Open dashboard',
        );
        expect(wrapper.find('a[href="/login"]').exists()).toBe(false);
        expect(wrapper.find('a[href="/register"]').exists()).toBe(false);
        wrapper.unmount();
    });

    it('renders mini Echo persona in AssessmentArtifact and toggles approval', async () => {
        const wrapper = mount(AssessmentArtifact);
        expect(wrapper.text()).toContain('Echo AI Drafted Feedback');
        expect(wrapper.find('.wolf-persona').exists()).toBe(true);
        expect(wrapper.text()).toContain('Teacher Review Required');

        const button = wrapper.find('[data-testid="approve-feedback-button"]');
        await button.trigger('click');
        expect(wrapper.text()).toContain('Approved ✓');
        expect(wrapper.text()).toContain('Verified by Teacher');
        expect(wrapper.text()).toContain('25/28 Completed');
    });

    it('holds the scripted conversation until the hero entrance animation settles', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        // While the window animates in, the chat stage is masked by the
        // boot placeholder and the script has not started yet.
        expect(wrapper.attributes('data-phase')).toBe('0');
        expect(wrapper.text()).toContain('Loading preview');
        expect(wrapper.findAll('.hdw-stage.is-hidden')).toHaveLength(2);

        // Nested animations bubble to the window — they must not release it.
        await wrapper
            .find('.demo-dot')
            .trigger('animationend', { animationName: 'demo-dot' });
        await vi.advanceTimersByTimeAsync(1400);
        expect(wrapper.attributes('data-phase')).toBe('0');
        expect(wrapper.findAll('.hdw-stage.is-hidden')).toHaveLength(2);

        // The window's own entrance ends → the script boots.
        await releaseEntrance(wrapper);
        expect(wrapper.findAll('.hdw-stage.is-hidden')).toHaveLength(0);
        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Awaiting request',
        );

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('shows Echo thinking before it greets Maria once the entrance finishes', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);

        expect(wrapper.text()).toContain('Echo is thinking');
        expect(wrapper.text()).not.toContain('Hi Maria!');
        expect(wrapper.find('.wolf-persona').attributes('data-motion')).toBe(
            'thinking',
        );

        await vi.advanceTimersByTimeAsync(800);
        expect(wrapper.text()).toContain(
            'Hi Maria! Ready to build tomorrow’s quiz?',
        );
        expect(wrapper.text()).not.toContain('Echo is thinking');

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('frames the demo as the teacher talking to Echo', async () => {
        const wrapper = mount(HeroDemoWindow);

        expect(wrapper.text()).toContain('Maria Santos · Teacher');
        expect(wrapper.text()).toContain('Grade 8 · 3 sections · 84 learners');
        expect(wrapper.text()).toContain('Teaching Copilot');
        expect(wrapper.text()).not.toContain('Maria Santos · Student');
        expect(wrapper.text()).not.toContain('Formative Companion');

        wrapper.unmount();
    });

    it('types Maria’s request slowly enough to follow', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);
        // Echo thinks, then greets, before Maria starts typing.
        await vi.advanceTimersByTimeAsync(GREETING_PLUS_START_MS);

        const promptInput = wrapper.get('[data-testid="prompt-input"]');
        const total = 'Build me a 15-item quiz on quadratics for tomorrow.'
            .length;

        // A believable hand: a fraction of the line per 200ms, never a flood.
        await vi.advanceTimersByTimeAsync(200);
        const firstTick = promptInput.text().trim().length;
        expect(firstTick).toBeGreaterThan(0);
        expect(firstTick).toBeLessThan(total / 2);

        // Still mid-sentence 700ms in — the old 22ms tick had finished by now.
        await vi.advanceTimersByTimeAsync(700);
        const midFlight = promptInput.text().trim().length;
        expect(midFlight).toBeLessThan(total);
        expect(midFlight).toBeGreaterThan(firstTick);
        expect(wrapper.find('.typing-cursor').exists()).toBe(true);

        // And it does finish inside the phase.
        await advanceToPhase(wrapper, 1);
        expect(wrapper.text()).toContain(
            'Build me a 15-item quiz on quadratics for tomorrow.',
        );

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('holds the working skeleton until Echo’s first character lands', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);

        // Phase 2 — Echo is visibly working, skeleton on, answer off.
        await advanceToPhase(wrapper, 2);
        expect(wrapper.text()).toContain(
            'Echo is aligning items to your syllabus',
        );
        expect(wrapper.find('.hdw-skeleton.is-hidden').exists()).toBe(false);
        expect(wrapper.find('.hdw-draft.is-on').exists()).toBe(false);

        // Phase 3 opens on the skeleton, not an empty reply bubble.
        await advanceToPhase(wrapper, 3);
        expect(wrapper.find('.hdw-skeleton.is-hidden').exists()).toBe(false);
        expect(wrapper.find('.hdw-draft.is-on').exists()).toBe(false);

        // First characters land, then the answer takes over — partially
        // typed, so the bubble is never rendered empty.
        await vi.advanceTimersByTimeAsync(700);
        expect(wrapper.find('.hdw-draft.is-on').exists()).toBe(true);
        expect(wrapper.find('.hdw-skeleton.is-hidden').exists()).toBe(true);

        const firstLine = wrapper.find('.hdw-type-line').text();
        expect(firstLine.startsWith('Drafted 15')).toBe(true);
        expect(firstLine.length).toBeLessThan(
            'Drafted 15 items across three difficulty tiers.'.length,
        );

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('streams Echo’s answer line by line instead of flooding the bubble', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);
        await advanceToPhase(wrapper, 3);
        await vi.advanceTimersByTimeAsync(700);

        const landed = () =>
            wrapper.findAll('.hdw-type-line').map((line) => line.text());

        expect(landed()).toHaveLength(1);
        expect(wrapper.text()).not.toContain('Every item maps to');

        // The next line only appears once the first has finished streaming.
        let previous = landed()[0].length;
        let guard = 0;
        while (landed().length === 1 && guard < 200) {
            await vi.advanceTimersByTimeAsync(100);
            guard += 1;
            previous = previous + 1;
        }

        expect(landed().length).toBeGreaterThan(1);
        expect(landed()[0].length).toBe(
            'Drafted 15 items across three difficulty tiers.'.length,
        );
        expect(previous).toBeGreaterThan(0);

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('cycles the hero demo through request, work, and confirmation phases', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        expect(wrapper.attributes('data-phase')).toBe('0');
        expect(wrapper.find('.wolf-persona').exists()).toBe(true);

        await releaseEntrance(wrapper);

        // Maria's request lands with the result pill.
        await advanceToPhase(wrapper, 1);
        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Request sent',
        );
        expect(wrapper.text()).toContain('15 items ready');

        // Echo starts working — orb switches to thinking.
        await advanceToPhase(wrapper, 2);
        expect(wrapper.find('.wolf-persona').attributes('data-motion')).toBe(
            'thinking',
        );

        // Answer streams in — awaiting the teacher's confirmation.
        await advanceToPhase(wrapper, 3);
        expect(wrapper.find('.wolf-persona').attributes('data-motion')).toBe(
            'micro',
        );
        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Needs your OK',
        );

        // Confirmed — it reaches the class, loop completes before restarting.
        await advanceToPhase(wrapper, 4);
        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Sent to class',
        );
        expect(wrapper.text()).toContain('Delivered to class ✓');
        expect(wrapper.text()).toContain(
            '“Checked against last term’s item bank.”',
        );

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('replays the opening beat when the teacher switches conversations', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);

        // The first conversation gets as far as Echo working.
        await advanceToPhase(wrapper, 2);

        const conversations = wrapper.findAll(
            'nav[aria-label="Demo conversations"] button',
        );
        await conversations[1].trigger('click');

        // The new conversation restarts from the top instead of inheriting
        // the finished turn of the one it replaced.
        expect(wrapper.attributes('data-phase')).toBe('0');
        expect(wrapper.text()).toContain('Echo is thinking');
        expect(wrapper.find('.hdw-score.is-on').exists()).toBe(false);

        // Echo greets the new topic.
        await vi.advanceTimersByTimeAsync(1600);
        expect(wrapper.text()).not.toContain('Echo is thinking');
        expect(wrapper.get('[data-testid="greeting-text"]').text()).toBe(
            'Hi Maria! Want a live look at Section Diamond? I can pull it right now.',
        );

        // Then Maria types her request, letter by letter.
        const promptInput = wrapper.get('[data-testid="prompt-input"]');
        await vi.advanceTimersByTimeAsync(200);
        const firstTick = promptInput.text().trim();
        expect(firstTick.length).toBeGreaterThan(0);
        expect(firstTick.length).toBeLessThan(
            'Who has actually logged in today?'.length / 2,
        );
        expect(wrapper.find('.typing-cursor').exists()).toBe(true);

        // The opening beat keeps its own budget — the turn only lands once the
        // request is fully typed, not on the leftover of the phase it
        // replaced.
        await vi.advanceTimersByTimeAsync(1200);
        expect(wrapper.attributes('data-phase')).toBe('0');
        expect(wrapper.find('.hdw-score.is-on').exists()).toBe(false);

        await advanceToPhase(wrapper, 1);
        expect(wrapper.text()).toContain('21 of 28 active');
        expect(wrapper.text()).toContain('Who has actually logged in today?');

        vi.useRealTimers();
        wrapper.unmount();
    });
    it('plays Maria pressing Enter before the request leaves the composer', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);

        const sendButton = wrapper.get('button[aria-label="Send message"]');
        const pointer = wrapper.get('[data-testid="demo-cursor"]');
        const isPressing = () => sendButton.classes().includes('is-pressing');
        const pointerPressed = () => pointer.classes().includes('is-pressing');

        // The pointer is on screen, waiting next to the keyboard.
        expect(pointer.classes()).toContain('is-visible');
        expect(isPressing()).toBe(false);

        // Her request lands and the key lights up.
        await advanceUntil(() => sendButton.classes().includes('scale-105'));
        expect(isPressing()).toBe(false);

        // The pointer glides onto the key and clicks it.
        await advanceUntil(isPressing);
        expect(pointerPressed()).toBe(true);

        // The press releases into the sent turn — the pointer stays put.
        await advanceToPhase(wrapper, 1);
        expect(isPressing()).toBe(false);
        expect(pointer.classes()).toContain('is-visible');
        expect(wrapper.find('.hdw-score.is-on').exists()).toBe(true);

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('moves the pointer to the next conversation instead of jumping threads', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);

        const rows = wrapper.findAll(
            'nav[aria-label="Demo conversations"] button',
        );
        const activeRow = () =>
            rows.findIndex((row) => row.classes().includes('bg-card'));
        const pointer = wrapper.get('[data-testid="demo-cursor"]');

        expect(activeRow()).toBe(0);

        // The first thread plays out to the confirmation.
        await advanceToPhase(wrapper, 4);

        // The pointer crosses to the next thread — the history lights it up.
        await advanceUntil(() => rows[1].classes().includes('bg-secondary/50'));
        expect(activeRow()).toBe(0);

        // It clicks, and the next conversation opens.
        await advanceUntil(() => activeRow() === 1);
        expect(wrapper.text()).toContain('Section Diamond · Roster');

        // The pointer goes back to the keyboard for the next request.
        expect(pointer.classes()).toContain('is-visible');

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('keeps the chat history in step with the live conversation', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);

        const rows = wrapper.findAll(
            'nav[aria-label="Demo conversations"] button',
        );
        const activeRow = () => rows[0].text();

        // Echo is thinking, and the history says so.
        expect(activeRow()).toContain('Echo is typing…');

        // The closed conversations keep their own last line.
        expect(rows[1].text()).toContain(
            '21 of 28 learners opened a task today.',
        );
        expect(rows[2].text()).toContain(
            '6 of 28 learners sit below 70% on quadratics.',
        );
        expect(rows[3].text()).toContain(
            'Compiled 28 learners across your three sections.',
        );

        // Once Maria is typing, the history follows her request live.
        await vi.advanceTimersByTimeAsync(2000);
        expect(activeRow()).toContain('You: ');

        // And the delivered turn shows up in the history too.
        await advanceToPhase(wrapper, 4);
        expect(activeRow()).toContain('Sent to class ✓');

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('cycles through every teacher task the demo advertises', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);

        const sidebar = wrapper.find('nav[aria-label="Demo conversations"]');
        expect(sidebar.text()).toContain('Quadratics · Quiz Builder');
        expect(sidebar.text()).toContain('Section Diamond · Roster');
        expect(sidebar.text()).toContain('Grade 8 · Analytics');
        expect(sidebar.text()).toContain('Grade 8 · Reports');

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('parks the hero demo on the finished state when motion is reduced', () => {
        const wrapper = mount(HeroDemoWindow, {
            props: { prefersReducedMotion: true },
        });

        expect(wrapper.attributes('data-phase')).toBe('4');
        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Sent to class',
        );
        wrapper.unmount();
    });

    it('keeps the scripted pointer gliding on low-end devices', () => {
        // The low-end guard collapses every transition to 0.01ms, which turns
        // the pointer into a teleport. It has to stay excluded from it.
        const guard = appCss.slice(
            appCss.indexOf('html[data-low-end] *:not('),
            appCss.indexOf('html[data-low-end] .backdrop-blur-sm'),
        );

        expect(guard).toContain('.hdw-cursor');
        expect(guard).toContain('.hdw-enter');
    });

    it('mounts the scripted demo inside the WelcomeHero flow', () => {
        expect(heroSource).toContain('HeroDemoWindow');
        expect(welcomeSource).toContain('<WelcomeHero');
    });
});
