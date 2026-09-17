# Dashboard Revamp — Analysis & Plan

> Goal: make the dashboard **more interactive** (clickable, drillable, filterable)
> while **removing decorative animation**. Motion budget drops to near zero;
> every pixel of the page gains a purpose and a pointer.

> **STATUS: IMPLEMENTED (Phases 1–6)** — see §8 for what shipped and follow-ups.

## 0. Decisions (approved by user)

| Question | Decision |
|---|---|
| Scope | Build it — implemented on this branch |
| Animations | Remove decorative only; keep hover/focus color transitions |
| Today checkboxes | Whole row links to Activities (no in-dashboard submission) |
| Claim XP | Header button (desktop) opening the existing claim modal; XP history stays reachable via the Progress card |
| Podium | KEEP design; re-stage as a band under the command bar (desktop) / announcement pill (mobile) |

---

## 1. Current-state analysis

### 1.1 Composition (desktop)

`resources/js/pages/Dashboard.vue` → `AppLayout` (sidebar: Dashboard, My
Courses, Assignments, Activities Hub, Calendar, Games, Library Hub, Grades,
Chats) renders, top → bottom:

| # | Block | Component | What it does today |
|---|-------|-----------|--------------------|
| 1 | Hero | `dashboard/DashboardHero.vue` | Greeting + avatar + level chip, **up to 3 stacked full-width orange gradient announcement banners**, refresh button, Fox ("Echo") slot |
| 2 | Focus strip | `dashboard/TodayStrip.vue` | 3 count tiles (Due today / Overdue / Next 24h) + next-up item with live countdown + day-progress bar. Tiles only **link away** to `/activities` |
| 3 | Reward | `dashboard/DailyRewardCard.vue` | Daily XP claim banner + auto-prompt modal |
| 4 | Progress row | `LevelProgressCard` (2-col) + `StreakCard` + `SeasonProgressBand` | XP ring/history **modal**, streak calendar **modal**, season days-left |
| 5 | Main grid | `ImprovedLeaderboard` (2/3) + `StreakHeatmap` card (1/3) | Section leaderboard (static ranking), 4-week login heatmap |

Overlays: `SectionSelectionModal`, fox-welcome `ResponsiveModal`,
`OnboardingTour` (9 steps), claim-prompt modal, ban modal.
Mobile has a separate composition: `dashboard/MobileDashboard.vue`.

### 1.2 What's working

- Strong gamification data contract: `userStats`, `claimXp`, `bonusXp`,
  `xpHistory`, `statsBreakdown`, `loginDates`, `sectionLeaderboards`,
  `activeSeason`, `assignments`, `upcomingExams` — all polled every 30 s with
  `only: POLL_PROPS`, paused when the tab is hidden. This is solid; keep it.
- Sensible "next up" logic (overdue → soonest due) and context-aware greeting.
- Design system is coherent (cream `#f5f0e8` / card `#faf7f2` / accent
  `#D97757`, Inter, `surface-card`, dark warm-brown theme).

### 1.3 Problems

1. **Animation-heavy, interaction-light.** Every block is wrapped in
   `Motion` with GSAP/MotionOne staggered entrance (`y: 30 → 0`, 0.7–0.8 s,
   in-view triggers). Number tickers (`useNumberAnimation`) run on streak/level.
   The result feels slow on repeat visits — yet the only real interactions are
   *click → modal* or *click → navigate away*.
2. **Modality overload.** XP history, streak calendar, claim prompt, section
   picker, tour, fox welcome — six+ overlays. Simple info (XP history,
   streak calendar) forces a full modal round-trip.
3. **Announcements shout.** Up to three stacked full-width gradient banners
   push actual content (Today, leaderboard) below the fold.
4. **TodayStrip is a dead end.** It shows *counts* but the item list lives on
   `/activities`. Users can't check off, see details, or filter in place.
5. **The podium is buried.** `ImprovedLeaderboard` already has a strong
   3-card podium (2nd–1st–3rd, SpotlightCard glows, crown/medal badges, tied
   modals, name search) — but the whole card sits ~5 blocks down the page.
   The most celebratory, motivating element is below the fold for most
   students. The list also lacks a time-frame switch, "locate me", and paging.
6. **No quick actions.** Common intents (claim XP, continue latest assignment,
   join open exam, jump to calendar) each require nav + scroll.
