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
        expect(wrapper.text()).not.toContain('Morning, Maria.');
        expect(wrapper.find('.wolf-persona').attributes('data-motion')).toBe(
            'thinking',
        );

        await vi.advanceTimersByTimeAsync(800);
        expect(wrapper.text()).toContain(
            'Morning, Maria. Tell me what you need and I’ll give each stage to the right specialist.',
        );
        expect(wrapper.text()).not.toContain('Echo is thinking');

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('frames the demo as the teacher talking to Echo', async () => {
        const wrapper = mount(HeroDemoWindow);

        expect(wrapper.text()).toContain('Maria Santos · Teacher');
        expect(wrapper.text()).toContain('Grade 8 · 3 sections · 84 learners');
        expect(wrapper.text()).toContain('Chief of Staff');
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
        const total = 'Create a 15-item quiz on quadratics for Section Diamond.'
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
            'Create a 15-item quiz on quadratics for Section Diamond.',
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
            'The Chief of Staff is assigning this request to three specialists',
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

        const firstTurn = wrapper.find('[data-speaker]');
        expect(firstTurn.exists()).toBe(true);
        expect(firstTurn.attributes('data-speaker')).toBe('Chief of Staff');
        expect(firstTurn.text()).toContain('On it. Curriculu');

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('lets every bot answer in turn inside the team chat', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);
        await advanceToPhase(wrapper, 3);
        const speakers = () =>
            wrapper
                .findAll('[data-speaker]')
                .map((turn) => turn.attributes('data-speaker'));

        await advanceUntil(() => speakers().length >= 1);
        expect(speakers()[0]).toBe('Chief of Staff');

        await advanceUntil(() => speakers().length >= 3);
        expect(speakers()).toContain('Curriculum Designer');

        await advanceToPhase(wrapper, 4);
        expect(speakers()).toEqual([
            'Chief of Staff',
            'Curriculum Designer',
            'Assessment Officer',
            'Data Steward',
            'Chief of Staff',
        ]);
        expect(
            wrapper.find('[data-testid="quiz-draft-preview"]').exists(),
        ).toBe(true);

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('renders the Chief of Staff and all eight distinct specialist roles', () => {
        const wrapper = mount(HeroDemoWindow, {
            props: { prefersReducedMotion: true },
        });
        const workers = wrapper.findAll('[data-worker-row]');

        expect(workers).toHaveLength(9);
        expect(
            wrapper.get('[data-testid="team-worker-count"]').text(),
        ).toContain('9 workers');
        expect(
            wrapper.get('[data-worker-row="chief"]').attributes('title'),
        ).toContain('Chief of Staff');
        expect(
            workers.map((worker) => worker.attributes('data-worker-row')),
        ).toEqual([
            'chief',
            'registrar',
            'curriculum-designer',
            'assessment-officer',
            'student-success-coach',
            'communications-lead',
            'operations-controller',
            'data-steward',
            'engagement-manager',
        ]);

        wrapper.unmount();
    });

    it('keeps the human approval gate beside the team answer', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);
        await advanceToPhase(wrapper, 4);

        expect(wrapper.find('[data-testid="team-thread"]').exists()).toBe(true);
        expect(
            wrapper.find('[data-testid="team-human-gate"]').text(),
        ).toContain('Maria’s OK');
        expect(wrapper.find('.hdw-approve--done.is-on').exists()).toBe(true);

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('marks the engaged workers as they move through the request', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);
        const row = (slug: string) =>
            wrapper.get(`[data-worker-row="${slug}"]`);

        await releaseEntrance(wrapper);

        // Idle: everyone is available, the Chief is planning.
        expect(row('registrar').attributes('title')).toContain('Available');

        // Request sent: the assigned specialists queue up.
        await advanceToPhase(wrapper, 1);
        expect(row('curriculum-designer').attributes('title')).toContain(
            'Queued',
        );
        expect(row('data-steward').attributes('title')).toContain('Queued');
        expect(row('communications-lead').attributes('title')).toContain(
            'Available',
        );

        // Echo streams its answer, then the engaged team flips to complete.
        await advanceToPhase(wrapper, 3);
        expect(row('curriculum-designer').attributes('title')).toContain(
            'Working',
        );
        await advanceUntil(() =>
            (row('curriculum-designer').attributes('title') ?? '').includes(
                'Complete',
            ),
        );

        // Delivered.
        await advanceToPhase(wrapper, 4);
        expect(row('curriculum-designer').attributes('title')).toContain(
            'Sent',
        );

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('lets visitors inspect a worker role using pointer and keyboard focus', async () => {
        const wrapper = mount(HeroDemoWindow, {
            props: { prefersReducedMotion: true },
        });
        const registrar = wrapper.get('[data-worker-row="registrar"]');
        expect(registrar.attributes('title')).toContain('Registrar');
        await registrar.trigger('click');
        expect(registrar.attributes('aria-pressed')).toBe('true');

        wrapper.unmount();
    });

    it('cycles the hero demo through request, work, and confirmation phases', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        expect(wrapper.attributes('data-phase')).toBe('0');
        expect(wrapper.find('.wolf-persona').exists()).toBe(true);

        await releaseEntrance(wrapper);

        // Maria's request lands; the draft preview waits for Echo's reply.
        await advanceToPhase(wrapper, 1);
        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Request sent',
        );
        expect(wrapper.find('.hdw-answer').text()).not.toContain(
            'Draft ready for review',
        );
        expect(
            wrapper.find('[data-testid="quiz-draft-preview"]').exists(),
        ).toBe(false);
        expect(wrapper.text()).toContain('Draft ready for review');

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
            'Echo working',
        );

        // Confirmed — it reaches the class, loop completes before restarting.
        await advanceToPhase(wrapper, 4);
        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Sent to class',
        );
        expect(wrapper.text()).toContain('Delivered to class ✓');
        expect(wrapper.text()).toContain(
            'Draft is ready for you, Maria. Approve it and I’ll publish it to Section Diamond.',
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
        expect(
            wrapper.find('[data-testid="quiz-draft-preview"]').exists(),
        ).toBe(false);

        // Echo greets the new topic.
        await vi.advanceTimersByTimeAsync(1600);
        expect(wrapper.text()).not.toContain('Echo is thinking');
        expect(wrapper.get('[data-testid="greeting-text"]').text()).toBe(
            'Hi Maria! I look after enrolment and learner records. What do you need?',
        );

        // Then Maria types her request, letter by letter.
        const promptInput = wrapper.get('[data-testid="prompt-input"]');
        await vi.advanceTimersByTimeAsync(200);
        const firstTick = promptInput.text().trim();
        expect(firstTick.length).toBeGreaterThan(0);
        expect(firstTick.length).toBeLessThan(
            'Who is enrolled in Section Diamond this term?'.length / 2,
        );
        expect(wrapper.find('.typing-cursor').exists()).toBe(true);

        // The opening beat keeps its own budget — the turn only lands once the
        // request is fully typed, not on the leftover of the phase it
        // replaced.
        await vi.advanceTimersByTimeAsync(1200);
        expect(wrapper.attributes('data-phase')).toBe('0');
        expect(
            wrapper.find('[data-testid="quiz-draft-preview"]').exists(),
        ).toBe(false);

        await advanceToPhase(wrapper, 1);
        expect(wrapper.text()).toContain('28 learners · 3 new');
        expect(wrapper.text()).toContain(
            'Who is enrolled in Section Diamond this term?',
        );

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
        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Request sent',
        );

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('moves Maria’s pointer to approve Echo’s finished draft', async () => {
        vi.useFakeTimers();
        const wrapper = mount(HeroDemoWindow);

        await releaseEntrance(wrapper);
        await advanceToPhase(wrapper, 3);

        const pointer = wrapper.get('[data-testid="demo-cursor"]');
        const approvalButton = wrapper.get(
            '[data-testid="approve-draft-button"]',
        );

        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Echo working',
        );
        expect(approvalButton.classes()).not.toContain('is-on');

        await advanceUntil(() =>
            approvalButton.classes().includes('is-pressing'),
        );

        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Ready for review',
        );
        expect(
            wrapper.find('[data-testid="quiz-draft-preview"]').exists(),
        ).toBe(true);
        expect(pointer.classes()).toContain('is-pressing');

        await advanceToPhase(wrapper, 4);

        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Sent to class',
        );
        expect(wrapper.find('.hdw-approve--done.is-on').exists()).toBe(true);
        expect(pointer.classes()).toContain('is-visible');

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
        expect(wrapper.text()).toContain('Registrar');

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
        expect(activeRow()).toContain('Chief of Staff is typing…');

        // Closed threads collapse to their title; only the open one keeps a line.
        expect(rows[1].text()).toContain('Registrar');
        expect(rows[2].text()).toContain('Curriculum Designer');
        expect(rows[3].text()).toContain('Assessment Officer');

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
        expect(sidebar.text()).toContain('Team chat');
        expect(sidebar.text()).toContain('Registrar');
        expect(sidebar.text()).toContain('Curriculum Designer');
        expect(sidebar.text()).toContain('Assessment Officer');
        expect(sidebar.text()).toContain('Student Success Coach');
        expect(sidebar.text()).toContain('Communications Lead');
        expect(sidebar.text()).toContain('Operations Controller');
        expect(sidebar.text()).toContain('Data Steward');
        expect(sidebar.text()).toContain('Engagement Manager');

        vi.useRealTimers();
        wrapper.unmount();
    });

    it('keeps the finished demo mobile-friendly when motion is reduced', () => {
        const wrapper = mount(HeroDemoWindow, {
            props: { prefersReducedMotion: true },
        });

        expect(wrapper.attributes('data-phase')).toBe('4');
        expect(wrapper.find('.hdw-badge.is-on').text()).toContain(
            'Sent to class',
        );
        const quizPreview = wrapper.find('[data-testid="quiz-draft-preview"]');
        expect(quizPreview.exists()).toBe(true);
        expect(quizPreview.text()).toContain('Solve x² − 7x + 12 = 0.');
        expect(
            quizPreview
                .find('[aria-label="Option B, correct answer"]')
                .exists(),
        ).toBe(true);
        expect(quizPreview.text()).toContain('Answer key verified');
        expect(
            wrapper.find('[data-testid="demo-cursor"]').classes(),
        ).not.toContain('is-visible');
        expect(wrapper.find('[aria-label="Send message"]').exists()).toBe(true);
        expect(wrapper.find('.hdw-approve--done.is-on').exists()).toBe(true);
        expect(quizPreview.find('.grid-cols-2').exists()).toBe(true);
        expect(wrapper.find('.hdw-delivered').classes()).toContain('sm:block');
        expect(wrapper.find('.hdw-delivered').classes()).toContain('hidden');
        expect(wrapper.find('.relative.min-w-0.flex-1').exists()).toBe(true);
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
