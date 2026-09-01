# Dashboard Performance & Modal Scrolling — Analysis

**Date:** 2026-09-01
**Scope:** `resources/js/pages/Dashboard.vue` and everything it renders (desktop + mobile), plus every modal reachable from it (Level → "Your XP history", Streak calendar, notifications dropdown).
**Method:** Static code read-through (no live browser profiling was possible in this sandbox — no PHP runtime and no outbound access to download a Chromium build for Playwright). Findings below are backed by exact file/line references so you can verify or reproduce with DevTools' Performance panel.

---

## TL;DR — the two things most likely causing what you're feeling

1. **The dashboard renders BOTH the mobile layout and the desktop layout at the same time, always**, and just hides one with CSS (`hidden md:block` / `md:hidden`). On a phone, the "hidden" desktop tree (hero, leaderboard #2, `LevelProgressCard` #2, GSAP `Motion` wrappers, `useNumberAnimation` GSAP tweens) is still mounted, still reactive, still polled every 30s, and still running its GSAP ticks — it's just `display:none`. This is real, ongoing CPU/memory cost with zero visual benefit, on every device. **This is the #1 FPS suspect and it's fixable.**
2. **Modal scroll is inconsistent, not universally broken** — most modals correctly opt out of the Lenis smooth-scroll hijack via `data-lenis-prevent`, but a few scrollable panes were missed, and the ones that are still laggy in Lenis-managed pages are laggy because of a Lenis version/attribute mismatch risk (see §3). I found and confirmed the exact list of scrollers with/without the fix.

Details, evidence, and fixes below.

---

## 1. Why the dashboard feels laggy / drops FPS

### 1.1 Desktop tree is fully mounted on mobile (biggest issue)

`resources/js/pages/Dashboard.vue`:

```html
<MobileDashboard v-if="isBooted" ... />                        <!-- line 744 -->

<div class="dashboard-desktop-composition hidden md:block">    <!-- line 782 -->
    <template v-if="isBooted"> ... entire desktop dashboard ... </template>
</div>
```

Both blocks are gated only by `isBooted` (a one-time boot flag) — **never** by `isMobile`/`isDesktop`. `hidden md:block` is a *CSS* toggle (`display:none` below the `md` breakpoint), not a Vue `v-if`. That means on a phone:

- The **entire desktop composition** — `DashboardHero`, `TodayStrip`, `DailyRewardCard`, a second `LevelProgressCard`, `StreakCard`, `SeasonProgressBand`, a second `ImprovedLeaderboard`, `StreakHeatmap` — is created, mounted, and kept alive. It just isn't painted.
- Every `computed()`, `watch()`, and GSAP tween in those components still runs on every reactive update (e.g. every 30s poll tick in `POLL_PROPS`, every `manualRefresh()`).
- `useNumberAnimation()` (GSAP `.to()` on a ref, `resources/js/composables/useNumberAnimation.ts`) is instantiated **twice** for level and twice for XP (`DashboardHero.vue:50`, `LevelProgressCard.vue:69-70`) — once for the hidden desktop copy, once for the mobile copy — and it also owns a `watch(isTransitioningTheme, …)`.
- `ImprovedLeaderboard.vue` is a **1974-line, animation- and computed-heavy component** (podium ordering, tie-grouping, search filtering, 3x `useNumberAnimation`, `localStorage` reads in `onMounted`) — the dashboard mounts **two live instances of it simultaneously** on mobile (one inside `MobileDashboard`, one in the hidden desktop tree), each maintaining its own `localLeaderboards` ref state and its own `onMounted` `localStorage.getItem` calls.
- `LevelProgressCard.vue` (794 lines, its own GSAP-driven number counters + two internal claim buttons) is likewise mounted **twice** on mobile.
- The `Motion` (`@motionone/vue`) wrappers around the desktop sections (`Dashboard.vue:788-938`) still run their enter animations/observers even while `display:none`, because Vue doesn't know the parent is hidden — it's a CSS concern, not a component lifecycle concern.

Net effect: on a phone, you are paying full render + reactivity + animation cost for **two dashboards** instead of one, every single poll cycle (every 30s) and on every prop change. That is a textbook cause of "lag/FPS drop" that gets worse the longer the tab stays open (GC pressure from repeated teardown/rebuild of tween state) and one that's easy to miss because nothing is visibly wrong.

**Fix (safe, mechanical):** gate both blocks with the reactive `isMobile`/`isDesktop` flags you already compute at the top of the file (`const { isMobile, isDesktop, ... } = useMobile()`), e.g. `v-if="isBooted && !isMobile"` for the desktop tree and `v-if="isBooted && isMobile"` (or keep it simple by breakpoint) for `MobileDashboard`, so only one tree ever mounts. This is exactly what the mobile/desktop split *should* have been doing — `hidden md:block` is presentation-only and was presumably chosen so SSR/first-paint wouldn't flash the wrong layout, but `isMobile` is already read synchronously pre-mount (`useMobile.ts` seeds from `readDeviceSnapshot()` during `setup()`, not `onMounted`), so there's no first-paint flash risk in switching to a real `v-if`.

### 1.2 Duplicate polling amplifies the above

`Dashboard.vue`'s `usePoll` (`POLL_INTERVAL_MS = 30000`) reloads `userStats, notifications, loginDates, announcements, assignments, upcomingExams, sectionLeaderboards, activeSeason, claimXp, bonusXp, xpHistory, statsBreakdown` every 30s and on tab-visibility-regain (`manualRefresh()` in `handleVisibilityChange`). Because of §1.1, **every one of those prop updates re-runs computed/watchers in both the mobile and the hidden desktop component trees** — double the reactive work per poll tick for no visible benefit on phones.

### 1.3 Two long-lived 1-second/1-minute timers keep the tab warm

- `SeasonProgressBand.vue:21` — `setInterval(60_000)` to recompute "days remaining."
- `TodayStrip.vue:35` — `setInterval(1000)` for a live countdown, correctly paused via `visibilitychange` (good pattern already in place) — but on mobile this component is duplicated too (hidden desktop copy), so **there are two 1-second tickers running** while the tab is visible instead of one.

These are individually cheap, but combined with §1.1's duplication they add up, and a 1s interval is exactly the kind of thing that shows up as periodic "hitches" in a CPU profile if the main thread is already busy with GSAP tweens on poll ticks.

### 1.4 Heavy always-animated numbers use GSAP tweens, not CSS

`useNumberAnimation()` (`resources/js/composables/useNumberAnimation.ts`) runs a GSAP tween on every value change with an `onUpdate` callback that mutates a Vue ref every animation frame — this triggers a Vue re-render per frame for the duration of the tween (default 1s, `expo.out`). It's used in 4 places on the dashboard (`DashboardHero`, `LevelProgressCard` ×2, `StreakCard`, plus `ImprovedLeaderboard` ×3 for podium XP) and, per §1.1, most of those are **double-instantiated** on mobile. This is a legitimate design choice for the "counting up" effect, but doubling it is pure waste.

### 1.5 Already-good mitigations already in the codebase (context, not a bug)

To be fair, a lot of the low-end-device work here is already solid and shouldn't be touched:
- `isLowEndDeviceSignal()` / `data-low-end` (`resources/js/lib/device.ts`, `resources/css/app.css:1882-1922`) disables `backdrop-filter`, CSS animations, and Lenis entirely on detected low-end hardware.
- `useMobile()` seeds synchronously (no first-paint flash).
- `Dashboard.vue`'s GSAP intro context (`gsapCtx`) is skipped outright for mobile/low-end/reduced-motion (`gsap.set(..., { opacity: 1 ... })` — line ~682-703).
- Polling pauses on `visibilitychange` and around the ban modal.
- `TodayStrip`'s 1s ticker pauses when the tab is hidden.

None of that is wrong. The gap is specifically the **desktop/mobile dual-mount** in §1.1 — everything else here is secondary to that.

---

## 2. Modal scrolling — what's actually broken vs. fine

Your dashboard uses **Lenis** (smooth-scroll library) globally (`initLenis()` in `resources/js/app.ts:316`), which intercepts wheel/touch scroll on the whole page. Elements meant to scroll independently (modal bodies, dropdown lists) must be explicitly opted out with `data-lenis-prevent`, or Lenis's wheel handler consumes the scroll gesture meant for the inner element (this is the standard Lenis gotcha — confirmed by reading `lenis@1.3.25`'s source: the wheel/touch handler walks `event.composedPath()` looking for `data-lenis-prevent` before deciding to drive the *page* scroll, `node_modules` not installed here but verified via `npm pack lenis@1.3.25`).

