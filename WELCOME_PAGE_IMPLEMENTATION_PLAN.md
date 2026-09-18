# Welcome Page Implementation Plan

**Project:** LSI — KOAMISHIN  
**Scope:** Public welcome page at `/`  
**Status:** Planning only. No application code has been modified in this planning pass.  
**Primary objective:** Implement the approved minimalist landing-page direction without breaking authentication, routing, SEO, performance protections, pricing integrity, or responsive behavior.

## 1. Executive recommendation

The current welcome page should be **refactored rather than incrementally patched**. It currently combines a highly animated technical hero, animated feature cards, live counters, a video walkthrough, a technology carousel, pricing, and a highly decorative footer. The approved direction is substantially calmer: a clear teacher-focused promise, one distinctive assessment-sheet visual, a short “How it works” explanation, practical features, honest pricing, a final CTA, and a minimal footer.

The safest implementation is to preserve the existing public route and authentication behavior while replacing the landing-page presentation layer. Backend changes should be limited to removing props that are no longer rendered and confirming any business claims before they are hard-coded into the page.

> **Target product story:** LSI helps a teacher see what learners understand and decide what to teach next.

## 2. Current-state findings

| Area | Current implementation | Risk or issue | Required treatment |
|---|---|---|---|
| Route | `/` renders `WelcomeController` and the Inertia `Welcome` page. | Route behavior is correct and should remain stable. | Preserve the named `home` route. |
| Hero | `WelcomeHero.vue` uses “LEARNING SYSTEMS INTELLIGENCE”, particle effects, character animation, parallax, three CTAs, and technical language. | Strong visual effort, but the message is broad and the page feels “vibe coded” rather than product-specific. | Replace with one direct promise and a simple assessment-sheet-to-next-lesson artifact. |
| Feature section | `FeatureCards.vue` contains “Assessment Intelligence”, “Feedback Intelligence”, and “Progress Intelligence”, animated bars, tilt effects, expandable cards, and code labels such as `MOD_EXM_01`. | Decorative complexity competes with comprehension and creates continuous work on desktop. | Replace with three static, practical feature blocks. |
| Stats | `Welcome.vue` renders Students, Exams Created, Assignments, and Submissions counters. | The numbers require careful interpretation and are not necessary for the minimalist page. | Remove unless product owners explicitly want verified live proof. |
| How it works | The page includes a walkthrough video and a disabled five-step card block (`v-if="false"`). | Dead markup and an expensive media experience complicate maintenance. | Keep one short three-step explanation; retain video only if there is a confirmed content and accessibility reason. |
| Tech stack | `TechStackCarousel.vue` is rendered on the welcome page. | Technology names are not a primary visitor benefit. | Remove from the public welcome page; leave the component file untouched until repository-wide usage is checked. |
| Pricing | `PricingSection.vue` contains three plans, a full feature matrix, and custom-plan links to `#contact`. The Starter description promises instant AI feedback while the matrix marks AI feedback unavailable for free. | This is a trust and conversion risk. `#contact` is not a real section in the current Welcome page. | Simplify the cards, reconcile every claim with the actual product, and use a real sales destination. |
| Header | `WelcomeHeader.vue` contains useful auth-aware navigation but relies on section anchors and branding props. | Some anchors are page-specific and the target brand/data relationship is unclear. | Keep auth logic, simplify navigation, and use stable section IDs plus named routes. |
| Footer | `WelcomeFooter.vue` contains multiple `#` links, anchors to `#engine` and `#metrics` that do not exist, placeholder social links, and a newsletter form with no submit behavior. | Visible unfinished links undermine trust and accessibility. | Remove placeholders, use real destinations, and omit unavailable links. |
| Backend props | `WelcomeController.php` passes counts, season data, demo URL, and registration state. `schoolBranding` is typed in `Welcome.vue` but is not passed by the controller. | Unused or inconsistent data contracts create accidental fallbacks and naming drift. | Define a small, explicit prop contract after the target page is finalized. |
| Validation | The frontend type check currently encounters missing generated Wayfinder modules, and production build is blocked when PHP/Composer are unavailable. | Code validation cannot be trusted until route generation prerequisites are restored. | Treat environment setup as a release gate before implementation validation. |

## 3. Approved target information architecture

The page should follow this order and should not add extra sections merely to increase page length.

