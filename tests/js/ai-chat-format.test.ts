import { readFileSync } from 'node:fs';
import { join } from 'node:path';
import { describe, expect, it } from 'vitest';

interface SseParser {
    push: (chunk: string) => string[];
    reset: () => void;
}

interface EchoChatGlobal {
    escapeHtml: (raw: unknown) => string;
    formatMarkdown: (raw: unknown) => string;
    createSseParser: () => SseParser;
}

const source = (path: string) => readFileSync(join(process.cwd(), path), 'utf8');

const loadEchoChat = (): EchoChatGlobal => {
    delete (window as unknown as { EchoChat?: unknown }).EchoChat;
    new Function(source('public/js/ai-chat-format.js'))();
    return (window as unknown as { EchoChat: EchoChatGlobal }).EchoChat;
};

describe('ai-chat-format helpers', () => {
    it('escapes HTML entities', () => {
        const { escapeHtml } = loadEchoChat();

        expect(escapeHtml('<img src=x onerror="alert(1)">\'')).toBe(
            '&lt;img src=x onerror=&quot;alert(1)&quot;&gt;&#39;',
        );
    });

    it('escapes raw HTML before applying markdown', () => {
        const { formatMarkdown } = loadEchoChat();

        const html = formatMarkdown('<script>alert(1)</script> **bold**');

        expect(html).not.toContain('<script>');
        expect(html).toContain('&lt;script&gt;');
        expect(html).toContain('<strong');
        expect(html).toContain('bold');
    });

    it('strips javascript: and data: links but keeps safe ones', () => {
        const { formatMarkdown } = loadEchoChat();

        expect(formatMarkdown('[click](javascript:alert(1))')).not.toContain('href');
        expect(formatMarkdown('[click](java\nscript:alert(1))')).not.toContain('href');
        expect(formatMarkdown('[notes](data:text/html;base64,AAAA)')).not.toContain('href');

        const safe = formatMarkdown('[docs](https://example.com/docs)');
        expect(safe).toContain('href="https://example.com/docs"');
        expect(safe).toContain('rel="noopener noreferrer"');
    });

    it('renders tables from pipe blocks', () => {
        const { formatMarkdown } = loadEchoChat();

        const html = formatMarkdown('| Name | XP |\n| --- | --- |\n| Ada | 120 |');

        expect(html).toContain('<table');
        expect(html).toContain('<th');
        expect(html).toContain('Ada');
        expect(html).toContain('120');
    });
});

describe('createSseParser', () => {
    it('reassembles frames split across chunks', () => {
        const parser = loadEchoChat().createSseParser();

        expect(parser.push('data: {"type":"text_delta"}\n\ndata: [DO')).toEqual(['{"type":"text_delta"}']);
        expect(parser.push('NE]\n\n')).toEqual(['[DONE]']);
        expect(parser.push('')).toEqual([]);
    });

    it('normalizes CRLF and joins multi-line data fields', () => {
        const parser = loadEchoChat().createSseParser();

        expect(parser.push('event: note\r\ndata: line one\r\ndata: line two\r\n\r\n')).toEqual(['line one\nline two']);
    });

    it('ignores comments and frames without data', () => {
        const parser = loadEchoChat().createSseParser();

        expect(parser.push(': keep-alive\n\n')).toEqual([]);
        expect(parser.push('event: ping\n\n')).toEqual([]);
        expect(parser.push('data: payload')).toEqual([]);
        expect(parser.push('\n\n')).toEqual(['payload']);
    });
});

describe('admin chat wiring', () => {
    it('loads the shared helper before the chat script', () => {
        const page = source('resources/views/filament/pages/ai-chat.blade.php');

        const helperPosition = page.indexOf('js/ai-chat-format.js');
        const scriptPosition = page.indexOf("@include('filament.pages.ai-chat-script')");

        expect(helperPosition).toBeGreaterThan(-1);
        expect(scriptPosition).toBeGreaterThan(-1);
        expect(helperPosition).toBeLessThan(scriptPosition);
    });

    it('delegates rendering and SSE parsing to the shared helper', () => {
        const script = source('resources/views/filament/pages/ai-chat-script.blade.php');

        expect(script).toContain('window.EchoChat.formatMarkdown(raw)');
        expect(script).toContain('window.EchoChat.createSseParser()');
    });

    it('cleans up timers, listeners, and audio on destroy', () => {
        const script = source('resources/views/filament/pages/ai-chat-script.blade.php');

        expect(script).toContain('destroy() {');
        expect(script).toContain('this.expirationTimer = null;');
        expect(script).toContain('this.searchTimer = null;');
        expect(script).toContain('this.streamingTimer = null;');
        expect(script).toContain('this._popStateHandler');
        expect(script).toContain("window.removeEventListener('popstate', this._popStateHandler)");
        expect(script).toContain('this.stopVoice();');
        expect(script).toContain('this.stopSpeaking();');
    });
});