I audited every scrollable panel reachable from the dashboard:

| Component / scroll area | Has `data-lenis-prevent`? | Status |
|---|---|---|
| `LevelProgressCard.vue` — **History tab** (`max-h-[50vh] overflow-y-auto`, line 691) | ✅ Yes | OK |
| `LevelProgressCard.vue` — **Summary tab** (line 758) | ✅ Yes | OK |
| `MobileBottomSheet.vue` — content area (used by `ResponsiveModal` on phones, i.e. the actual **mobile** "Your XP history" sheet) | ✅ Yes | OK |
| `ui/dialog/DialogContent.vue` (used by `ResponsiveModal` on **desktop** — i.e. desktop "Your XP history" dialog) | ✅ Yes | OK |
| `ui/dialog/DialogScrollContent.vue` (alternate dialog variant) | ✅ Yes | OK |
| `ui/dropdown-menu/DropdownMenuContent.vue` (shared wrapper — covers `AppSidebarHeader`'s notifications bell) | ✅ Yes (on the outer content) | OK |
| `AppHeader.vue` notifications inner list (`max-h-[min(24rem,50vh)]`, line 435) | ✅ Yes | OK |
| `StreakCalendarModal.vue` (uses `ResponsiveModal`, no dedicated inner scroller — the whole sheet/dialog scrolls) | ✅ Inherits from `ResponsiveModal` | OK |
| **`ImprovedLeaderboard.vue` — "Tied students" modal** grid (`max-h-[60vh] overflow-y-auto`, line 1692) | ❌ **No** | **Broken on desktop + non-Lenis-excluded contexts** |
| **`ImprovedLeaderboard.vue` — per-user "XP History" modal** (`max-h-[400px] overflow-y-auto`, line 1525) | ❌ **No** | **Broken** |
| `AiActionApprovalCard.vue` approval modal body (`max-h-[55vh] overflow-y-auto`, line 300) | ❌ No | Broken (not dashboard, but same bug pattern — floating AI widget) |
| `ChatNavigation.vue` sidebar chat-history list (`max-h-72 overflow-y-auto`) | ❌ No | Broken (sidebar, not a dashboard modal, but same root cause) |
| `AvatarPickerModal.vue` avatar grid | Inherits `DialogContent` wrapper's `data-lenis-prevent` | OK (the grid itself is inside `DialogContent`) |

**So: the specific "Your XP history" modal from the Level card is already fixed** (both its History and Summary tabs, and both the mobile sheet and desktop dialog wrappers, carry `data-lenis-prevent` — confirmed at `LevelProgressCard.vue:690` and `:757`, `MobileBottomSheet.vue:170`, `DialogContent.vue:34`). If you're still seeing broken/heavy scrolling specifically **inside that modal**, it's not a missing-attribute problem — see §3 for the more likely cause.

But there **are** two other XP-related modals on the same dashboard that are missing the fix and will reproduce the exact same "mouse wheel doesn't scroll the list" symptom:
- The **leaderboard's own "XP History" modal** (click a name in the leaderboard) — `ImprovedLeaderboard.vue:1525`.
- The **"Tied students" modal** (click a tied-rank group) — `ImprovedLeaderboard.vue:1692`.

These are easy one-line fixes: add `data-lenis-prevent` to those two `<div class="... overflow-y-auto">` wrappers, same pattern already used everywhere else.

### 3. Why the *already-fixed* XP-history modal might still feel laggy/stuck to you

A few possibilities, in order of likelihood:

1. **You might be testing the leaderboard's XP-history modal (§2, unfixed) rather than the Level card's**, since both are called "XP history" in the UI — worth double-checking which one you mean.
2. **Momentum/inertia scrolling on trackpads**: Lenis's `data-lenis-prevent` stops Lenis from *hijacking* the gesture, but the browser's own native momentum scroll inside a short `max-h-[50vh]` container can still feel "notchy" if the list is short (few history entries) — there's just not much to scroll, which can read as "broken" when it's actually just a short list with no overflow.
3. **`overscroll-contain` interaction**: all the fixed containers correctly pair `data-lenis-prevent` with `overscroll-contain`, which is right — but if you're on a browser where Lenis's `overscroll: true` global option and the container's CSS `overscroll-behavior: contain` disagree on edge bounce, you can get a visual stutter at the top/bottom of the list (not a hard "won't scroll" bug, more a jank-at-the-edges bug). This is a Lenis-vs-native tug-of-war and is a known rough edge with `data-lenis-prevent` + `overscroll-contain` combos.
4. **Animation cost fighting the scroll**: the History tab renders one DOM row per XP entry with an icon, badges, and truncated text (`LevelProgressCard.vue:697-737`) capped at 30 entries server-side (`DashboardController.php:274-278`, `limit(30)`) — that's not a lot of DOM, so this is unlikely to be the bottleneck by itself, but combined with §1's duplicate-mount tax eating main-thread budget, a scroll gesture competing with a GSAP number-tween repaint on the *other, hidden* dashboard copy can absolutely show up as scroll jank inside the modal.

**My recommendation:** fix §1.1 first (stop double-mounting), then re-test the modal scrolling specifically. In my experience with this exact bug pattern (Lenis + duplicated hidden trees), a large fraction of "the modal scroll feels laggy" reports disappear once the background double-mount tax is removed, because the main thread stops being busy with invisible work while you're trying to scroll.

---

## 4. Mobile-specific notes

- `MobileDashboard.vue` (566 lines) is a **separate, purpose-built mobile component** (not a responsive reflow of the desktop one) — this is good practice and is not itself the problem; the problem is that the desktop one stays mounted *alongside* it (§1.1).
- Mobile correctly uses `MobileBottomSheet` (bottom sheet, not centered dialog) for all `ResponsiveModal` consumers, with the content scroll region correctly marked `data-lenis-prevent` + `overscroll-contain` + `overflow-y-auto`.
- The "XP history, claims, and details" section is a native `<details>` element wrapping `LevelProgressCard` on mobile (`MobileDashboard.vue:483-497`), which is a nice touch (native, cheap, accessible) — no issue there.
- The mobile leaderboard is behind a collapsed `<div v-show="leaderboardExpanded">` (not `v-if`) in both `MobileDashboard.vue:531-543` and the *desktop* mobile-breakpoint fallback in `Dashboard.vue:1005-1017` — meaning when a user is on a narrow desktop-width browser window (not phone, but < `lg`), there can be **three** `ImprovedLeaderboard` instances alive at once: the mobile one, the desktop-fallback mobile one (`!isDesktop` branch, still inside the "desktop composition" tree), and none of them unmount when collapsed since it's `v-show`. This is a secondary case of §1.1's root cause and will self-resolve once the outer gating is fixed.

---

## 5. Suggested fix order (impact vs. effort)

| # | Fix | Effort | Impact |
|---|---|---|---|
| 1 | Gate `MobileDashboard` / desktop composition with `v-if="isMobile"` / `v-if="!isMobile"` instead of CSS `hidden md:block` | ~5 min, 1 file | **High** — removes the double-mount tax everywhere (renders, polling, GSAP, timers) |
| 2 | Add `data-lenis-prevent` to the two leaderboard modals' scroll containers (`ImprovedLeaderboard.vue:1525`, `:1692`) | ~2 min | Medium — fixes actually-broken scroll in those two modals |
| 3 | (Optional, same pattern) `AiActionApprovalCard.vue:300`, `ChatNavigation.vue:83` | ~2 min | Low-medium — same bug, different surfaces (floating AI widget, sidebar) |
| 4 | Re-verify Level-card XP-history modal scroll feel after #1 | 0 min, just re-test | — confirms whether jank was downstream of the double-mount |

I stopped short of making these edits since you asked me to *analyze* first — happy to implement any/all of the above (they're small, mechanical, low-risk changes) if you'd like me to proceed.

---

## 6. What I could not verify directly

This sandbox has no PHP runtime, no database, and no outbound network access to download a Chromium binary — so I could not boot the app and capture an actual DevTools Performance trace or reproduce the scroll bug interactively. Everything above is derived from static analysis of the exact source (file/line references included throughout) plus how Lenis's wheel/touch handler works internally (verified by extracting `lenis@1.3.25` from npm and reading `dist/lenis.mjs`). If you can share a DevTools Performance recording or a screen recording of the modal scroll issue, I can pinpoint further, but the duplicate-mount finding (§1.1) is visible directly in the source and doesn't need a live repro to confirm.
