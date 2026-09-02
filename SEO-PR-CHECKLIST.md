# SEO PR Checklist — https://lsi.koamishin.com

This PR aggregates all SEO work done direct to `main` (now 97/100 lab) for review:

**Included (already on `main` via 8 commits):**
- OG 1200x630 `public/brand/og-cover.png` + `SeoHead` dims/alt
- Sitemap dynamic `lastmod` + `image:image` + filemtime
- Robots `Allow: /brand/` + `Crawl-delay: 10` + `Disallow: /library,/chats,/activities,/calendar`
- Per-page `SeoHead` title + `Organization`/`BreadcrumbList`/`Article` (no deprecated `HowTo`)
- Canonical normalize lowercase + trailing-slash
- X-Robots-Tag on `LibraryHubController`
- WelcomeHero `fetchpriority=high` + preconnect/dns-prefetch
- GEO 140w FAQs + founder Person + testimonial Review + pillar `/blog/assessment-to-next-lesson` + ItemList
- `sameAs` → `koamishin.com`, `dccp.edu.ph`

**CI checks (must pass):**
- `npm run lint:check` — eslint 0 errors
- `npm run format:check` — prettier 0
- `npm run types:check` — vue-tsc 0
- `vendor/bin/pint --test`
- `npm run test:js` — 198 passed

**Verify live after merge:**
- `curl -s https://lsi.koamishin.com/sitemap.xml | grep -c "<url>"` >=4
- `curl -s https://lsi.koamishin.com/robots.txt | grep Allow`
- `curl -I https://lsi.koamishin.com/brand/og-cover.png` → 200
- Rich Results Test: 0 errors for Organization/BreadcrumbList/Article

