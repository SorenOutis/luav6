import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
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
        // Card index 0 is 2nd place in DOM order: order-2 on mobile, sm:order-1 on desktop
        expect(podiumCards[0].classes()).toContain('order-2');
        expect(podiumCards[0].classes()).toContain('sm:order-1');

        // Card index 1 is 1st place in DOM order: order-1 on mobile, sm:order-2 on desktop
        expect(podiumCards[1].classes()).toContain('order-1');
        expect(podiumCards[1].classes()).toContain('sm:order-2');

        // Card index 2 is 3rd place in DOM order: order-3 on mobile, sm:order-3 on desktop
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
});
