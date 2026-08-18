/**
 * Interaction test for Exam.vue review modal — verifies that recorded MC /
 * true-false / identification answers are actually DISPLAYED in the student's
 * "Review Results" modal after the exam is closed.
 *
 * Also verifies the opposite: a student who never answered a closed exam gets
 * NO "Review results" affordance, so the questions cannot be opened.
 */
import { mount, flushPromises } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { defineComponent, h } from 'vue';
import ExamPage from '@/pages/Exam.vue';

vi.mock('@inertiajs/vue3', () => ({
    Head: defineComponent({ render: () => null }),
    Link: defineComponent({
        props: ['href'],
        setup(props: any, { slots }: any) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    }),
    // Exam.vue calls router.reload in refreshExams() on every mount after the
    // first; keep the mock complete so additional tests can't crash on it.
    router: {
        reload: vi.fn(),
    },
    usePoll: vi.fn(() => ({ start: vi.fn(), stop: vi.fn() })),
    // OnboardingTour reads the authenticated user's public_id for its
    // per-user localStorage scope.
    usePage: () => ({
        props: { auth: { user: { public_id: 'test-user' } } },
    }),
}));

vi.mock('@motionone/vue', () => ({
    Motion: defineComponent({
        props: [
            'initial',
            'animate',
            'in-view',
            'in-view-options',
            'transition',
        ],
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
    }),
}));

vi.mock('@/composables/useLenis', () => ({
    getLenis: vi.fn(() => ({ stop: vi.fn(), start: vi.fn() })),
}));

vi.mock('@/routes/exams', () => ({
    show: (id: number) => ({ url: `/exams/${id}` }),
}));

vi.mock('@/layouts/AppLayout.vue', () => ({
    default: defineComponent({
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
    }),
}));

// ResponsiveModal — render slot content (it's an overlay wrapper). The
// footer slot matters: the mobile Prev/Next review controls are pinned there
// so long essay answers can't push them off screen.
vi.mock('@/components/ResponsiveModal.vue', () => ({
    default: defineComponent({
        props: ['open', 'custom-header', 'content-class'],
        setup(props: any, { slots }: any) {
            return () =>
                props.open
                    ? h('div', [
                          slots.default?.(),
                          slots.footer ? h('div', slots.footer()) : null,
                      ])
                    : null;
        },
    }),
}));

const stubs = {
    Head: { render: () => null },
    Link: {
        props: ['href'],
        setup(props: any, { slots }: any) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    },
    Motion: {
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
    },
    AppLayout: {
        setup(_: any, { slots }: any) {
            return () => h('div', slots.default?.());
        },
    },
    ResponsiveModal: {
        props: ['open'],
        setup(props: any, { slots }: any) {
            return () =>
                props.open
                    ? h('div', [
                          slots.default?.(),
                          slots.footer ? h('div', slots.footer()) : null,
                      ])
                    : null;
        },
    },
    Button: { render: () => null },
    DialogDescription: { render: () => null },
    DialogTitle: { render: () => null },
};

const mountExamPage = (exams: any[]) =>
    mount(ExamPage, {
        props: {
            examsBySeason: [{ seasonName: 'Season 1', exams }],
        },
        global: { stubs },
    });

