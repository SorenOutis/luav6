import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { defineComponent, h, ref } from 'vue';
import ImprovedLeaderboard from '@/components/ImprovedLeaderboard.vue';

vi.mock('@inertiajs/vue3', () => ({
    Link: defineComponent({
        props: { href: { type: String, default: '#' } },
        setup(props, { slots }) {
            return () => h('a', { href: props.href }, slots.default?.());
        },
    }),
}));

vi.mock('@/composables/useNumberAnimation', () => ({
    useNumberAnimation: (getter: () => number) => ref(getter()),
}));

describe('ImprovedLeaderboard tied XP grouping', () => {
    const createMockUsers = () => [
        {
            id: 1,
            name: 'Alice',
            xp: 1500,
            avatar: '/avatars/alice.png',
            xpProgress: 50,
            streak: 5,
            joinedAt: 'Jan 2026',
            weeklyXp: 200,
            trend: 'up' as const,
            isCurrentUser: true,
        },
        {
            id: 2,
            name: 'Bob',
            xp: 1500,
            avatar: '/avatars/bob.png',
            xpProgress: 50,
            streak: 4,
            joinedAt: 'Feb 2026',
            weeklyXp: 180,
            trend: 'up' as const,
            isCurrentUser: false,
        },
        {
            id: 3,
            name: 'Charlie',
            xp: 1200,
            avatar: '/avatars/charlie.png',
            xpProgress: 20,
            streak: 3,
            joinedAt: 'Mar 2026',
            weeklyXp: 100,
            trend: 'stable' as const,
            isCurrentUser: false,
        },
        {
            id: 4,
            name: 'David',
            xp: 800,
            avatar: '/avatars/david.png',
            xpProgress: 80,
            streak: 2,
            joinedAt: 'Apr 2026',
            weeklyXp: 50,
            trend: 'down' as const,
            isCurrentUser: false,
        },
        {
            id: 5,
            name: 'Emma',
            xp: 800,
            avatar: '/avatars/emma.png',
            xpProgress: 80,
            streak: 6,
            joinedAt: 'May 2026',
            weeklyXp: 70,
            trend: 'up' as const,
            isCurrentUser: false,
        },
    ];

    it('groups tied students with identical XP into the same podium card', () => {
        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                sectionLeaderboards: [
                    {
                        sectionId: 1,
                        sectionName: 'Section Alpha',
                        users: createMockUsers(),
                        userRank: 1,
                        totalPlayers: 5,
                    },
                ],
            },
        });

        // Podium cards
        const podiumCards = wrapper.findAll('.lb-podium-card');
        // Alice and Bob share rank 1 (1500 XP), Charlie has rank 2 (1200 XP), David & Emma share rank 3 (800 XP)
        // All 5 students fit into 3 podium rank tiers!
        expect(podiumCards).toHaveLength(3);

        // Rank 1 Podium card contains both Alice and Bob
        const champCard = wrapper.find('.lb-podium-card--champ');
        expect(champCard.exists()).toBe(true);
        expect(champCard.text()).toContain('Alice');
        expect(champCard.text()).toContain('Bob');
        expect(champCard.text()).toContain('Tied (2)');
        expect(champCard.text()).toContain('1,500');

        // Rank 3 card contains both David and Emma
        expect(podiumCards[2].text()).toContain('David');
        expect(podiumCards[2].text()).toContain('Emma');
        expect(podiumCards[2].text()).toContain('Tied (2)');
        expect(podiumCards[2].text()).toContain('800');
    });

    it('orders podium correctly on mobile (1st place at top) and desktop (2nd, 1st, 3rd)', () => {
        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                sectionLeaderboards: [
                    {
                        sectionId: 1,
                        sectionName: 'Section Alpha',
                        users: createMockUsers(),
                        userRank: 1,
                        totalPlayers: 5,
                    },
                ],
            },
        });

        const podiumCards = wrapper.findAll('.lb-podium-card');
        // DOM order is rank order (1st, 2nd, 3rd) — readable by screen
        // readers and stacked narrow layouts. The champion leads.
        expect(podiumCards[0].classes()).toContain('lb-podium-card--champ');
        expect(podiumCards[0].classes()).toContain('order-1');
        expect(podiumCards[0].classes()).toContain('sm:order-2');

        // Card index 1 is 2nd place: order-2 on mobile, sm:order-1 on desktop
        expect(podiumCards[1].classes()).toContain('order-2');
        expect(podiumCards[1].classes()).toContain('sm:order-1');

        // Card index 2 is 3rd place: order-3 at every width
        expect(podiumCards[2].classes()).toContain('order-3');
        expect(podiumCards[2].classes()).toContain('sm:order-3');
    });

    it('displays tied status in "Your rank" row when the current user is tied', () => {
        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                sectionLeaderboards: [
                    {
                        sectionId: 1,
                        sectionName: 'Section Alpha',
                        users: createMockUsers(),
                        userRank: 1,
                        totalPlayers: 5,
                    },
                ],
            },
        });

        const rankRow = wrapper.find('.lb-rank-row');
        expect(rankRow.exists()).toBe(true);
        expect(rankRow.text()).toContain('#1');
        expect(rankRow.text()).toContain('Tied with 1 other');
    });

    it('groups tied students in list rankings (4th rank and below) into the same row card', () => {
        const manyUsers = [
            {
                id: 1,
                name: 'P1',
                xp: 2000,
                xpProgress: 0,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 0,
                trend: 'stable' as const,
            },
            {
                id: 2,
                name: 'P2',
                xp: 1800,
                xpProgress: 0,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 0,
                trend: 'stable' as const,
            },
            {
                id: 3,
                name: 'P3',
                xp: 1600,
                xpProgress: 0,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 0,
                trend: 'stable' as const,
            },
            {
                id: 4,
                name: 'P4',
                xp: 1000,
                xpProgress: 0,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 0,
                trend: 'stable' as const,
            },
            {
                id: 5,
                name: 'P5',
                xp: 1000,
                xpProgress: 0,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 0,
                trend: 'stable' as const,
            },
        ];

        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                sectionLeaderboards: [
                    {
                        sectionId: 1,
                        sectionName: 'Section Alpha',
                        users: manyUsers,
                        userRank: 4,
                        totalPlayers: 5,
                    },
                ],
            },
        });

        // 3 podium cards for 2000, 1800, 1600 XP tiers
        const podiumCards = wrapper.findAll('.lb-podium-card');
        expect(podiumCards).toHaveLength(3);

        // 1 list row for the 1000 XP tier containing P4 and P5
        const listRows = wrapper.findAll('.lb-row');
        expect(listRows).toHaveLength(1);
        expect(listRows[0].text()).toContain('#4');
        expect(listRows[0].text()).toContain('Tied (2 students)');
        expect(listRows[0].text()).toContain('P4');
        expect(listRows[0].text()).toContain('P5');
        expect(listRows[0].text()).toContain('1,000');
    });

    it('limits tied avatars/names in podium card and opens modal on click showing all profiles', async () => {
        const sevenUsers = [
            {
                id: 1,
                name: 'Student 1',
                xp: 2000,
                avatar: '/avatars/1.png',
                xpProgress: 90,
                streak: 5,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
                isCurrentUser: true,
            },
            {
                id: 2,
                name: 'Student 2',
                xp: 2000,
                avatar: '/avatars/2.png',
                xpProgress: 90,
                streak: 4,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
            {
                id: 3,
                name: 'Student 3',
                xp: 2000,
                avatar: '/avatars/3.png',
                xpProgress: 90,
                streak: 3,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
            {
                id: 4,
                name: 'Student 4',
                xp: 2000,
                avatar: '/avatars/4.png',
                xpProgress: 90,
                streak: 2,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
            {
                id: 5,
                name: 'Student 5',
                xp: 2000,
                avatar: '/avatars/5.png',
                xpProgress: 90,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
            {
                id: 6,
                name: 'Student 6',
                xp: 2000,
                avatar: '/avatars/6.png',
                xpProgress: 90,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
            {
                id: 7,
                name: 'Student 7',
                xp: 2000,
                avatar: '',
                xpProgress: 90,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
                blurred: true,
            },
            {
                id: 8,
                name: 'Rank2 Student',
                xp: 1500,
                xpProgress: 50,
                streak: 2,
                joinedAt: '2026',
                weeklyXp: 50,
                trend: 'stable' as const,
            },
            {
                id: 9,
                name: 'Rank3 Student',
                xp: 1000,
                xpProgress: 30,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 20,
                trend: 'down' as const,
            },
        ];

        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                sectionLeaderboards: [
                    {
                        sectionId: 1,
                        sectionName: 'Section Alpha',
                        users: sevenUsers,
                        userRank: 1,
                        totalPlayers: 9,
                    },
                ],
            },
        });

        // 1st place champ card
        const champCard = wrapper.find('.lb-podium-card--champ');
        expect(champCard.exists()).toBe(true);

        // 7 avatars ≤ PODIUM_TIED_AVATAR_LIMIT (8), so all avatars render
        // but 7 names > PODIUM_NAME_LIMIT (3), so only 3 names + "and 4 more"
        expect(champCard.text()).toContain('Student 1');
        expect(champCard.text()).toContain('Student 2');
        expect(champCard.text()).toContain('Student 3');
        expect(champCard.text()).toContain('and 4 more');

        // Only the first 3 names shown (PODIUM_NAME_LIMIT); the blurred
        // Student 7 is beyond that limit so their ████████ doesn't appear in the card
        expect(champCard.text()).toContain('and 4 more');

        // Find the "Tied (7)" badge button and click it to open the modal
        const tiedButtons = champCard
            .findAll('button')
            .filter((b) => b.text().includes('Tied (7)'));
        expect(tiedButtons.length).toBeGreaterThan(0);

        await tiedButtons[0].trigger('click');

        // The tied students modal should show ALL 7 students
        const bodyHtml = document.body.innerHTML;
        expect(bodyHtml).toContain('grid-cols-5');
        expect(bodyHtml).toContain('1st Place · Tied Students (7)');
        expect(bodyHtml).toContain('Student 1');
        expect(bodyHtml).toContain('Student 2');
        expect(bodyHtml).toContain('Student 3');
        expect(bodyHtml).toContain('Student 4');
        expect(bodyHtml).toContain('Student 5');
        expect(bodyHtml).toContain('Student 6');
        expect(bodyHtml).toContain('████████████████████');
        expect(bodyHtml).toContain('YOU');
    });

    it('opens tied modal when clicking the "Tied (N)" badge on a multi-avatar podium card', async () => {
        const fiveUsers = [
            {
                id: 1,
                name: 'User A',
                xp: 2000,
                xpProgress: 90,
                streak: 5,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
            {
                id: 2,
                name: 'User B',
                xp: 2000,
                xpProgress: 90,
                streak: 4,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
            {
                id: 3,
                name: 'User C',
                xp: 2000,
                xpProgress: 90,
                streak: 3,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
            {
                id: 4,
                name: 'User D',
                xp: 2000,
                xpProgress: 90,
                streak: 2,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
            {
                id: 5,
                name: 'User E',
                xp: 2000,
                xpProgress: 90,
                streak: 1,
                joinedAt: '2026',
                weeklyXp: 100,
                trend: 'up' as const,
            },
        ];

        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                sectionLeaderboards: [
                    {
                        sectionId: 1,
                        sectionName: 'Section Alpha',
                        users: fiveUsers,
                        userRank: 1,
                        totalPlayers: 5,
                    },
                ],
            },
        });

        const champCard = wrapper.find('.lb-podium-card--champ');

        // 5 avatars ≤ PODIUM_TIED_AVATAR_LIMIT (8), so no "+N" overflow button
        expect(
            champCard.findAll('button').find((b) => b.text().includes('+1')),
        ).toBeUndefined();

        // Only the first 3 names shown in card (PODIUM_NAME_LIMIT), plus "and 2 more"
        expect(champCard.text()).toContain('User A');
        expect(champCard.text()).toContain('User B');
        expect(champCard.text()).toContain('User C');
        expect(champCard.text()).toContain('and 2 more');

        // Open the modal via the "Tied (5)" badge — modal shows ALL names
        const tiedBadge = champCard
            .findAll('button')
            .find((b) => b.text().includes('Tied (5)'));
        expect(tiedBadge).toBeDefined();

        await tiedBadge!.trigger('click');

        const bodyHtml = document.body.innerHTML;
        expect(bodyHtml).toContain('1st Place · Tied Students (5)');
        expect(bodyHtml).toContain('User A');
        expect(bodyHtml).toContain('User D');
        expect(bodyHtml).toContain('User E');
    });

    it("keeps a searched user's true rank instead of relabeling them as 1st", async () => {
        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                sectionLeaderboards: [
                    {
                        sectionId: 1,
                        sectionName: 'Section Alpha',
                        users: createMockUsers(),
                        userRank: 1,
                        totalPlayers: 5,
                    },
                ],
            },
        });

        // David is rank 4 in the full list (tied with Emma at 800 XP).
        const input = wrapper.find('input');
        await input.setValue('David');

        const podiumCards = wrapper.findAll('.lb-podium-card');
        // Only the single match is shown in the podium.
        expect(podiumCards).toHaveLength(1);

        const card = podiumCards[0];
        // The badge must show the true placement (#4), not "1st".
        expect(card.text()).toContain('#4');
        expect(card.text()).not.toContain('1st');
        expect(card.text()).toContain('David');

        // And the redundant place label below the name is gone entirely.
        expect(card.text()).not.toContain('1st');
        expect(card.text()).not.toContain('2nd');
        expect(card.text()).not.toContain('3rd');
    });
});

