import { configureEcho } from '@laravel/echo-vue';

configureEcho({ broadcaster: 'null' });

// jsdom implements no CSS Object Model media queries, so `matchMedia` is
// undefined. Anything reaching for `useMobile()` / `useLenis()` — i.e. every
// component that adapts to small screens or low-end hardware — throws on
// mount without this. Defaults describe a plain desktop viewport.
if (typeof window !== 'undefined' && !window.matchMedia) {
    window.matchMedia = ((query: string) => ({
        matches: false,
        media: query,
        onchange: null,
        addEventListener: () => {},
        removeEventListener: () => {},
        addListener: () => {},
        removeListener: () => {},
        dispatchEvent: () => false,
    })) as unknown as typeof window.matchMedia;
}
