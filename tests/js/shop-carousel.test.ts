import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import { nextTick } from 'vue';
import Shop from '@/pages/Shop.vue';
import type { MerchItem } from '@/types/merch';

vi.mock('@inertiajs/vue3', () => ({
    Head: { template: '<head-stub><slot /></head-stub>' },
    Link: { template: '<a :href="$attrs.href"><slot /></a>' },
    usePage: () => ({
        props: {
            auth: { user: null },
            schoolBranding: null,
        },
        url: '/shop',
    }),
}));

vi.mock('@/routes', () => ({
    dashboard: () => ({ url: '/dashboard' }),
    login: () => ({ url: '/login' }),
    register: () => ({ url: '/register' }),
}));

const sampleMerches: MerchItem[] = [
    {
        id: 1,
        name: 'KOAMISHIN Signature Heavyweight Tee',
        description:
            '240 GSM combed cotton with high-density embroidered insignia.',
        price: 750,
        currency: 'PHP',
        formatted_price: '₱750.00',
        image_url: 'https://placehold.co/400x400/tee',
        stock: 24,
        is_out_of_stock: false,
        stock_label: '24 in stock',
        url: 'https://koamishin.com/products/heavyweight-tee',
    },
    {
        id: 2,
        name: 'KOAMISHIN Techwear Hoodie',
        description: 'Ultra-soft fleece with double-lined hood.',
        price: 1450,
        currency: 'PHP',
        formatted_price: '₱1,450.00',
        image_url: 'https://placehold.co/400x400/hoodie',
        variants: [
            {
                name: 'Obsidian',
                image_url: 'https://placehold.co/800x800/obsidian.jpg',
            },
            {
                name: 'Alabaster',
                image_url: 'https://placehold.co/800x800/alabaster.jpg',
            },
        ],
        stock: 12,
        is_out_of_stock: false,
        stock_label: '12 in stock',
        url: 'https://koamishin.com/products/hoodie',
    },
    {
        id: 3,
        name: 'KOAMISHIN Structured Snapback Cap',
        description: '6-panel structured wool-blend crown.',
        price: 550,
        currency: 'PHP',
        formatted_price: '₱550.00',
        image_url: 'https://placehold.co/400x400/cap',
        stock: 4,
        is_out_of_stock: false,
        stock_label: 'Only 4 left',
        url: 'https://koamishin.com/products/snapback',
    },
    {
        id: 4,
        name: 'Classroom Canvas Field Tote',
        description: 'Heavyweight 16oz raw natural cotton canvas.',
        price: 420,
        currency: 'PHP',
        formatted_price: '₱420.00',
        image_url: 'https://placehold.co/400x400/tote',
        stock: 0,
        is_out_of_stock: true,
        stock_label: 'Out of Stock',
        url: 'https://koamishin.com/products/tote',
    },
];

const mountShop = (merches: MerchItem[] = sampleMerches) => {
    return mount(Shop, {
        props: {
            canRegister: true,
            merches,
        },
        global: {
            mocks: {
                $page: {
                    props: {
                        auth: { user: null },
                        schoolBranding: null,
                    },
                    url: '/shop',
                },
            },
        },
    });
};