7. **Vertical sprawl.** Hero + 3 banners + strip + reward + progress row +
   leaderboard = the fold arrives late; the heatmap and season info rarely get
   seen.
8. **Duplication.** Progress info is spread across hero chip, level card,
   streak card, season band — three cards that each answer one question.

---

## 2. Design direction (mockup)

See `dashboard-revamp-mockup.png` (generated alongside this plan). Key moves:

- **One compact command bar** replaces hero + announcements: avatar, greeting,
  level chip, a single-line dismissible announcement pill (overflow behind a
  "view all" popover), refresh, and 2–3 quick-action buttons on the right.
- **Today becomes a working panel**: segmented tabs `Due today / Overdue /
  Next 24h / Done` over an in-dashboard task list with checkboxes, kind badges
  (Exam/Assignment), countdown chips, and "Open in Activities" links.
- **One consolidated "Progress" card** with a segmented control
  (`XP & level | Streak | Season`) — inline expandable detail (history rows,
  streak calendar grid) instead of modals.
- **Leaderboard gains controls**: `All-time / Weekly` tabs, search box,
  "locate me" button, show-more paging. Current user row stays pinned/highlighted.
- **Right rail** keeps the heatmap and adds a "This week" mini-calendar strip
  (existing Calendar data) so the sidebar answers "what's coming".
- **Motion budget**: skeleton on first boot (already exists) → static content.
  Only color/opacity hover/focus transitions ≤ 200 ms. No entrance staggers,
  no count-up tickers, no in-view reveals. `prefers-reduced-motion` trivially
  satisfied because there's nothing to reduce.

### Target wireframe (desktop ≥ lg)

```
┌──────────┬──────────────────────────────────────────────────────────────┐
│ Sidebar  │ ☀ Good morning, Maya   [Lv 12 ▓▓▓░]  📢 pill  ⟳ │ ⚡Claim ➜ │
│ Dashboard│ ┌──────────────────────────────────────────┐ ┌─────────────┐ │
│ Courses  │ │ TODAY  [Due today 3|Overdue 1|24h 2|Done]│ │ PROGRESS    │ │
│ Assign…  │ │ ✓ Quiz 3 — Math        due 4:00p  [open] │ │ [XP|Streak|S]│ │
│ Activ…   │ │ ☐ Essay draft — Eng    due 11:59p [open] │ │  XP ring    │ │
│ Calendar │ │ ⚠ Lab report — Chem    2d overdue [open] │ │  history ▾  │ │
│ Games    │ └──────────────────────────────────────────┘ ├─────────────┤ │
│ Library  │ ┌──────────────────────────────────────────┐ │ THIS WEEK   │ │
│ Grades   │ │ LEADERBOARD  [All-time|Weekly] [⌕] [Locate me]         │ │
│ Chats    │ │ 1 Ana ▲ 12,404  …  #7 You ▲ 8,120  [more]│ ├─────────────┤ │
│          │ └──────────────────────────────────────────┘ │ ACTIVITY    │ │
│          │   quick actions: Continue essay · Join exam  │ ▦▦▦▦ heatmap│ │
└──────────┴──────────────────────────────────────────────────────────────┘
```

### 2.1 Podium placement — DECIDED ✅

**Approved by user (mockups): Option A on desktop + a matching mobile band.**

Reference mockups (approved in chat): desktop = three podium cards
(2nd silver / 1st gold elevated with crown / 3rd bronze) in a "Season 4 —
Top 3" band with a "Full rankings" link, directly under the greeting/command
bar; mobile = same band as a standalone card below the announcement pill,
above the Today card.

- Desktop: extract `resources/js/components/dashboard/LeaderboardPodium.vue`
  (top-3 only, reusing the existing podium card markup/styles) into a slim
  band at the top of the main column; "Full rankings ↓" scrolls/anchors to
  the full leaderboard card below.
- Mobile (`MobileDashboard.vue`): podium band card replaces the plain rank
  chip inside the collapsed leaderboard toggle; the toggle itself stays
  (trophy + "You are rank #7 of 32" + chevron, per mockup).
- The full leaderboard card keeps podium-at-top removed (podium lives in the
  band) and gains tabs / search / locate-me / show-more.
- All existing podium functionality preserved: tied-rank grouping +
  "• Tied (N)" modal, blurred/private profiles, SpotlightCard glow, profile
  links, `show-join-button`, section switcher, onboarding tour anchors.