// The review renders a "Review Results" button on each exam card
const closedExam = {
    id: 7,
    title: 'Midterm Exam',
    description: 'desc',
    exam_date: '2026-08-01',
    exam_date_iso: '2026-08-01T00:00:00+08:00',
    duration_minutes: 60,
    status: 'closed',
    url: null,
    submitted_parts_count: 1,
    total_parts: 1,
    is_locked: true,
    has_submissions: true,
    submissions: [
        {
            id: 1,
            user_id: 2,
            exam_id: 7,
            exam_part_id: 101,
            answers: [
                {
                    question_number: 1,
                    answer: 0,
                    question_type: 'multiple_choice',
                    question_text: 'Capital of France?',
                    points: 1,
                },
                {
                    question_number: 2,
                    answer: 1,
                    question_type: 'true_false',
                    question_text: 'The sun is a star.',
                    points: 1,
                },
                {
                    question_number: 3,
                    answer: 'Jose Rizal',
                    question_type: 'identification',
                    question_text: 'Who wrote Noli Me Tangere?',
                    points: 2,
                },
            ],
            status: 'submitted',
            score: '3.00',
        },
    ],
    parts: [
        {
            id: 101,
            title: 'Part I',
            instructions: null,
            type: 'section',
            points: 1,
            questions: [
                {
                    text: 'Capital of France?',
                    type: 'multiple_choice',
                    points: 1,
                    options: [
                        { text: 'Berlin', is_correct: false },
                        { text: 'Paris', is_correct: true },
                        { text: 'Rome', is_correct: false },
                    ],
                },
                {
                    text: 'The sun is a star.',
                    type: 'true_false',
                    points: 1,
                    options: [
                        { text: 'True', is_correct: true },
                        { text: 'False', is_correct: false },
                    ],
                },
                {
                    text: 'Who wrote Noli Me Tangere?',
                    type: 'identification',
                    points: 2,
                    options: null,
                    correct_answer: 'Jose Rizal',
                },
            ],
        },
    ],
};

// A closed exam the student never answered: no submissions, no review.
const closedExamNoSubmission = {
    id: 8,
    title: 'Final Exam',
    description: 'desc',
    exam_date: '2026-08-01',
    exam_date_iso: '2026-08-01T00:00:00+08:00',
    duration_minutes: 60,
    status: 'closed',
    url: null,
    submitted_parts_count: 0,
    total_parts: 1,
    is_locked: true,
    has_submissions: false,
    submissions: [],
    parts: [
        {
            id: 102,
            title: 'Part I',
            instructions: null,
            type: 'section',
            points: 1,
            questions: [],
        },
    ],
};

// A closed exam where the student submitted part 101 but never answered part
// 103. They saw every part while the exam was open, so the review must still
// show the unsubmitted part (its questions render with a "No answer" fallback).
const closedExamPartialSubmission = {
    id: 9,
    title: 'Quarterly Exam',
    description: 'desc',
    exam_date: '2026-08-01',
    exam_date_iso: '2026-08-01T00:00:00+08:00',
    duration_minutes: 60,
    status: 'closed',
    url: null,
    submitted_parts_count: 1,
    total_parts: 2,
    is_locked: true,
    has_submissions: true,
    submissions: [
        {
            id: 2,
            user_id: 2,
            exam_id: 9,
            exam_part_id: 101,
            answers: [
                {
                    question_number: 1,
                    answer: 1,
                    question_type: 'multiple_choice',
                    question_text: 'Capital of France?',
                    points: 1,
                },
            ],
            status: 'submitted',
            score: '1.00',
        },
    ],
    parts: [
        {
            id: 101,
            title: 'Part I',
            instructions: null,
            type: 'multiple_choice',
            points: 1,
            questions: [
                {
                    text: 'Capital of France?',
                    type: 'multiple_choice',
                    points: 1,
                    options: [
                        { text: 'Berlin', is_correct: false },
                        { text: 'Paris', is_correct: true },
                        { text: 'Rome', is_correct: false },
                    ],
                },
            ],
        },
        {
            id: 103,
            title: 'Part II',
            instructions: null,
            type: 'identification',
            points: 2,
            questions: [
                {
                    text: 'Who wrote Noli Me Tangere?',
                    type: 'identification',
                    points: 2,
                    options: null,
                    correct_answer: 'Jose Rizal',
                },
            ],
        },
    ],
};

