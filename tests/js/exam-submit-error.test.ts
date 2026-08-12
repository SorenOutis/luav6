/**
 * Verifies that a FAILED part submission surfaces an error to the student
 * instead of silently resetting — the failure mode that previously made
 * students re-answer whole parts ("answers not recorded, answer again").
 */
import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { defineComponent, h } from 'vue';
import Show from '@/pages/Exams/Show.vue';

const postMock = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    router: {
        post: (...args: unknown[]) => {
            postMock(...args);
            const opts = args[2] as any;
            // simulate a FAILED visit
            opts?.onError?.({ error: 'You have already submitted this part.' });
            opts?.onFinish?.();
        },
    },
}));

vi.mock('axios', () => ({
    default: {
        post: vi.fn(async () => ({
            data: { deadline: new Date(Date.now() + 3600_000).toISOString() },
        })),
    },
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup(_: any, { slots }: any) {
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
    description: 'd',
    exam_date: '2026-08-12',
    duration_minutes: 60,
    status: 'published',
    url: null,
    parts: [
        {
            id: 101,
            title: 'Part I',
            instructions: null,
            type: 'section',
            points: 1,
            questions: [
                {
                    text: 'Q1',
                    type: 'multiple_choice',
                    points: 1,
                    options: [{ text: 'A' }, { text: 'B' }],
                },
            ],
        },
    ],
});

describe('Exams/Show.vue failed submission', () => {
    beforeEach(() => {
        localStorage.clear();
        postMock.mockClear();
    });

    it('shows an error banner when the submit request fails', async () => {
        const wrapper = mount(Show, {
            props: {
                exam: makeExam() as any,
                submissions: {},
                submittedPartId: null,
                partDeadlines: {},
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
        await flushPromises();
        await new Promise((r) => setTimeout(r, 50));

        // start the part
        await wrapper.find('.exam-part-card').trigger('click');
        await flushPromises();
        const startBtn = wrapper
            .findAll('button')
            .find((b: any) => b.text().includes('Start'));
        if (startBtn) await startBtn.trigger('click');
        await flushPromises();
        await new Promise((r) => setTimeout(r, 50));

        // answer the single MC question
        const radio = wrapper
            .findAll('input[type="radio"]')
            .filter((i: any) => i.attributes('name') === 'q-0')[0];
        await radio.setValue();
        await flushPromises();

        // submit -> mocked onError
        const submitBtn = wrapper
            .findAll('button')
            .find((b: any) => b.text().includes('Submit this part'));
        await submitBtn!.trigger('click');
        await flushPromises();
        await new Promise((r) => setTimeout(r, 50));

        // The failure message must be visible to the student
        expect(wrapper.text()).toContain(
            'You have already submitted this part.',
        );
    });
});