Other approved layout decisions from the mockups:
- Command bar: greeting + avatar + level chip + one-line announcement pill +
  `Claim daily XP` button (desktop).
- Today card with segmented tabs (Due today / Overdue / Next 24h / Done) and
  checkbox task rows with countdown chips (desktop + mobile).
- Progress: desktop = card with segmented control (XP / Streak / Season);
  mobile = single compact strip (XP ring · streak · season bar).
- Collapsed "Leaderboard" bar on mobile + 4-week heatmap card at the end.

---

## 3. Interactivity inventory (the point of the revamp)

| Element | Today | After revamp |
|---|---|---|
| Announcements | stacked banners, dismiss | one-line pill; click → expand/popover list; dismiss persists (localStorage per user) |
| Today tiles | link to `/activities` | tabs that filter an **inline** task list; rows have checkbox (complete), countdown, deep link |
| Quick actions | none | `Claim daily XP` (opens claim), `Continue <latest assignment>` (deepest intent first), `Join exam` when `is_open_now` |
| XP & level card | click → modal | segmented panel; `View history ▾` expands inline list (first 5, `show all` → keep modal for full) |
| Streak card | click → modal | tap flips to mini calendar grid inline; restore CTA unchanged |
| Season band | static | static + tooltip/link to season details (no modal) |
| Leaderboard | podium buried below fold; list w/ search | podium re-staged (§2.1); tabs (all-time / weekly — weekly from existing `weeklyXp`), `Locate me` scrolls+highlights own row, `Show more` paging |
| Heatmap | static | hover/focus tooltips per day (already partially), click day → streak calendar jump |
| Refresh | full-strip spinner | subtle icon state only; content swaps without motion |

All interactions keyboard-reachable; tabs use proper `role="tablist"` +
arrow-key nav; expandables use `aria-expanded`/`aria-controls`.

---

## 4. De-animation checklist (explicit removals)

- [ ] `Dashboard.vue`: remove `Motion` wrappers (4 blocks), `gsap.context`
      boot block, `gsap.globalTimeline` pause/resume on ban modal,
      `.animate-section` CSS, and the `Motion` import.
- [ ] `DashboardHero.vue` / `LevelProgressCard.vue` / `StreakCard.vue`:
      remove `useNumberAnimation` count-ups → render final value immediately.
- [ ] `TodayStrip.vue` / `DailyRewardCard.vue` / `MobileDashboard.vue`:
      drop enter/leave TransitionGroups and spring effects; instant swaps
      (or single 150 ms opacity fade only where a swap would otherwise flash).
- [ ] Keep: `DashboardSkeleton` boot state; focus rings; the 30 s polling with
      `only:` (data freshness is not animation).
- [ ] Guardrail: add an ESLint/CI note (or comment convention) that dashboard
      components must not import `gsap` / `@motionone/vue` / `motion-v`.

---

## 5. Implementation plan (phased)

**Phase 0 — Prep (½ day)**
- Snapshot current dashboard with screenshots (light/dark, mobile/desktop).
- Freeze data contracts; confirm no backend changes needed for Phase 1–3.
- Add component test scaffold for `Dashboard.vue` (Vitest + @vue/test-utils).

**Phase 1 — De-animation pass (1 day)**
- Execute §4 checklist. Verify boot skeleton → content swap is instant.
- Deliverable: dashboard that renders final state immediately; zero GSAP/Motion
  on the page. Ship independently — pure subtraction, low risk.

**Phase 2 — Command bar (1–2 days)**
- Collapse hero: greeting + avatar + level chip stay; announcements become a
  single dismissible pill + "view all" popover (persist dismissed ids per user
  in localStorage alongside the existing in-memory `dismissedAnnouncementIds`).
- Add quick actions row (claim XP / continue latest / join open exam).
  Data: reuse `claimXp`, `assignments`, `upcomingExams` already in props.
- Update `OnboardingTour` step targets (`dashboard-hero` anchor survives).

**Phase 3 — Interactive Today panel (2–3 days)**
- Build `TodayPanel.vue`: tablist + inline list rendered from the existing
  `dueItems` computed (already merges assignments + published exams).
- Rows: checkbox → POST to existing assignment submit/status route →
  `manualRefresh()`; countdown chip reuses `TodayStrip` countdown logic.
- Keep `/activities` links as row-level "Open" affordance.
- Retire `TodayStrip` (or reduce it to the mobile composition).
- Mirror in `MobileDashboard` (tabs become a horizontal scroll of the same list).