describe('Shop.vue studio world', () => {
    it('renders the stage headline and the external store CTA', () => {
        const wrapper = mountShop();

        expect(wrapper.find('.studio-stage').exists()).toBe(true);
        expect(wrapper.find('.stage-title').text()).toBe('The merch line.');

        const storeLink = wrapper.find(
            '.studio-stage a[href^="https://koamishin.com"]',
        );
        expect(storeLink.exists()).toBe(true);
        expect(storeLink.text()).toContain('Visit store');
    });

    it('leads the stage with the piece that has a colorway story', async () => {
        const wrapper = mountShop();
        await nextTick();

        expect(wrapper.find('.stage-garment').attributes('src')).toBe(
            'https://placehold.co/800x800/alabaster.jpg',
        );

        expect(wrapper.findAll('.piece-tile').length).toBe(
            sampleMerches.length,
        );
    });

    it('swaps the garment in place when a colorway capsule is clicked', async () => {
        const wrapper = mountShop();
        await nextTick();

        const capsules = wrapper.findAll('.colorway-capsule');
        const obsidian = capsules.find(
            (capsule) => capsule.text() === 'Obsidian',
        );
        const alabaster = capsules.find(
            (capsule) => capsule.text() === 'Alabaster',
        );

        expect(obsidian?.exists()).toBe(true);
        expect(alabaster?.exists()).toBe(true);

        await obsidian?.trigger('click');
        expect(wrapper.find('.stage-garment').attributes('src')).toBe(
            'https://placehold.co/800x800/obsidian.jpg',
        );
        expect(
            wrapper
                .findAll('.colorway-capsule')
                .find((capsule) => capsule.text() === 'Obsidian')
                ?.attributes('aria-pressed'),
        ).toBe('true');

        await alabaster?.trigger('click');
        expect(wrapper.find('.stage-garment').attributes('src')).toBe(
            'https://placehold.co/800x800/alabaster.jpg',
        );
    });

    it('reports the on-stage piece stock honestly', async () => {
        const wrapper = mountShop();
        await nextTick();

        expect(wrapper.find('.studio-stage').text()).toContain('12 in stock');
    });

    it('renders one collection tile per piece with name, price, and stock label', () => {
        const wrapper = mountShop();

        const tiles = wrapper.findAll('.piece-tile');
        expect(tiles.length).toBe(sampleMerches.length);

        const firstTile = tiles[0];
        expect(firstTile.text()).toContain(
            'KOAMISHIN Signature Heavyweight Tee',
        );
        expect(firstTile.text()).toContain('₱750.00');
        expect(firstTile.text()).toContain('24 in stock');

        expect(tiles[3].text()).toContain('Out of Stock');
    });

    it('reports catalog counts and the reference-price note', () => {
        const wrapper = mountShop();

        expect(wrapper.text()).toContain('04 items');
        expect(wrapper.text()).toContain('03 in stock');
        expect(wrapper.text()).toContain(
            'checkout & fulfillment on koamishin.com',
        );
    });

    it('opens the detail sheet when a tile is clicked', async () => {
        const wrapper = mountShop();

        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        const firstTileOpen = wrapper.find('.piece-tile .tile-open');
        expect(firstTileOpen.exists()).toBe(true);

        await firstTileOpen.trigger('click');

        const modal = wrapper.findComponent({ name: 'ShopQuickViewModal' });
        expect(modal.exists()).toBe(true);
        expect(modal.props('open')).toBe(true);
        expect(modal.props('merch')?.name).toBe(
            'KOAMISHIN Signature Heavyweight Tee',
        );
    });

    it('shows a single colorway as one selected capsule', async () => {
        const merchesWithOneVariantEach: MerchItem[] = [
            {
                id: 1,
                name: 'Merch Item One',
                description: 'First item description',
                price: 1200,
                currency: 'PHP',
                formatted_price: '₱1,200.00',
                image_url: 'https://placehold.co/400x400/one',
                variants: [
                    {
                        name: 'Alabaster',
                        image_url:
                            'https://placehold.co/800x800/alabaster-lone.jpg',
                    },
                ],
                stock: 10,
                is_out_of_stock: false,
                stock_label: '10 in stock',
                url: 'https://koamishin.com/products/one',
            },
            {
                id: 2,
                name: 'Merch Item Two',
                description: 'Second item description',
                price: 1400,
                currency: 'PHP',
                formatted_price: '₱1,400.00',
                image_url: 'https://placehold.co/400x400/two',
                variants: [
                    {
                        name: 'Obsidian',
                        image_url:
                            'https://placehold.co/800x800/obsidian-lone.jpg',
                    },
                ],
                stock: 8,
                is_out_of_stock: false,
                stock_label: '8 in stock',
                url: 'https://koamishin.com/products/two',
            },
        ];

        const wrapper = mountShop(merchesWithOneVariantEach);
        await nextTick();

        expect(wrapper.find('.stage-garment').attributes('src')).toBe(
            'https://placehold.co/800x800/alabaster-lone.jpg',
        );

        const capsules = wrapper.findAll('.colorway-capsule');
        expect(capsules.length).toBe(1);
        expect(capsules[0].text()).toBe('Alabaster');
        expect(capsules[0].attributes('aria-pressed')).toBe('true');
    });

    it('renders an empty-stage state with a store link when there is no merch', () => {
        const wrapper = mountShop([]);

        expect(wrapper.find('.stage-garment').exists()).toBe(false);
        expect(wrapper.find('.empty-stage').exists()).toBe(true);
        expect(wrapper.find('.empty-stage').text()).toContain(
            'Nothing in the studio yet.',
        );

        const storeLink = wrapper.find('.empty-stage a');
        expect(storeLink.attributes('href')).toBe('https://koamishin.com/');
        expect(storeLink.text()).toContain('Visit store');
    });

    it('navigates pieces on stage using the stage switcher controls', async () => {
        const wrapper = mountShop();
        await nextTick();

        // Sample merches start with the hoodie (index 1) because it has variants
        expect(wrapper.find('.studio-stage h2').text()).toBe(
            'KOAMISHIN Techwear Hoodie',
        );

        const nextButtons = wrapper.findAll(
            'button[aria-label="Next piece on stage"]',
        );
        expect(nextButtons.length).toBeGreaterThan(0);

        await nextButtons[0].trigger('click');
        await nextTick();

        // Next item in array is Cap (index 2)
        expect(wrapper.find('.studio-stage h2').text()).toBe(
            'KOAMISHIN Structured Snapback Cap',
        );
    });

    it('filters collection items by stock status using the segmented control', async () => {
        const wrapper = mountShop();
        await nextTick();

        expect(wrapper.findAll('.piece-tile').length).toBe(4);

        const filterButtons = wrapper.findAll('[role="toolbar"] button');
        const inStockButton = filterButtons.find((btn) =>
            btn.text().includes('In stock'),
        );
        expect(inStockButton?.exists()).toBe(true);

        await inStockButton?.trigger('click');
        await nextTick();

        // Only 3 in-stock items
        expect(wrapper.findAll('.piece-tile').length).toBe(3);
        expect(wrapper.text()).not.toContain('Classroom Canvas Field Tote');

        const allButton = filterButtons.find((btn) =>
            btn.text().includes('All'),
        );
        await allButton?.trigger('click');
        await nextTick();

        expect(wrapper.findAll('.piece-tile').length).toBe(4);
    });

    it('renders craftsmanship commitments at the foot of the line', () => {
        const wrapper = mountShop();

        expect(wrapper.text()).toContain('Curated drops');
        expect(wrapper.text()).toContain('Heavyweight craft');
        expect(wrapper.text()).toContain('Direct fulfillment');
    });
});
