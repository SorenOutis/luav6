import { mount } from '@vue/test-utils';
import { describe, expect, it, vi } from 'vitest';
import Shop from '@/pages/Shop.vue';
import type { MerchItem } from '@/types/merch';

// Mock Inertia Head and Link components
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

// Mock Wayfinder routes
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
        image_url: 'https://placehold.co/400x400',
        stock: 24,
        is_out_of_stock: false,
        stock_label: '24 in stock',
        url: 'https://koamishin.com/products/heavyweight-tee',
    },
    {
        id: 2,
        name: 'KOAMISHIN BSIT Techwear Hoodie',
        description: 'Ultra-soft fleece with double-lined hood.',
        price: 1450,
        currency: 'PHP',
        formatted_price: '₱1,450.00',
        image_url: 'https://placehold.co/400x400/obsidian',
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
        image_url: 'https://placehold.co/400x400',
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
        image_url: 'https://placehold.co/400x400',
        stock: 0,
        is_out_of_stock: true,
        stock_label: 'Out of Stock',
        url: 'https://koamishin.com/products/tote',
    },
];

const mountShop = () => {
    return mount(Shop, {
        props: {
            canRegister: true,
            merches: sampleMerches,
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

describe('Shop.vue Bento Grid Collection', () => {
    it('renders the Bento Grid layout with merchandise cards and brand spec tile', () => {
        const wrapper = mountShop();

        const grid = wrapper.find('.merch-bento-grid');
        expect(grid.exists()).toBe(true);

        // Contains product cards in the bento grid
        const cards = wrapper.findAll('.merch-bento-grid .merch-card');
        expect(cards.length).toBe(sampleMerches.length);

        // First card (Flagship) has title and Shop Now button pointing to koamishin.com
        const firstCard = cards[0];
        expect(firstCard.text()).toContain(
            'KOAMISHIN Signature Heavyweight Tee',
        );
        expect(firstCard.text()).toContain('₱750.00');
        expect(firstCard.text()).toContain('24 in stock');

        const shopNowLink = firstCard.find('a[href^="https://koamishin.com"]');
        expect(shopNowLink.exists()).toBe(true);
        expect(shopNowLink.text()).toContain('Coming Soon...');

        // Contains Atelier brand spec tile
        expect(wrapper.text()).toContain('KOAMISHIN × BSIT Department');
    });

    it('has clean catalog header with inventory count and external store link', () => {
        const wrapper = mountShop();

        // Catalog header contains item count and clean external link
        expect(wrapper.text()).toContain('Merchandise Catalog');
        expect(wrapper.text()).toContain('4 Items');

        const storeLink = wrapper.find('a[href^="https://koamishin.com"]');
        expect(storeLink.exists()).toBe(true);
        expect(wrapper.text()).toContain('Visit main store');
    });

    it('opens the Quick-View Lightbox modal when a merchandise card image is clicked', async () => {
        const wrapper = mountShop();

        // Initially, modal dialog should not be rendered
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        // Find the first card's image button trigger
        const firstCardImageBtn = wrapper.find(
            '.merch-bento-grid .merch-card button.group\\/img',
        );
        expect(firstCardImageBtn.exists()).toBe(true);

        // Click to open quick-view lightbox
        await firstCardImageBtn.trigger('click');

        // Modal should now be open
        const modal = wrapper.findComponent({ name: 'ShopQuickViewModal' });
        expect(modal.exists()).toBe(true);
        expect(modal.props('open')).toBe(true);
        expect(modal.props('merch')?.name).toBe(
            'KOAMISHIN Signature Heavyweight Tee',
        );
    });

    it('renders dynamic variant buttons and updates hero showcase image on click', async () => {
        const wrapper = mountShop();

        // Check variant buttons rendered
        const variantButtons = wrapper.findAll('.shop-root button.rounded-lg');
        const obsidianBtn = variantButtons.find(
            (btn) => btn.text() === 'Obsidian',
        );
        const alabasterBtn = variantButtons.find(
            (btn) => btn.text() === 'Alabaster',
        );

        expect(obsidianBtn?.exists()).toBe(true);
        expect(alabasterBtn?.exists()).toBe(true);

        // Find hero image
        const heroImg = wrapper.find('section[aria-label*="Showcase"] img');
        expect(heroImg.exists()).toBe(true);

        // Click Alabaster
        await alabasterBtn?.trigger('click');
        expect(heroImg.attributes('src')).toBe(
            'https://placehold.co/800x800/alabaster.jpg',
        );

        // Click Obsidian
        await obsidianBtn?.trigger('click');
        expect(heroImg.attributes('src')).toBe(
            'https://placehold.co/800x800/obsidian.jpg',
        );
    });

    it('renders tabs when 1 variant is uploaded per merch item across different merches', async () => {
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

        const wrapper = mount(Shop, {
            props: {
                canRegister: true,
                merches: merchesWithOneVariantEach,
            },
            global: {
                mocks: {
                    $page: {
                        props: { auth: { user: null }, schoolBranding: null },
                        url: '/shop',
                    },
                },
            },
        });

        const variantButtons = wrapper.findAll('.shop-root button.rounded-lg');
        const alabasterBtn = variantButtons.find(
            (btn) => btn.text() === 'Alabaster',
        );
        const obsidianBtn = variantButtons.find(
            (btn) => btn.text() === 'Obsidian',
        );

        expect(alabasterBtn?.exists()).toBe(true);
        expect(obsidianBtn?.exists()).toBe(true);

        await obsidianBtn?.trigger('click');
        expect(
            wrapper
                .find('section[aria-label*="Showcase"] img')
                .attributes('src'),
        ).toBe('https://placehold.co/800x800/obsidian-lone.jpg');

        await alabasterBtn?.trigger('click');
        expect(
            wrapper
                .find('section[aria-label*="Showcase"] img')
                .attributes('src'),
        ).toBe('https://placehold.co/800x800/alabaster-lone.jpg');
    });
});