**Phase 4 — Consolidated progress card (2 days)**
- Merge `LevelProgressCard` + `StreakCard` + `SeasonProgressBand` into one
  `ProgressCard.vue` with segmented control; inline expansion for XP history
  (top 5 + "show all" modal) and streak mini-calendar.
- Keep both existing modals working (deep links/tests), just no longer the
  only path.

**Phase 5 — Leaderboard interactivity + podium staging (1–2 days)**
- Execute the approved §2.1 placement: extract
  `resources/js/components/dashboard/LeaderboardPodium.vue` from the existing
  podium markup; mount it as the desktop band + mobile band card; full
  leaderboard card keeps list + controls (§2.1) and gains tabs (all-time /
  weekly — weekly from existing `weeklyXp`, client-side), `Locate me`
  button, `Show more` paging (10/page).
- Verify with 100+ synthetic rows + tied-rank groups; keep
  `sectionLeaderboards` contract and the existing tied-player modal.

**Phase 6 — Right rail + mobile parity (1–2 days)**
- Heatmap tooltips + click → streak calendar.
- "This week" mini-calendar from Calendar page data (needs a small prop or
  reuse of `api/dashboard-exams`-style endpoint — assess in Phase 6 kickoff;
  optional, can ship without it).
- Port Phases 2–4 equivalents into `MobileDashboard.vue`.

**Phase 7 — QA & hardening (1 day)**
- Keyboard-only pass, screen-reader labels, dark mode, low-end device path
  (`isLowEndDevice`), `prefers-reduced-motion`, ban modal flow, tour flow,
  section-picker flow, claim-prompt sequencing (claim → Echo → tour).
- Vitest: tabs filter logic, countdown chip, dismiss persistence, paging.
- Perf check: confirm no regression in poll payload / mount cost.

**Total: ~8–11 working days** for a full-time dev; Phases 1–2 alone are a
shippable "v1" after ~2 days.

---

## 6. Risks & mitigations

| Risk | Mitigation |
|---|---|
| Onboarding tour anchors break when components merge | Keep `data-tour` ids stable (`dashboard-hero`, `dashboard-today`, `dashboard-level-card`, `dashboard-streak-card`, `dashboard-season`, `dashboard-leaderboard`, `dashboard-activity`) |
| Claim → Echo → tour sequencing is delicate | Don't touch gating logic in Phase 1–2; re-run that flow after each phase |
| Removing animations exposes layout shift on poll updates | Reserve row heights; swap content in place; keep skeletons for boot only |
| Weekly leaderboard tab implies server aggregation | `weeklyXp` already ships per user — compute client-side first; server endpoint only if sections are huge |
| Mobile drift | Treat `MobileDashboard.vue` as a first-class checklist item (Phase 6), not an afterthought |

## 7. Out of scope

- New backend features (AI, new XP sources), redesign of the sidebar/chat,
  changes to exam/assignment pages, and any new mascot work.

---

## 8. Implementation record (shipped)

### New components
- `resources/js/components/dashboard/CommandBar.vue` — greeting + avatar +
  level chip w/ progress, one-line announcement pill (expandable list,
  dismissible, persisted per user in localStorage), Claim XP button (reuses
  `ClaimXpButton` → same modal, same auto-prompt sequencing), refresh.
- `resources/js/components/dashboard/TodayPanel.vue` — tabbed task list
  (Due today / Overdue / Next 24h / Done) with per-bucket counts, countdown
  chips (1 s tick only while visible & active), kind badges, whole-row links
  to `/activities`; `compact` variant for mobile; exports the `TodayTask` /
  `NextUpItem` types.
- `resources/js/components/dashboard/ProgressCard.vue` — segmented
  XP / Streak / Season card. Panes are `v-show`-mounted wrappers around the
  existing `LevelProgressCard`, `StreakCard` and `SeasonProgressBand`, so the
  XP-history modal, streak calendar modal and streak-restore all keep working.

### Changed
- `ImprovedLeaderboard.vue` — new `podiumOnly` (band) and `hidePodium`
  (list-only) modes; All-time / Weekly segmented control (weekly re-ranks
  client-side from `weeklyXp`); mode-aware list + "Show All" paging.
  **Podium design untouched** (2nd–1st–3rd, SpotlightCard glow, crown/medal
  badges, tied modals, blurred profiles). Removed entrance staggers +
  XP count-up tickers.