// A closed exam whose essay question carries a long response + AI feedback.
const closedExamWithEssay = {
    ...closedExam,
    id: 10,
    title: 'Essay Exam',
    submissions: [
        {
            ...closedExam.submissions[0],
            answers: [
                ...closedExam.submissions[0].answers,
                {
                    question_number: 4,
                    answer: 'A very long essay response that would otherwise stretch the review modal far beyond the viewport height, making the close button unreachable without scrolling the entire sheet.',
                    question_type: 'essay',
                    question_text: 'Write an essay.',
                    points: 10,
                    ai_score: 8,
                    ai_feedback:
                        'A very long AI feedback paragraph that should also be bounded inside a scrollable area so it never pushes the rest of the review out of view.',
                },
            ],
        },
    ],
    parts: [
        {
            ...closedExam.parts[0],
            questions: [
                ...closedExam.parts[0].questions,
                {
                    text: 'Write an essay.',
                    type: 'essay',
                    points: 10,
                    options: null,
                    correct_answer: null,
                },
            ],
        },
    ],
};

describe('Exam.vue review modal answer display', () => {
    beforeEach(() => {
        localStorage.clear();
        if (!(Element.prototype as any).scrollTo) {
            (Element.prototype as any).scrollTo = () => {};
        }
    });

    it('shows recorded MC / TF / identification answers', async () => {
        const wrapper = mountExamPage([closedExam as any]);
        await flushPromises();

        // Open the review modal
        const reviewBtn = wrapper
            .findAll('button')
            .find((b: any) => b.text().includes('Review results'));
        expect(reviewBtn, 'Review results button should exist').toBeTruthy();
        await reviewBtn!.trigger('click');
        await flushPromises();
        await new Promise((r) => setTimeout(r, 50));

        const text = wrapper.text();

        // MC: the student's pick (Berlin, index 0) should be marked "Your answer"
        expect(text).toContain('Your answer');
        // Identification: the typed answer should appear
        expect(text).toContain('Jose Rizal');
        // Score header
        expect(text).toContain('3.0');

        // The "No answer" fallback must NOT appear for any question
        expect(text).not.toContain('No answer');

        // Each question shows a "Your answer" badge (MC, TF, ID)
        const yourAnswerBadges = wrapper
            .findAll('span')
            .filter((s: any) => s.text().trim() === 'Your answer');
        expect(yourAnswerBadges.length).toBe(3);
    });

    it('does not offer review results for a closed exam the student never answered', async () => {
        const wrapper = mountExamPage([closedExamNoSubmission as any]);
        await flushPromises();

        const reviewBtn = wrapper
            .findAll('button')
            .find((b: any) => b.text().includes('Review results'));
        expect(
            reviewBtn,
            'Review results button must NOT exist for a non-participant',
        ).toBeUndefined();

        // The review modal must stay closed, so the questions are not exposed.
        expect(wrapper.text()).not.toContain('Your Results');

        // The card should still clearly read as closed rather than completed.
        expect(wrapper.text()).toContain('Closed');
        expect(wrapper.text()).not.toContain('Completed');
    });

    it('still shows an unsubmitted part for a student who answered other parts', async () => {
        const wrapper = mountExamPage([closedExamPartialSubmission as any]);
        await flushPromises();

        const reviewBtn = wrapper
            .findAll('button')
            .find((b: any) => b.text().includes('Review results'));
        expect(reviewBtn, 'Review results button should exist').toBeTruthy();
        await reviewBtn!.trigger('click');
        await flushPromises();
        await new Promise((r) => setTimeout(r, 50));

        const text = wrapper.text();

        // Both parts are present in the modal, even the one never submitted.
        expect(text).toContain('Part I');
        expect(text).toContain('Part II');
        // The unsubmitted part's question is still shown (they saw it during
        // the exam) and falls back to "No answer".
        expect(text).toContain('Who wrote Noli Me Tangere?');
        expect(text).toContain('No answer');
    });

    it('bounds long essay responses and AI feedback in scrollable areas', async () => {
        const wrapper = mountExamPage([closedExamWithEssay as any]);
        await flushPromises();

        const reviewBtn = wrapper
            .findAll('button')
            .find((b: any) => b.text().includes('Review results'));
        expect(reviewBtn, 'Review results button should exist').toBeTruthy();
        await reviewBtn!.trigger('click');
        await flushPromises();
        await new Promise((r) => setTimeout(r, 50));

        // The essay response renders inside a height-capped scroll container.
        // The cap and the overflow are gated behind `sm:` so the element is
        // NOT a scroll container on phones — `overflow-y: auto` there would
        // cut the touch scroll chain (see the mobile test below).
        const response = wrapper.find('[data-test="essay-response"]');
        expect(response.exists()).toBe(true);
        expect(response.classes()).toContain('sm:max-h-52');
        expect(response.classes()).toContain('sm:overflow-y-auto');
        // An unprefixed `overscroll-contain` on the nested box traps touch
        // scrolling at its boundary instead of chaining to the modal scroller
        // — containment belongs on the modal body, not on inner boxes.
        expect(response.classes()).not.toContain('overscroll-contain');
        // The long text is still fully rendered (scrollable, not truncated)
        expect(response.text()).toContain('A very long essay response');

        // The AI feedback is bounded the same way
        const feedback = wrapper.find('[data-test="essay-feedback"]');
        expect(feedback.exists()).toBe(true);
        expect(feedback.classes()).toContain('sm:max-h-52');
        expect(feedback.classes()).toContain('sm:overflow-y-auto');
        expect(feedback.classes()).not.toContain('overscroll-contain');
        expect(feedback.text()).toContain('A very long AI feedback paragraph');
    });

    it('keeps a long essay scrollable in the mobile one-question review', async () => {
        const previousInnerWidth = window.innerWidth;
        Object.defineProperty(window, 'innerWidth', {
            configurable: true,
            value: 390,
        });

        try {
            const wrapper = mountExamPage([closedExamWithEssay as any]);
            await flushPromises();

            const reviewBtn = wrapper
                .findAll('button')
                .find((b: any) => b.text().includes('Review results'));
            expect(
                reviewBtn,
                'Review results button should exist',
            ).toBeTruthy();
            await reviewBtn!.trigger('click');
            await flushPromises();
            await new Promise((r) => setTimeout(r, 50));

            // Mobile review walks questions one at a time with Prev / Next.
            const prev = wrapper
                .findAll('button')
                .find((b: any) => b.text().includes('Prev'));
            const next = wrapper
                .findAll('button')
                .find((b: any) => b.text().includes('Next'));
            expect(prev, 'mobile Prev control should exist').toBeTruthy();
            expect(next, 'mobile Next control should exist').toBeTruthy();
            expect(wrapper.text()).toContain('1 / 4');

            // The long essay flows at full height on mobile so the bottom
            // sheet's own scroll area handles it. Any mobile scroll container
            // here (`overflow-y-auto` used to be unprefixed, `max-h-96`
            // before that) cuts the touch scroll chain — `overscroll-behavior`
            // is honored even on non-scrolling scroll containers in modern
            // Chrome/Safari — so drags on the answer text went dead and the
            // Prev/Next controls were unreachable. The element must carry NO
            // unprefixed overflow/overscroll classes; only the desktop modal
            // (sm+) gets the bounded, scrollable box.
            const response = wrapper.find('[data-test="essay-response"]');
            expect(response.exists()).toBe(true);
            expect(response.classes()).not.toContain('overflow-y-auto');
            expect(response.classes()).not.toContain('overscroll-contain');
            expect(response.classes()).not.toContain('max-h-96');
            expect(response.classes()).toContain('sm:max-h-52');
            expect(response.classes()).toContain('sm:overflow-y-auto');
            expect(response.text()).toContain('A very long essay response');

            const feedback = wrapper.find('[data-test="essay-feedback"]');
            expect(feedback.exists()).toBe(true);
            expect(feedback.classes()).not.toContain('overflow-y-auto');
            expect(feedback.classes()).not.toContain('max-h-96');
            expect(feedback.classes()).not.toContain('overscroll-contain');
            expect(feedback.classes()).toContain('sm:max-h-52');
            expect(feedback.classes()).toContain('sm:overflow-y-auto');
        } finally {
            Object.defineProperty(window, 'innerWidth', {
                configurable: true,
                value: previousInnerWidth,
            });
        }
    });
});
