import DOMPurify from 'dompurify';
import { marked } from 'marked';

/**
 * Render AI chat responses as sanitized HTML.
 *
 * marked parses the markdown (GFM: tables, strikethrough, task lists) and
 * DOMPurify strips anything dangerous (scripts, inline handlers, unknown
 * protocols) before it ever reaches v-html.
 */

marked.setOptions({
    breaks: true, // single newlines become <br> — chat messages are multi-line
    gfm: true,
});

// Open links in a new tab instead of navigating away from the app.
DOMPurify.addHook('afterSanitizeAttributes', (node) => {
    if (node.tagName === 'A') {
        node.setAttribute('target', '_blank');
        node.setAttribute('rel', 'noopener noreferrer');
    }
});

const escapeHtml = (text: string): string =>
    text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .replace(/\n/g, '<br>');

export function renderMarkdown(text: string): string {
    // SSR / no-DOM fallback: emit plain escaped text; hydration swaps in the
    // rendered markdown on the client.
    if (typeof window === 'undefined') {
        return escapeHtml(text ?? '');
    }

    const html = marked.parse(text ?? '', { async: false });

    return DOMPurify.sanitize(html as string);
}