describe('ImprovedLeaderboard podium band (podiumOnly)', () => {
    // Strictly distinct XP values → three clean rank groups.
    const createRankedUsers = () => [
        {
            id: 1,
            name: 'Ava',
            xp: 12404,
            avatar: '/avatars/ava.png',
            xpProgress: 80,
            streak: 9,
            joinedAt: 'Jan 2026',
            weeklyXp: 400,
            trend: 'up' as const,
            isCurrentUser: false,
        },
        {
            id: 2,
            name: 'Daniel',
            xp: 11870,
            avatar: '/avatars/daniel.png',
            xpProgress: 72,
            streak: 6,
            joinedAt: 'Jan 2026',
            weeklyXp: 350,
            trend: 'up' as const,
            isCurrentUser: false,
        },
        {
            id: 3,
            name: 'Kat',
            xp: 11240,
            avatar: '/avatars/kat.png',
            xpProgress: 64,
            streak: 5,
            joinedAt: 'Feb 2026',
            weeklyXp: 300,
            trend: 'stable' as const,
            isCurrentUser: false,
        },
        {
            id: 4,
            name: 'Maya',
            xp: 9340,
            avatar: '/avatars/maya.png',
            xpProgress: 50,
            streak: 8,
            joinedAt: 'Feb 2026',
            weeklyXp: 85,
            trend: 'up' as const,
            isCurrentUser: true,
        },
    ];

    const mountBand = () =>
        mount(ImprovedLeaderboard, {
            props: {
                podiumOnly: true,
                sectionLeaderboards: [
                    {
                        sectionId: 1,
                        sectionName: 'Grade 11 — Newton',
                        users: createRankedUsers(),
                        userRank: 4,
                        totalPlayers: 4,
                    },
                ],
            },
        });

    it('renders only the podium with a band label and no list or rank row', () => {
        const wrapper = mountBand();

        expect(wrapper.text()).toContain('Top 3');
        expect(wrapper.findAll('.lb-podium-card')).toHaveLength(3);
        // Full-card chrome is suppressed in band mode.
        expect(wrapper.find('h2').exists()).toBe(false);
        expect(wrapper.find('.lb-rank-row').exists()).toBe(false);
        expect(wrapper.find('.lb-row').exists()).toBe(false);
    });

    it('uses rank order in the DOM and stages 2nd-1st-3rd only via CSS', () => {
        const wrapper = mountBand();

        const cards = wrapper.findAll('.lb-podium-card');
        expect(cards).toHaveLength(3);

        // DOM order = rank order (1st, 2nd, 3rd): screen readers and the
        // stacked mobile band read top-to-bottom by rank.
        expect(cards[0].classes()).toContain('lb-podium-card--champ');
        expect(cards[0].text()).toContain('Ava');
        expect(cards[1].text()).toContain('Daniel');
        expect(cards[2].text()).toContain('Kat');

        // Wide screens re-stage visually to 2nd-1st-3rd via order utilities.
        const classOf = (el: (typeof cards)[number]) => el.classes().join(' ');
        expect(classOf(cards[0])).toContain('order-1 sm:order-2');
        expect(classOf(cards[1])).toContain('order-2 sm:order-1');
        expect(classOf(cards[2])).toContain('order-3 sm:order-3');
    });
});

