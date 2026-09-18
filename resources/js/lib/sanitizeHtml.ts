import DOMPurify from 'dompurify';

const escapeHtml = (value: string): string =>
    value
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

/** Sanitize the administrator-authored HTML intentionally rendered by lessons. */
export const sanitizeRichHtml = (value: string): string => {
    if (typeof window === 'undefined') return escapeHtml(value);

    return DOMPurify.sanitize(value, {
        USE_PROFILES: { html: true },
        FORBID_TAGS: [
            'script',
            'style',
            'iframe',
            'object',
            'embed',
            'form',
            'input',
            'button',
        ],
        FORBID_ATTR: ['style'],
    });
};

/** QR SVG comes from Fortify, but still crosses a v-html sink. */
export const sanitizeSvg = (value: string): string => {
    if (typeof window === 'undefined') return '';

    return DOMPurify.sanitize(value, {
        USE_PROFILES: { svg: true, svgFilters: true },
        FORBID_TAGS: ['script', 'foreignObject', 'foreignobject'],
        FORBID_ATTR: ['onload', 'onclick', 'onerror'],
    });
};