| Order | Section | Purpose | Required content |
|---:|---|---|---|
| 1 | Header | Establish the brand and provide a clear escape path. | `LSI — KOAMISHIN`, Product or Features, How it works, Pricing, About, Login, Create a free account. |
| 2 | Hero | Explain the value in seconds. | “Make every assessment count.”; “See what learners understand, then plan what to teach next.”; one primary CTA; one secondary link; assessment-sheet visual. |
| 3 | How it works | Explain the actual teacher workflow. | Create an assessment; Review responses; Plan the next lesson. |
| 4 | Essentials | Summarize the product without technical jargon. | Assessments, Feedback, Class progress. |
| 5 | Pricing | Make the buying path understandable and honest. | Starter, Classroom, District; verified descriptions; real CTAs. |
| 6 | Final CTA | Provide one final action. | “Know what to teach next.”; Create a free account; Contact sales. |
| 7 | Footer | Provide only useful, real links. | Brand, active routes, canonical contact address, legal destinations when available. |

The separate Product Proof section should remain **out of the target page**. The hero already provides product proof through the assessment artifact, and another dashboard section would repeat the same idea.

## 4. Content and naming contract

Before coding, freeze the following copy in one source-of-truth object or content module. Do not duplicate strings across multiple components where a shared constant is practical.

### Brand

Use **LSI — KOAMISHIN** consistently in the visible header, footer, page title, and structured metadata. If KOAMISHIN is the company and LSI is the product, use “LSI — KOAMISHIN” consistently until a final brand lockup is supplied. Remove fallback labels such as “LSI Engine” and “LSI Learning Engine” from this public page unless product ownership explicitly requires them.

### Hero

Use the following target copy:

- Eyebrow: **A LEARNING PLATFORM FOR SCHOOLS**
- Headline: **Make every assessment count.**
- Supporting text: **See what learners understand, then plan what to teach next.**
- Primary CTA: **Create a free account**
- Secondary link: **See how it works**
- Supporting line: **Teacher-controlled · built for schools**

The visual should be a lightweight semantic assessment sheet, not a decorative dashboard. It may show “Grade 8 Math · Quadratic Equations”, a sample question, one highlighted response, and a “Next lesson · Factoring practice” note. The artifact must remain understandable without animation.

### How it works

Use only three practical steps:

1. **Create an assessment** — Choose questions and publish.
2. **Review responses** — See where learners are stuck.
3. **Plan the next lesson** — Assign focused follow-up.

### Features

Use three flat, readable feature blocks:

- **Assessments** — Create quizzes and assignments that fit your class.
- **Feedback** — Review, adjust, and share feedback before learners see it.
- **Class progress** — Keep track of what needs attention over time.

### Pricing

Use the following as proposed copy, but verify the commercial truth before implementation:

| Plan | Price label | Description | CTA |
|---|---|---|---|
| Starter | Free | Essential tools for teachers. | Create a free account |
| Classroom | Custom | For a class or school. | Contact sales |
| District | Custom | For schools working together. | Contact sales |

Do not publish “Starter is free. Paid plans include a 14-day trial.” until the business owner confirms that exact rule. The current source contains a contradiction between the Starter description and the feature matrix, so the plan matrix must be reviewed line by line before it is reintroduced.

## 5. File-by-file implementation plan

### 5.1 `resources/js/pages/Welcome.vue`

Refactor this into a thin page shell. It should own page-level props, SEO metadata, the root theme class, section ordering, and only the minimum animation lifecycle required by the approved design.

Remove the following from the page shell unless a later requirement explicitly restores them: neural particle background, animated statistics, Lenis synchronization, the disabled five-step block, the technology carousel, and the demo modal. Use stable section IDs such as `how-it-works`, `features`, and `pricing` so header navigation has real targets.

Keep auth-aware routing. Authenticated visitors should see Dashboard/Open Dashboard behavior where appropriate; unauthenticated visitors should see Create a free account and Login. Avoid constructing new ad hoc URLs where a named Wayfinder route already exists.

Update `Head` and `SeoHead` so the page title, description, JSON-LD name, and visible brand all agree. The description should describe assessments, feedback, and next-lesson planning without claiming capabilities that are not available in the selected plan.

### 5.2 `resources/js/components/welcome/WelcomeHeader.vue`

Keep the existing auth-aware header and mobile sheet because it already handles mobile menu state and theme toggling. Simplify its visual treatment to match the light editorial page.

Use only anchors whose IDs exist on `/`, and use route navigation for `/about` and `/how-it-works`. If the header is rendered on a dedicated page, preserve `hideScrollNav` behavior. Add or preserve visible keyboard focus styles and ensure the mobile menu closes after navigation.

