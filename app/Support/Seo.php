<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Server-rendered social/SEO metadata.
 *
 * Link-preview crawlers (Facebook, X/Twitter, Slack, Discord, WhatsApp,
 * iMessage, LinkedIn) never execute JavaScript, so the og:/twitter: tags that
 * resources/js/components/Seo/SeoHead.vue writes with Inertia's <Head>
 * component are invisible to them — the shared link renders as a bare title
 * with no image. This resolver produces the same values server-side so
 * resources/views/app.blade.php can inline them into the first HTML response.
 *
 * The registry below mirrors the <SeoHead> props each page declares. A feature
 * test (tests/Feature/SeoHeadTest.php) asserts the copy still matches the Vue
 * source, so changing page copy without updating this map fails CI instead of
 * silently shipping a stale preview.
 */
class Seo
{
    /**
     * Default dimensions of the social card, matching the actual asset.
     */
    public const IMAGE_WIDTH = 1200;

    public const IMAGE_HEIGHT = 630;

    /**
     * Per-Inertia-component social metadata, keyed by component name
     * (resources/js/pages/{component}.vue).
     *
     * Only public, indexable pages belong here. Anything missing from this map
     * is treated as private and gets no social tags at all, so authenticated
     * pages never publish a preview card.
     *
     * @var array<string, array{title: string, description: string, type: string}>
     */
    private const PAGES = [
        'Welcome' => [
            'title' => 'LSI - KOAMISHIN | Make every assessment count',
            'description' => 'LSI helps teachers see what learners understand, give useful feedback, and plan what to teach next.',
            'type' => 'website',
        ],
        'About' => [
            'title' => 'About LSI - KOAMISHIN | Why we build for the next lesson',
            'description' => 'Learn why LSI exists and how it helps schools connect assessment, feedback, and the next lesson.',
            'type' => 'article',
        ],
        'Shop' => [
            'title' => 'Shop - KOAMISHIN | Official Merchandise & Gear',
            'description' => 'Browse official KOAMISHIN apparel, accessories, and gear for educators, learners, and creators. Exclusively fulfilled on koamishin.com.',
            'type' => 'website',
        ],
        'HowItWorks' => [
            'title' => 'How It Works — LSI | From enrollment to achievement',
            'description' => 'Five clear steps: enroll, take exams, get instant feedback, track progress, and celebrate milestones with LSI.',
            'type' => 'article',
        ],
        'Blog/AssessmentToNextLesson' => [
            'title' => 'From Assessment to Next Lesson — LSI Pillar Guide',
            'description' => 'How teachers turn assessment responses into clear next steps: collect, understand, and reteach with section-targeted follow-up and reviewable AI feedback on LSI.',
            'type' => 'article',
        ],
        'Privacy' => [
            'title' => 'Privacy Policy | LSI - KOAMISHIN',
            'description' => 'How LSI collects, uses, shares, and deletes account, learning, and support data.',
            'type' => 'article',
        ],
        'Terms' => [
            'title' => 'Terms and Conditions | LSI - KOAMISHIN',
            'description' => 'The rules for using LSI accounts, content, learning features, and support.',
            'type' => 'article',
        ],
        'Cookies' => [
            'title' => 'Cookie Policy | LSI - KOAMISHIN',
            'description' => 'Which cookies and local storage LSI uses, why each one exists, and how to control them.',
            'type' => 'article',
        ],
    ];

    /**
     * The registered page map, exposed for tests and future tooling.
     *
     * @return array<string, array{title: string, description: string, type: string}>
     */
    public static function pages(): array
    {
        return self::PAGES;
    }

    /**
     * Resolve the social metadata for an Inertia page payload.
     *
     * Page props win over the registry so a controller can override copy for a
     * specific record (e.g. a future per-artwork share page) without touching
     * this map.
     *
     * @param  array<string, mixed>  $page  The Inertia page array handed to the root view.
     * @return array<string, mixed>|null Null when the page is not public.
     */
    public static function forPage(array $page, Request $request): ?array
    {
        $component = is_string($page['component'] ?? null) ? $page['component'] : null;
        $entry = $component === null ? null : (self::PAGES[$component] ?? null);

        if ($entry === null) {
            return null;
        }

        $props = is_array($page['props'] ?? null) ? $page['props'] : [];

        $siteName = (string) config('seo.site_name', config('app.name', 'LSI'));
        $origin = self::origin($request);

        $title = self::firstString($props['title'] ?? null, $entry['title']) ?? $siteName;
        $description = self::firstString($props['description'] ?? null, $entry['description'])
            ?? (string) config('seo.description', '');

        return [
            'siteName' => $siteName,
            'title' => $title,
            'description' => $description,
            'canonical' => self::canonical($request, $origin),
            'image' => self::absoluteUrl(
                self::firstString($props['og_image'] ?? null, config('seo.og_image')),
                $origin,
            ),
            'imageWidth' => self::IMAGE_WIDTH,
            'imageHeight' => self::IMAGE_HEIGHT,
            'imageAlt' => $title,
            'type' => $entry['type'],
            'locale' => (string) config('seo.locale', 'en_US'),
            'robots' => 'index, follow',
        ];
    }

    /**
     * The absolute origin social tags should point at.
     *
     * `seo.site_url` (APP_URL) is authoritative so previews keep pointing at the
     * public domain even when the request arrives on an internal hostname.
     * Falls back to the request root when the config is left at a placeholder.
     */
    private static function origin(Request $request): string
    {
        $configured = rtrim((string) config('seo.site_url', ''), '/');

        if (preg_match('#^https?://#i', $configured) === 1) {
            return $configured;
        }

        return rtrim($request->root(), '/');
    }

    /**
     * Canonical URL for the current request.
     *
     * Mirrors resolveCanonicalUrl() in resources/js/lib/seo.ts: query strings
     * and fragments are dropped, the path is lowercased and collapsed to single
     * slashes, and only the root keeps a trailing slash.
     */
    private static function canonical(Request $request, string $origin): string
    {
        $path = '/'.ltrim($request->path(), '/');
        $path = (string) preg_replace('#/+#', '/', $path);
        $path = strtolower($path);

        if ($path !== '/') {
            $path = rtrim($path, '/');
        }

        return $path === '/' ? $origin.'/' : $origin.$path;
    }

    /**
     * Absolutize a site-relative asset path against the resolved origin.
     */
    private static function absoluteUrl(?string $candidate, string $origin): string
    {
        if ($candidate === null || $candidate === '') {
            return '';
        }

        if (preg_match('#^https?://#i', $candidate) === 1) {
            return $candidate;
        }

        return $origin.'/'.ltrim($candidate, '/');
    }

    /**
     * First non-blank string from a list of candidates.
     */
    private static function firstString(mixed ...$values): ?string
    {
        foreach ($values as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
