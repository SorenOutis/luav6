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
        description: '240 GSM combed cotton with high-density embroidered insignia.',
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
        name: 'LSI Developer Minimalist Hoodie',
        description: 'Ultra-soft fleece with double-lined hood.',
        price: 1450,
        currency: 'PHP',
        formatted_price: '₱1,450.00',
        image_url: 'https://placehold.co/400x400',
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

describe('Shop.vue Infinite Marquee Carousel', () => {
    it('renders the infinite carousel track with duplicate sets for right-to-left loop', () => {
        const wrapper = mountShop();

        const track = wrapper.find('.merch-carousel-track');
        expect(track.exists()).toBe(true);
        expect(track.classes()).toContain('merch-carousel-track');

        // Contains cards in the track
        const cards = wrapper.findAll('.merch-carousel-track .merch-card');
        // Normalized repeat expands 4 items to at least 8 items * 2 sets = 16 cards
        expect(cards.length).toBeGreaterThanOrEqual(16);

        // First card has title and Shop Now button pointing to koamishin.com
        const firstCard = cards[0];
        expect(firstCard.text()).toContain('KOAMISHIN Signature Heavyweight Tee');
        expect(firstCard.text()).toContain('₱750.00');
        expect(firstCard.text()).toContain('24 in stock');

        const shopNowLink = firstCard.find('a[href^="https://koamishin.com"]');
        expect(shopNowLink.exists()).toBe(true);
        expect(shopNowLink.text()).toContain('Coming Soon...');
    });

    it('has clean desktop header with external link and no bulky toggle buttons', () => {
        const wrapper = mountShop();

        // Desktop controls wrapper has no Carousel/Grid toggle
        expect(wrapper.find('button[title="Grid view"]').exists()).toBe(false);
        expect(wrapper.find('button[title="Infinite Carousel view"]').exists()).toBe(false);

        // Desktop header contains the clean external link
        const desktopLink = wrapper.find('.hidden.sm\\:flex a[href^="https://koamishin.com"]');
        expect(desktopLink.exists()).toBe(true);
        expect(desktopLink.text()).toContain('Visit main store');
    });

    it('has simplified mobile action bar with single-line pause button', async () => {
        const wrapper = mountShop();

        const track = wrapper.find('.merch-carousel-track');
        expect(track.classes()).not.toContain('is-paused');

        // Mobile simplified pause button
        const mobilePauseBtn = wrapper.find('.sm\\:hidden button');
        expect(mobilePauseBtn.exists()).toBe(true);
        expect(mobilePauseBtn.text()).toContain('Pause');

        // Click to pause
        await mobilePauseBtn.trigger('click');
        expect(track.classes()).toContain('is-paused');
        expect(mobilePauseBtn.text()).toContain('Play');

        // Click again to resume
        await mobilePauseBtn.trigger('click');
        expect(track.classes()).not.toContain('is-paused');
        expect(mobilePauseBtn.text()).toContain('Pause');
    });

    it('opens the Quick-View Lightbox modal when a merchandise card image is clicked', async () => {
        const wrapper = mountShop();

        // Initially, modal dialog should not be rendered
        expect(wrapper.find('[role="dialog"]').exists()).toBe(false);

        // Find the first card's image button trigger
        const firstCardImageBtn = wrapper.find('.merch-carousel-track .merch-card button.group\\/img');
        expect(firstCardImageBtn.exists()).toBe(true);

        // Click to open quick-view lightbox
        await firstCardImageBtn.trigger('click');

        // Modal should now be open
        const modal = wrapper.findComponent({ name: 'ShopQuickViewModal' });
        expect(modal.exists()).toBe(true);
        expect(modal.props('open')).toBe(true);
        expect(modal.props('merch')?.name).toBe('KOAMISHIN Signature Heavyweight Tee');
    });
});