Use the canonical brand label. Do not allow the current fallback branding logic to silently replace the agreed public name.

### 5.3 `resources/js/components/welcome/WelcomeHero.vue`

Rewrite the hero layout around two columns on large screens and a single stack on small screens. The left side contains the copy and actions. The right side contains a new semantic assessment artifact component, preferably extracted as `AssessmentArtifact.vue` under `resources/js/components/welcome/` so it can be tested independently.

The artifact should use real HTML text and CSS/Tailwind layout rather than a raster image. It must not depend on canvas, a large background image, or generated decorative media. This keeps text accessible, responsive, and maintainable.

Limit entrance motion to a short opacity/transform reveal. Gate non-essential motion behind reduced-motion and low-end-device signals. Do not use character splitting, parallax, hover magnetism, or continuous visual effects in the hero.

### 5.4 `resources/js/components/welcome/FeatureCards.vue`

Replace the current accordion and animated fragment bars with three static feature blocks. Keep the filename if it avoids unnecessary import churn, but remove the GSAP lifecycle, generated bars, mouse-following tilt, hover glow, and nested action controls.

Each block should be a semantic article with an optional icon, a practical title, and one concise description. Do not make the entire article a clickable `div`. If a feature needs a destination, use a real link or a real button with an implemented action.

### 5.5 `resources/js/components/welcome/PricingSection.vue`

Rewrite the pricing section as three simple cards. Remove the large comparison matrix from the welcome page unless the product owner specifically requires it. A separate pricing/detail page is a better home for a full matrix.

Before merging, reconcile the free-tier description, AI feedback availability, essay grading availability, trial language, and paid-plan contact flow with the actual product rules. Replace `href: '#contact'` with a real contact route or the canonical sales email after confirmation. Do not invent an email address in the UI.

### 5.6 `resources/js/components/welcome/WelcomeFooter.vue`

Reduce the footer to active links only. Remove every placeholder `#` destination, including placeholder social links and unavailable resources. Remove or implement the newsletter form; a form with `@submit.prevent` and no submission behavior must not remain visible.

Remove links to nonexistent anchors such as `#engine` and `#metrics`. Include the canonical email only after it is confirmed. Legal links should point to real legal pages; if those pages do not exist, omit the links until they are implemented.

### 5.7 `resources/js/components/welcome/NeuralParticleNetwork.vue`, `TechStackCarousel.vue`, and `DemoVideoModal.vue`

Do not delete these files immediately. First search the repository for all imports. Remove them from the welcome page, then delete only if they are unused and deletion is approved. If the demo video remains a product requirement, expose it from the How It Works section with an accessible button, poster, `controls`, and a non-video fallback.

### 5.8 `resources/css/app.css`

Prefer page-scoped Tailwind classes and the existing semantic tokens. Avoid changing global dashboard themes to achieve the welcome-page look. If page-specific tokens are needed, scope them under `.welcome-root` and document why they exist.

The existing low-end rules and reduced-motion protections are release-critical. Preserve the selectors that disable backdrop filters, looping effects, and expensive background work on constrained devices. Add new performance rules only if they are measured and necessary.

## 6. Safe implementation sequence

| Phase | Work | Checkpoint |
|---:|---|---|
| 0 | Create a feature branch, confirm working tree state, install PHP/Composer dependencies, generate Wayfinder route files, and run the current baseline tests. | Baseline test and build results recorded before edits. |
| 1 | Freeze brand, copy, CTA, pricing, canonical email, and whether the video remains. | No unresolved business claim is hard-coded. |
| 2 | Refactor the page shell, header, hero, and assessment artifact. | `/` renders for guests and authenticated users; mobile menu and CTAs work. |
| 3 | Replace feature cards and simplify/remove pricing matrix. | No placeholder links, no dead anchors, no nested interactive elements. |
| 4 | Rewrite footer and clean unused imports/components after repository-wide search. | Every visible link has a real destination or is removed. |
| 5 | Add/update tests and run formatting, type checks, JS tests, PHP feature tests, and production build. | All required checks pass, or failures are explicitly environment-only and documented. |
| 6 | Perform browser QA at desktop and mobile widths, with light/dark theme, reduced motion, coarse pointer, guest, and authenticated states. | Acceptance checklist passes before merge. |

## 7. Testing and verification matrix

### Automated checks

