import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

interface Point {
    x: number;
    y: number;
}

interface Size {
    width: number;
    height: number;
}

interface EchoChatHeadGlobal {
    clampToViewport: (position: Point, viewport: Size, element: Size, margin?: number) => Point;
    parseStoredPosition: (raw: string | null) => Point | null;
    shouldDisplay: (input: { standalone: boolean; pathname: string; chatPaths: string[] }) => boolean;
    panelPosition: (bubble: Point & Size, panel: Size, viewport: Size, margin?: number) => Point;
}

const source = (path: string) => readFileSync(join(process.cwd(), path), 'utf8');

const loadEchoChatHead = (): EchoChatHeadGlobal => {
    delete (window as unknown as { EchoChatHead?: unknown }).EchoChatHead;
    new Function(source('public/js/ai-chat-head.js'))();
    return (window as unknown as { EchoChatHead: EchoChatHeadGlobal }).EchoChatHead;
};

describe('ai-chat-head helpers', () => {
    it('clamps positions inside the viewport margin', () => {
        const { clampToViewport } = loadEchoChatHead();
        const viewport = { width: 1000, height: 800 };
        const bubble = { width: 56, height: 56 };

        expect(clampToViewport({ x: -40, y: -40 }, viewport, bubble, 16)).toEqual({ x: 16, y: 16 });
        expect(clampToViewport({ x: 5000, y: 5000 }, viewport, bubble, 16)).toEqual({ x: 928, y: 728 });
    });

    it('parses a stored position and rejects garbage', () => {
        const { parseStoredPosition } = loadEchoChatHead();

        expect(parseStoredPosition('{"x":120,"y":340}')).toEqual({ x: 120, y: 340 });
        expect(parseStoredPosition('nonsense')).toBeNull();
        expect(parseStoredPosition('{"x":"left","y":10}')).toBeNull();
        expect(parseStoredPosition('')).toBeNull();
        expect(parseStoredPosition(null)).toBeNull();
    });

    it('only displays in standalone mode outside the chat routes', () => {
        const { shouldDisplay } = loadEchoChatHead();
        const chatPaths = ['/admin/ai-chat', '/admin/ai-assistant-app'];

        expect(shouldDisplay({ standalone: true, pathname: '/admin', chatPaths })).toBe(true);
        expect(shouldDisplay({ standalone: false, pathname: '/admin', chatPaths })).toBe(false);
        expect(shouldDisplay({ standalone: true, pathname: '/admin/ai-chat', chatPaths })).toBe(false);
        expect(shouldDisplay({ standalone: true, pathname: '/admin/ai-assistant-app', chatPaths })).toBe(false);
    });

    it('anchors the panel above the bubble and inside the viewport', () => {
        const { panelPosition } = loadEchoChatHead();
        const viewport = { width: 1000, height: 800 };
        const panel = { width: 360, height: 480 };

        expect(panelPosition({ x: 900, y: 700, width: 56, height: 56 }, panel, viewport, 16)).toEqual({
            x: 596,
            y: 204,
        });

        expect(panelPosition({ x: 20, y: 700, width: 56, height: 56 }, panel, viewport, 16).x).toBe(16);
    });

    it('flips the panel below the bubble when there is no room above', () => {
        const { panelPosition } = loadEchoChatHead();
        const viewport = { width: 1000, height: 800 };
        const panel = { width: 360, height: 480 };

        const flipped = panelPosition({ x: 900, y: 40, width: 56, height: 56 }, panel, viewport, 16);

        expect(flipped.y).toBe(112);
    });
});
