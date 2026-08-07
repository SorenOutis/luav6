import { usePage } from '@inertiajs/vue3';

export interface SeoConfig {
    siteName?: string;
    tagline?: string;
    description?: string;
    siteUrl?: string;
    ogImage?: string;
    locale?: string;
}

export type JsonLdObject = Record<string, unknown>;

/**
 * Reads the shared `seo` props injected by HandleInertiaRequests.
 * Falls back to safe defaults so components render without a backend hit.
 */
export function useSeoConfig(): SeoConfig {
    const page = usePage();
    return (page.props.seo ?? {}) as SeoConfig;
}

/** ... */
export function resolveCanonicalUrl(path: string, config: SeoConfig): string {
    const origin =
        typeof window !== 'undefined'
            ? window.location.origin
            : String(config.siteUrl ?? '').replace(/\/+$/, '');
    const base = origin.replace(/\/+$/, '');
    const clean = path === '/' ? '' : path.split('?')[0];
    return `${base}${clean}`;
}

/** ... */
export function resolveOgImage(override: string, config: SeoConfig): string {
    const candidate = override || config.ogImage || '';
    if (!candidate) return '';
    if (/^https?:\/\//.test(candidate)) return candidate;
    const origin =
        typeof window !== 'undefined'
            ? window.location.origin
            : String(config.siteUrl ?? '').replace(/\/+$/, '');
    return `${origin.replace(/\/+$/, '')}/${candidate.replace(/^\/+/, '')}`;
}

/** Normalize a single JSON-LD block or an array into an array. */
export function normalizeJsonLd(
    input?: JsonLdObject | JsonLdObject[],
): JsonLdObject[] {
    if (!input) return [];
    return Array.isArray(input) ? input : [input];
}
