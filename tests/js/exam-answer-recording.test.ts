/**
 * Interaction test for Exams/Show.vue — simulates a student answering
 * multiple choice, true/false and identification questions, then submitting,
 * and asserts the exact payload that reaches the server.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { defineComponent, h } from 'vue';
import Show from '@/pages/Exams/Show.vue';

const postMock = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({
        props: { href: { type: String, default: '#' } },
        setup(
            props: { href?: string },
            { slots }: { slots: { default?: () => any } },
        ) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    }),
    router: {
        post: (...args: unknown[]) => {
            postMock(...args);
            // simulate a successful Inertia visit
            const opts = args[2] as any;
            opts?.onSuccess?.({ props: { submissions: {} } });
            opts?.onFinish?.();
        },
        // used by submitPart's onSuccess to invalidate the prefetch cache
        flush: vi.fn(),
    },
}));

vi.mock('axios', () => ({
    default: {
        post: vi.fn(async () => ({
            data: { deadline: new Date(Date.now() + 3600_000).toISOString() },
        })),
        put: vi.fn(async () => ({
            data: { saved_at: new Date().toISOString() },
        })),
    },
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        props: ['breadcrumbs', 'hide-sidebar'],
        setup(_, { slots }) {
            return () => h('div', slots.default?.());
        },
    }),
}));

vi.mock('@/components/PageSkeleton.vue', () => ({
    default: defineComponent({ render: () => null }),
}));

const makeExam = () => ({
    id: 1,
    title: 'Sample Exam',
    description: 'desc',
    exam_date: '2026-08-12',
    duration_minutes: 60,
    status: 'published',
    url: null,
    parts: [
        {
            id: 101,
            title: 'Part I - Multiple Choice',
            instructions: null,
            type: 'section',
            points: 1,
            questions: [
                {
                    text: 'What is the capital of France?',
                    type: 'multiple_choice',
                    points: 1,
                    options: [
                        { text: 'Berlin' },
                        { text: 'Paris' },
                        { text: 'Rome' },
                    ],
                },
                {
                    text: 'The sun is a star.',
                    type: 'true_false',
                    points: 1,
                    options: [{ text: 'True' }, { text: 'False' }],
                },
                {
                    text: 'Who wrote Noli Me Tangere?',
                    type: 'identification',
                    points: 2,
                    options: null,
                },
            ],
        },
    ],
});

const mountShow = async (answerDrafts: Record<number, unknown> = {}) => {
    const wrapper = mount(Show, {
        props: {
            exam: makeExam() as any,
            submissions: {},
            submittedPartId: null,
            partDeadlines: {},
            answerDrafts: answerDrafts as any,
            realtimeChannel: 'exam.1.student.1',
        },
        global: {
            stubs: {
                Head: { render: () => null },
                Motion: { render: () => null },
                AppLayout: {
                    setup(_: any, { slots }: any) {
                        return () => h('div', slots.default?.());
                    },
                },
                PageSkeleton: { render: () => null },
            },
        },
    });
    // wait for boot
    await flushPromises();
    await new Promise((r) => setTimeout(r, 50));
    return wrapper;
};

const startPart = async (wrapper: any) => {
    // Click the part card (first .exam-part-card)
    await wrapper.find('.exam-part-card').trigger('click');
    await flushPromises();
    // Start modal visible -> click "confirm" button (look for the start modal action)
    const startBtn = wrapper
        .findAll('button')
        .find(
            (b: any) =>
                b.text().includes('Begin') || b.text().includes('Start'),
        );
    if (startBtn) {
        await startBtn.trigger('click');
    }
    await flushPromises();
    await new Promise((r) => setTimeout(r, 50));
};

describe('Exams/Show.vue answer recording', () => {
    beforeEach(async () => {
        localStorage.clear();
        postMock.mockClear();
        const axiosMod = await import('axios');
        (vi.mocked(axiosMod.default.post) as any).mockClear();
    });

    it('records MC / true-false / identification answers in the submit payload', async () => {
        const wrapper = await mountShow();
        await startPart(wrapper);

        // ── Multiple choice: pick "Paris" (index 1) ──
        const mcInputs = wrapper
            .findAll('input[type="radio"]')
            .filter((i: any) => i.attributes('name') === 'q-0');
        expect(mcInputs.length).toBe(3);
        await mcInputs[1].setValue();
        await flushPromises();

        // Check icon should now render for option index 1 (Check is lucide icon)
        await new Promise((r) => setTimeout(r, 50));

        // ── True/false: pick "True" (index 0) ──
        const tfInputs = wrapper
            .findAll('input[type="radio"]')
            .filter((i: any) => i.attributes('name') === 'q-1');
        expect(tfInputs.length).toBe(2);
        await tfInputs[0].setValue();
        await flushPromises();

        // ── Identification: type an answer ──
        const idInput = wrapper.find('input[type="text"]');
        await idInput.setValue('Jose Rizal');
        await flushPromises();

        // ── Submit ──
        const submitBtn = wrapper
            .findAll('button')
            .find((b: any) => b.text().includes('Submit this part'));
        expect(submitBtn, 'submit button should exist').toBeTruthy();
        await submitBtn!.trigger('click');
        await flushPromises();

        // The first click may show the unanswered warning if some question is
        // counted unanswered; if so, click "Submit Anyway".
        await new Promise((r) => setTimeout(r, 50));
        const anywayBtn = wrapper
            .findAll('button')
            .find((b: any) => b.text().includes('Submit Anyway'));
        if (anywayBtn) {
            await anywayBtn.trigger('click');
            await flushPromises();
        }

        expect(postMock).toHaveBeenCalled();
        const [url, payload] = postMock.mock.calls.find(
            (c: any) => typeof c[0] === 'string' && c[0].includes('/submit'),
        ) as [string, any];

        expect(url).toContain('/exams/1/parts/101/submit');

        const answers = payload.answers;
        expect(Array.isArray(answers)).toBe(true);
        expect(answers).toHaveLength(3);

        const byNumber = Object.fromEntries(
            answers.map((a: any) => [a.question_number, a]),
        );

        // MC answer recorded as the option INDEX (1 = Paris)
        expect(byNumber[1].answer).toBe(1);
        expect(byNumber[1].question_type).toBe('multiple_choice');
        // TF answer recorded as the option INDEX (0 = True)
        expect(byNumber[2].answer).toBe(0);
        expect(byNumber[2].question_type).toBe('true_false');
        // Identification answer recorded as typed text
        expect(byNumber[3].answer).toBe('Jose Rizal');
        expect(byNumber[3].question_type).toBe('identification');

        // The same answers are durably auto-saved before final submission.
        const axiosMod = await import('axios');
        const autosaveCall = vi
            .mocked(axiosMod.default.put)
            .mock.calls.find((call: any[]) =>
                String(call[0]).includes('/parts/101/answers'),
            );
        expect(autosaveCall).toBeTruthy();
        expect(autosaveCall?.[1]).toMatchObject({
            answers: expect.arrayContaining([
                { question_number: 1, answer: 1 },
                { question_number: 2, answer: 0 },
                { question_number: 3, answer: 'Jose Rizal' },
            ]),
        });
    });

    it('restores database-backed answers when a part is reopened after reload', async () => {
        const wrapper = await mountShow({
            101: {
                answers: [
                    { question_number: 1, answer: 1 },
                    { question_number: 2, answer: 0 },
                    { question_number: 3, answer: 'Jose Rizal' },
                ],
                saved_at: new Date().toISOString(),
            },
        });
        await startPart(wrapper);

        const selectedMc = wrapper
            .findAll('input[type="radio"]')
            .find(
                (input: any) =>
                    input.attributes('name') === 'q-0' && input.element.checked,
            );
        const selectedTrueFalse = wrapper
            .findAll('input[type="radio"]')
            .find(
                (input: any) =>
                    input.attributes('name') === 'q-1' && input.element.checked,
            );

        expect(selectedMc?.attributes('value')).toBe('1');
        expect(selectedTrueFalse?.attributes('value')).toBe('0');
        expect(
            (wrapper.find('input[type="text"]').element as HTMLInputElement)
                .value,
        ).toBe('Jose Rizal');
    });
});