| Check | Expected result |
|---|---|
| `php artisan test --compact tests/Feature/WelcomeMobilePerformanceTest.php` | Welcome route remains successful and early low-end detector remains present. |
| `npm run test:js` | Existing welcome/mobile performance tests pass after expectations are updated for removed decorative components. |
| `npm run types:check` | No missing route declarations or page/component type errors. Run Wayfinder generation first. |
| `npm run lint:check` | No ESLint errors in changed Vue/TypeScript files. |
| `npm run format:check` | Formatting is clean. |
| `npm run build` | Vite build completes after PHP/Composer and route-generation prerequisites are available. |

### Component and content tests to add or update

Add focused tests for the new hero artifact and page content. Assert the presence of the agreed headline, CTA, supporting copy, assessment example, and real section IDs. Assert that placeholder `href="#"` links are absent from the welcome components. Assert that the mobile/low-end path does not mount continuous animation work.

Add or update tests for auth-aware CTA behavior: guests receive registration/login links, while authenticated users receive the dashboard link. Add a controller/Inertia assertion for the final prop contract so removed or stale props do not silently return.

### Manual browser checklist

| Scenario | Acceptance criteria |
|---|---|
| Guest desktop | Hero copy is understandable without scrolling; Create a free account and Login work. |
| Authenticated desktop | Dashboard action replaces registration action correctly. |
| Mobile width | Header menu works; hero stacks cleanly; assessment artifact remains readable; no horizontal overflow. |
| Keyboard only | All navigation, buttons, disclosure controls, and CTAs are reachable with visible focus. |
| Reduced motion | No character-split, parallax, continuous loops, or delayed content reveal is required to understand the page. |
| Dark theme | Text, cards, borders, and CTAs maintain readable contrast and do not inherit an accidental invisible color. |
| Slow or constrained device | No large media is eagerly downloaded solely for decoration; the page remains usable without animation. |
| Link audit | No visible placeholder hash links, nonexistent anchors, fake social destinations, or dead contact actions remain. |
| Pricing audit | Every displayed plan claim matches the confirmed commercial rules. |
| SEO | Title, description, visible brand, canonical route, and structured metadata agree. |

## 8. Release gates and risk controls

**Do not merge until the naming decision is locked.** The visible brand, page metadata, structured data, header fallback, footer wordmark, and any favicon/alt text must use the same relationship between LSI and KOAMISHIN.

**Do not merge until pricing is reconciled.** The current implementation contains a free-tier/AI-feedback contradiction. If the business rule is not confirmed, omit the disputed matrix detail rather than guessing.

**Do not merge with placeholder links.** It is better to omit a Resources, Careers, or social link than to render a link that goes nowhere.

**Do not hide build failures.** The current environment has shown that Wayfinder route generation depends on PHP/Composer. Restore that prerequisite, regenerate route files, and rerun the complete validation suite before judging the implementation.

**Do not reintroduce decorative complexity during implementation.** The approved design is intentionally quieter. Any new animation, media asset, metric, or interactive control should require a specific user-facing reason and a corresponding test or acceptance criterion.

## 9. Definition of done

The implementation is complete when the public `/` route presents the approved minimalist information architecture, uses the agreed LSI — KOAMISHIN naming and CTA language, shows a distinctive but lightweight assessment artifact, contains only practical sections, has verified pricing and contact paths, preserves guest/authenticated behavior, passes responsive/accessibility/performance checks, and builds successfully after route generation.

At that point, the remaining work should be visual polish—not another structural redesign.

## Repository references

- `routes/web.php` — public home route and named routes.
- `app/Http/Controllers/WelcomeController.php` — current Welcome Inertia prop contract.
- `resources/js/pages/Welcome.vue` — current page shell and section ordering.
- `resources/js/components/welcome/WelcomeHero.vue` — current hero and animation logic.
- `resources/js/components/welcome/FeatureCards.vue` — current feature-card data and interactions.
- `resources/js/components/welcome/PricingSection.vue` — current pricing tiers and feature matrix.
- `resources/js/components/welcome/WelcomeHeader.vue` — shared public navigation and auth actions.
- `resources/js/components/welcome/WelcomeFooter.vue` — current footer links, newsletter form, and status elements.
- `resources/css/app.css` — global semantic tokens and low-end/reduced-motion rules.
- `tests/Feature/WelcomeMobilePerformanceTest.php` — current route and performance regression coverage.
- `tests/js/welcome-mobile.test.ts` — current frontend mobile/performance coverage.