describe('ImprovedLeaderboard controlled section (dashboard band sync)', () => {
    const makeUsers = (prefix: string, boost: number) =>
        [3000, 2000, 1000].map((xp, i) => ({
            id: boost + i,
            name: `${prefix} Student ${i + 1}`,
            xp: xp + boost,
            xpProgress: 50,
            streak: 3,
            joinedAt: 'Jan 2026',
            weeklyXp: 100,
            trend: 'stable' as const,
            isCurrentUser: false,
        }));

    const sections = () => [
        {
            sectionId: 1,
            sectionName: 'Section Alpha',
            users: makeUsers('Alpha', 0),
            userRank: 1,
            totalPlayers: 3,
        },
        {
            sectionId: 2,
            sectionName: 'Section Beta',
            users: makeUsers('Beta', 5000),
            userRank: 2,
            totalPlayers: 3,
        },
    ];

    beforeEach(() => {
        localStorage.clear();
    });

    it('renders the podium for the controlled activeSectionId instead of the first tab', () => {
        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                podiumOnly: true,
                sectionLeaderboards: sections(),
                activeSectionId: 2,
            },
        });

        expect(wrapper.text()).toContain('Section Beta');
        expect(wrapper.text()).toContain('Beta Student 1');
        expect(wrapper.text()).not.toContain('Alpha Student 1');
    });

    it('emits update:activeSectionId on tab click and switches once the parent applies it', async () => {
        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                sectionLeaderboards: sections(),
                activeSectionId: 1,
            },
        });

        const tabs = wrapper.findAll('.lb-tab');
        expect(tabs).toHaveLength(2);

        await tabs[1].trigger('click');

        expect(wrapper.emitted('update:activeSectionId')).toEqual([[2]]);
        // Still showing section 1 until the parent applies the update.
        expect(wrapper.text()).toContain('Section Alpha');

        await wrapper.setProps({ activeSectionId: 2 });
        expect(wrapper.text()).toContain('Section Beta');
        expect(wrapper.text()).toContain('Beta Student 1');
    });

    it('keeps self-managed tab state when activeSectionId is omitted (standalone page)', async () => {
        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                sectionLeaderboards: sections(),
            },
        });

        const tabs = wrapper.findAll('.lb-tab');
        await tabs[1].trigger('click');

        expect(wrapper.emitted('update:activeSectionId')).toBeUndefined();
        expect(wrapper.text()).toContain('Section Beta');
    });

    it('falls back to the first section when the controlled id is unknown', () => {
        const wrapper = mount(ImprovedLeaderboard, {
            props: {
                podiumOnly: true,
                sectionLeaderboards: sections(),
                activeSectionId: 999,
            },
        });

        expect(wrapper.text()).toContain('Section Alpha');
        expect(wrapper.text()).toContain('Alpha Student 1');
    });

    it('syncs the Top-3 band when a section tab is picked in the sibling rankings card', async () => {
        // Mirrors the dashboard wiring: one shared ref drives both instances.
        const DashboardHarness = defineComponent({
            setup() {
                const sectionId = ref<number | null>(1);
                const list = sections();
                return () =>
                    h('div', [
                        h(ImprovedLeaderboard, {
                            podiumOnly: true,
                            sectionLeaderboards: list,
                            activeSectionId: sectionId.value,
                            'onUpdate:activeSectionId': (id: number) => {
                                sectionId.value = id;
                            },
                        }),
                        h(ImprovedLeaderboard, {
                            hidePodium: true,
                            sectionLeaderboards: list,
                            activeSectionId: sectionId.value,
                            'onUpdate:activeSectionId': (id: number) => {
                                sectionId.value = id;
                            },
                        }),
                    ]);
            },
        });

        const wrapper = mount(DashboardHarness);
        const instances = wrapper.findAllComponents(ImprovedLeaderboard);
        expect(instances).toHaveLength(2);

        const [band, card] = [instances[0], instances[1]];
        expect(band.text()).toContain('Alpha Student 1');

        const tabs = card.findAll('.lb-tab');
        expect(tabs).toHaveLength(2);
        await tabs[1].trigger('click');
        await wrapper.vm.$nextTick();

        // The band's Top 3 follows the rankings card's new section.
        expect(band.text()).toContain('Section Beta');
        expect(band.text()).toContain('Beta Student 1');
        expect(band.text()).not.toContain('Alpha Student 1');
    });
});