- `Dashboard.vue` — new desktop composition: CommandBar → podium band
  ("Full rankings" scroll link) → TodayPanel + ProgressCard → full rankings
  (`hidePodium`) + Activity heatmap. Removed ALL Motion/GSAP usage, Motion
  import, `.animate-section` style; tour re-anchored
  (level/streak/season → `dashboard-progress`); announcement dismissals now
  persist in localStorage; dead `nextItem` computation removed.
- `MobileDashboard.vue` — greeting card gains avatar + level chip; podium
  band card under the announcement; tabbed `TodayPanel` replaces metric
  tiles + next-item; static "+N XP" reward tile replaced by the real
  `ClaimXpButton` (claim now possible on mobile, `:show-prompt="false"`
  preserves existing no-auto-modal behavior); expanded leaderboard uses
  `hidePodium`; fox retained (smaller).

### De-animation (Phase 1) — verified
- No `gsap` / `@motionone/vue` / `Motion` imports remain on the dashboard
  page or its live components (ClaimXpButton's claim-celebration confetti is
  user-triggered reward feedback and was kept, honoring the "keep feedback"
  decision; it already respects reduced-motion/low-end).
- Kept: `DashboardSkeleton` boot state, hover/focus transitions, polling.

### Verification
- `vite build`: ✓ 4130 modules (main app entry; the Filament admin CSS entry
  requires PHP `vendor/`, unavailable in this sandbox — unrelated).
- `vue-tsc --noEmit`: ✓ clean.
- `vitest run`: ✓ 37 files / 228 tests passing (3 structural source-assertion
  tests updated to the new contracts).
- ESLint: ✓ clean on all changed files.
- Sandbox note: `resources/js/routes_temp/` stubs were created only to let
  the frontend build/type-check run without PHP; the directory is gitignored
  and the real wayfinder prebuild replaces it.

### Modal & interaction entry points (desktop / mobile)

| What opens | Desktop click path | Mobile tap path |
|---|---|---|
| Streak calendar modal | Progress card → **Streak** tab → "Streak calendar" pill (or tap the streak card itself); also **Activity card → "Streak calendar"** pill next to the heatmap | Flame **Streak tile** in the reward/streak row (one tap) |
| XP history / breakdown modal | Progress card → **XP & level** tab → "XP history" pill (or tap the level card itself) | "XP history, claims, and details" section → tap the level card |
| Claim XP modal | **Claim button in the command bar** (auto-prompt unchanged) | Reward tile's claim button (no auto-prompt, by design) |
| A player's XP history | History icon on any podium card (band) or leaderboard row | Same, in the band and the expanded leaderboard |
| Tied-rank modal | "• Tied (N)" badge on podium/rank rows; "Tied with N" in your-rank row | Same |
| Streak restore | Inside the streak calendar modal (restore panel) | Same |

Implementation notes: `LevelProgressCard` now exposes `openHistory()` and
`StreakCard` exposes `openCalendar()` (defineExpose) so ProgressCard's
pane-aware quick-action pill opens the *same* modal instance the card opens
on click — no duplicated modal state. The dashboard's Activity card hosts its
own `StreakCalendarModal` instance wired to the heatmap.

### Podium ordering semantics (finalized)

- **DOM order = rank order (1st, 2nd, 3rd)** in all modes: screen readers and
  the stacked mobile band read top-to-bottom by rank, matching the approved
  mobile mockup (`docs/mockups/expected-mobile.png`).
- **Wide screens (≥640px)** re-stage the cards visually to the classic
  2nd–1st–3rd podium purely via `order-*` / `sm:order-*` utilities — the
  approved desktop look (`docs/mockups/expected-desktop.png`) is unchanged.
- Locked by regression tests in `tests/js/leaderboard-ui.test.ts`
  ("uses rank order in the DOM and stages 2nd-1st-3rd only via CSS").

### Follow-ups (recommended)
1. Retire `DashboardHero.vue`, `TodayStrip.vue`, `DailyRewardCard.vue` (now
   unreferenced by any page; still covered by their own tests) and port those
   tests to `CommandBar` / `TodayPanel` / the CommandBar claim integration.
2. Prune now-unused mobile CSS (`.mobile-dashboard-metric*`,
   `.mobile-dashboard-next-item*`, `.mobile-dashboard-reward__*`).
3. Optional Phase 6 extras: heatmap day tooltips, "This week" mini-calendar.
