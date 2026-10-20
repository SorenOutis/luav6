import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';
import modalSource from '@/components/shop/ShopQuickViewModal.vue?raw';
import shopSource from '@/pages/Shop.vue?raw';
import welcomeSource from '@/pages/Welcome.vue?raw';

// vitest's `?raw` transform does not cover plain `.css` imports.
const readCss = (relativePath: string): string =>
    readFileSync(join(process.cwd(), relativePath), 'utf8');

const appCss = readCss('resources/css/app.css');
const studioCss = readCss('resources/js/components/shop/studio.css');

/**
 * The welcome page and the shop are one surface, and in dark mode that surface
 * is the hero demo window's own material. These guard the pairing so neither
 * page drifts back to a private palette — and so the skin cannot silently lose
 * the cascade to a theme preset.
 */
describe('Welcome and shop share one studio skin', () => {
    const skinBlock =
        /\.welcome-root,\s*\.shop-root,\s*\.st-sheet\s*\{([^}]*)\}/;
    const darkSkinBlock =
        /\.dark \.welcome-root,\s*\.dark \.shop-root,\s*\.dark \.st-sheet\s*\{([^}]*)\}/;

    const bodyOf = (block: RegExp): string => appCss.match(block)?.[1] ?? '';

    /** Comments carry prose with semicolons in it, so strip them first. */
    const declarationsOf = (block: RegExp): string[] =>
        bodyOf(block)
            .replace(/\/\*[\s\S]*?\*\//g, '')
            .split(';')
            .map((line) => line.trim())
            .filter((line) => line.startsWith('--color-'));

    it('declares the skin for the welcome root, the shop root, and the teleported sheet', () => {
        expect(appCss).toMatch(skinBlock);
        expect(appCss).toMatch(darkSkinBlock);
        expect(appCss).toContain('.st-sheet');
    });

    it('keeps the cream light theme on both pages', () => {
        const light = bodyOf(skinBlock);

        expect(light).toContain('--color-background: #f5f0e8');
        expect(light).toContain('--color-card: #faf7f2');
        expect(light).toContain('--color-secondary: #ebe5dc');
        expect(light).toContain('--color-border: #e0dbd3');
        expect(light).toContain('--color-foreground: #1a1a1e');

        // A page cannot be both modes at once.
        expect(bodyOf(darkSkinBlock)).not.toContain('--color-card: #faf7f2');
    });

    it('files welcome cards onto the page ground, in both modes', () => {
        // Welcome cards carry no fill of their own — the ground shows through
        // and the hairline separates them. The shop keeps its stage panel,
        // because that panel is the page's subject rather than a card on it.
        const welcomeCardLight = appCss.match(
            /\.welcome-root\s*\{([^}]*--color-card:[^}]*)\}/,
        )?.[1];
        const welcomeCardDark = appCss.match(
            /\.dark \.welcome-root\s*\{([^}]*--color-card:[^}]*)\}/,
        )?.[1];

        expect(welcomeCardLight).toContain('--color-card: #f5f0e8');
        expect(welcomeCardDark).toContain('--color-card: #1a1410');

        // …matching the ground declared for the same mode.
        expect(bodyOf(skinBlock)).toContain('--color-background: #f5f0e8');
        expect(bodyOf(darkSkinBlock)).toContain('--color-background: #1a1410');
    });

    it('keeps the shop stage distinct from its page ground', () => {
        const shopStage = appCss.match(/\.dark \.shop-root\s*\{([^}]*)\}/)?.[1];

        expect(shopStage).toBeUndefined();
        expect(bodyOf(darkSkinBlock)).toContain('--color-card: #14100d');
        expect(bodyOf(darkSkinBlock)).not.toContain('--color-card: #1a1410');
    });

    it('keeps the card surfaces warm, not the hero window’s cold near-blacks', () => {
        const dark = bodyOf(darkSkinBlock);

        expect(dark).toContain('--color-card: #14100d');
        expect(dark).toContain('--color-secondary: #1e1813');
        expect(dark).toContain('--color-border: #2c2520');
        expect(dark).toContain('--color-foreground: #f5f0eb');
        expect(dark).toContain('--color-muted-foreground: #a1968a');
    });

    it('never puts a blue cast on a dark surface', () => {
        // The hero window’s near-blacks (#0e0f14, #14151b) read cold against
        // the warm ground — red must never fall below blue on these surfaces.
        const surfaces = declarationsOf(darkSkinBlock)
            .filter((line) =>
                /--(color-(background|card|secondary|muted|popover|accent)|color-border|color-input):/.test(
                    line,
                ),
            )
            .map((line) => line.match(/#([0-9a-f]{6})/i)?.[1])
            .filter((hex): hex is string => Boolean(hex));

        expect(surfaces.length).toBeGreaterThanOrEqual(7);

        for (const hex of surfaces) {
            const r = parseInt(hex.slice(0, 2), 16);
            const b = parseInt(hex.slice(4, 6), 16);

            expect(b, `${hex} is blue-biased`).toBeLessThanOrEqual(r);
        }
    });

    it('leaves the page ground on its original warm tone', () => {
        // Only the cards go near-black. Repainting the ground too would swallow
        // the contrast between the page and the panels sitting on it.
        expect(bodyOf(darkSkinBlock)).toContain('--color-background: #1a1410');
        expect(bodyOf(skinBlock)).toContain('--color-background: #f5f0e8');
    });

    it('cannot lose the cascade to a theme preset after minification', () => {
        // :root[data-theme-preset='…'].dark sits at (0,3,0) and the minifier
        // strips a leading `:root` from a descendant selector, so plain
        // specificity is not enough — every declaration must be pinned.
        const declarations = [
            ...declarationsOf(skinBlock),
            ...declarationsOf(darkSkinBlock),
        ];

        expect(declarations.length).toBe(28);

        for (const declaration of declarations) {
            expect(declaration).toMatch(/!\s*important\s*$/);
        }
    });

    it('maps the shop studio layer onto the skin rather than its own palette', () => {
        expect(studioCss).toContain(
            '--st-stage-bg: var(--color-card, #faf7f2);',
        );
        expect(studioCss).toContain(
            '--st-well-bg: var(--color-secondary, #ebe5dc);',
        );
        expect(studioCss).toContain(
            '--st-tile: var(--color-secondary, #ebe5dc);',
        );
        expect(studioCss).toContain(
            '--st-ink: var(--color-foreground, #1a1a1e);',
        );
        expect(studioCss).toContain(
            '--st-ink-mute: var(--color-muted-foreground, #68625a);',
        );
        expect(studioCss).toContain('var(--color-border, #e0dbd3)');
        expect(studioCss).toContain(
            '--st-accent: var(--color-brand, #d97757);',
        );
    });

    it('keeps only the dark surfaces the theme tokens have no slot for', () => {
        // The recessed well, translucent panel, and quick-view sheet stay a
        // step below the stage so the studio keeps its depth in dark mode.
        expect(studioCss).toMatch(
            /\.dark \.shop-root,\s*\.dark \.st-sheet\s*\{/,
        );
        expect(studioCss).toContain('--st-well-bg: #0e0f11;');
        expect(studioCss).toContain('--st-panel-bg: rgba(19, 20, 22, 0.6);');
        expect(studioCss).toContain('--st-sheet-bg: rgba(29, 29, 31, 0.78);');
    });

    it('never re-declares a theme token inside a component', () => {
        for (const source of [shopSource, modalSource, welcomeSource]) {
            expect(source).not.toMatch(/--color-(card|background|secondary):/);
            expect(source).not.toMatch(/--st-(stage-bg|panel-bg|tile):/);
            expect(source).not.toMatch(/--st-(ink|ink-mute):/);
        }
    });

    it('shares the studio stylesheet between the shop page and its sheet', () => {
        expect(shopSource).toContain(
            '<style scoped src="../components/shop/studio.css"></style>',
        );
        expect(modalSource).toContain(
            '<style scoped src="./studio.css"></style>',
        );
    });
});
