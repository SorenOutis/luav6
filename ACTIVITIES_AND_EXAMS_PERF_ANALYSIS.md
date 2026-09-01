# Activities Hub & Exams — Performance and Scroll Analysis

**Date:** 2026-09-01
**Scope:** `resources/js/pages/Activities/Index.vue` (Activities Hub, also serves the legacy `/exams` route), `resources/js/pages/Exam.vue` (older exams list), `resources/js/pages/Exams/Show.vue` (the actual exam-taking page).
**Method:** Same as the dashboard analysis — static source read-through with file/line evidence, no live browser (no PHP runtime / no network access for a Playwright browser download in this sandbox).

This is a follow-up to `DASHBOARD_PERF_AND_SCROLL_ANALYSIS.md`. Short version: **the same root-cause pattern from the dashboard (full duplicate mobile+desktop DOM trees, toggled with CSS instead of `v-if`) exists here too, and on the exam-taking page it's worse** because it duplicates every single question card, not just a handful of dashboard widgets — and the code has already had one real bug caused by it (see §3.1).

---

## 0. Routing note (not a perf bug, but worth knowing)

`routes/web.php:150-154`:
```php
Route::get('activities', [ActivityHubController::class, 'index'])->name('activities.index');
Route::get('exams', [ActivityHubController::class, 'index'])->name('exams.index');   // legacy alias
```

Both `/activities` and `/exams` now render **`Activities/Index.vue`** via `ActivityHubController`. The older **`Exam.vue`** page (rendered by `ExamController::index()`, `app/Http/Controllers/ExamController.php:49-56`) has **no route pointing to it anymore** — I searched all of `routes/web.php` and found nothing that calls `ExamController@index`. It appears to be dead code left over from before the Activities Hub replaced it. It still gets built into your JS bundle and still has the same performance issue described below (§2), but since nothing links to it, it's likely not what you're experiencing day-to-day — flagging it mainly so you know it's there and can decide whether to delete it.

The exam-taking page (`Exams/Show.vue`, at `/exams/{exam}`) is separate and definitely live — that's what `examsShow(exam.id).url` in `Activities/Index.vue:502` and `MobileNav.vue` link to.

---

## 1. Activities Hub (`Activities/Index.vue`) — mostly fine, one real gap

Good news first: **this page does not have the dashboard's duplicate-mount problem.** It's a single, unified template that uses responsive Tailwind classes (`hidden sm:grid`, `md:hidden`, etc.) on small, cheap elements (a stats bar, a header row) — not two whole parallel component trees. There's no separate "MobileActivities.vue" being mounted alongside a desktop one. Confirmed via `resources/css/app.css:4158` (`.activities-mobile-stats` — a 4-cell summary bar, not a full page duplicate) and the single `<template v-if="filteredExamsBySeason.length > 0">` render path (`Activities/Index.vue:769`) shared by all breakpoints.

### 1.1 What's actually there
- 10-second poll (`usePoll(10000, { only: [...] })`, `Activities/Index.vue:115`) for `examsBySeason, hubStats, sectionTabs, activityScores` — reasonable for a list page, correctly paused/resumed on tab visibility.
- Each exam card and season group is wrapped in its own `Motion` component with a staggered per-index delay (`Activities/Index.vue:771-826`) — fine for typical list sizes (tens of cards), would only become notable with hundreds of exams on screen at once, which is an edge case, not your everyday load.
- The exam-review modal (`ResponsiveModal`, line 1089) and "My Scores" drawer (`Sheet`, line 1662) both correctly carry `data-lenis-prevent` on their scrollable inner content (`Activities/Index.vue:1156`, `:1678`) — these scroll properly.

### 1.2 The one scroll gap found
Two small scroll boxes inside the **exam review modal**, showing an essay's "Your response" text and AI feedback text, are missing `data-lenis-prevent`:

```
Activities/Index.vue:1497   class="custom-scrollbar mt-1 ... sm:max-h-52 sm:overflow-y-auto"   (Your response)
Activities/Index.vue:1560   class="custom-scrollbar mt-2 ... sm:max-h-52 sm:overflow-y-auto"   (AI feedback)
```
These only apply `max-h-52` / `overflow-y-auto` at `sm:` and up (desktop/tablet), and they nest *inside* the outer modal scroll container that IS correctly marked (`Activities/Index.vue:1156`). In practice this means: on a long essay answer, trying to scroll just that inner text box with a mouse wheel can instead scroll the whole modal (Lenis intercepts the gesture before it reaches the un-flagged inner box), which reads as "scrolling in this box feels wrong" even though the modal itself scrolls fine. Low severity (small, secondary scroll area) but a one-line fix (add `data-lenis-prevent` to both).

---

## 2. Legacy `Exam.vue` — same double-mount bug as the dashboard (likely dead code, see §0)

`Exam.vue` renders **two full copies** of the exam list at all times:
```html
<section class="mobile-exams-queue md:hidden">      <!-- Exam.vue:699 -->
    ...full mobile exam list, own <button> rows...
</section>
...
<template v-if="filteredExamsBySeason.length > 0">   <!-- Exam.vue:876 -->
    <Motion class="exams-desktop-groups" ...>          <!-- Exam.vue:887 -->
        ...full desktop exam grid, Motion-wrapped cards...
    </Motion>
</template>
```
Both are permanently mounted; visibility is controlled purely by CSS (`resources/css/app.css:3963-3967`: `.exams-desktop-groups, .mobile-exams-queue... { display: none !important; }` inside a `@media (max-width: 767px)` block, and the inverse for desktop). Same pattern, same cost, as the dashboard's `hidden md:block` split. Since no route currently serves this page, it's not urgent — but if you ever re-link it, it should get the same fix as recommended for the dashboard.

---

## 3. Exam-taking page (`Exams/Show.vue`) — the important one

This is a 4,700-line page and by far the heaviest of the three. It has the same architecture problem as the dashboard, but it's **more expensive here** because it duplicates entire question sets, not just summary widgets — and it's the page where students spend the most focused, uninterrupted time, so any per-frame cost is more noticeable (typing lag, janky flag/answer toggling, sluggish scrolling while working through questions).

### 3.1 Both the mobile question carousel AND the desktop question grid are always mounted

```html
<!-- Exams/Show.vue:2752 -->
<div class="block md:hidden">
    <!-- one question at a time, swipeable -->
    ...renders selectedPart.questions[mobileQuestionIndex]...
</div>

<!-- Exams/Show.vue:3150 -->
<div class="hidden gap-6 md:grid md:grid-cols-2">
    <div v-for="(question, qIndex) in selectedPart!.questions" ...>
        <!-- ALL questions in the part, one full card each -->
    </div>
</div>
```
Again, `block md:hidden` / `hidden md:grid` are CSS-only toggles — both blocks mount together. That means **on a phone, every question in the active exam part is still fully rendered in the DOM** (radio inputs, `v-model` bindings into the shared `answers` reactive object, matching `<select>`s, essay `<textarea>`s) inside the hidden desktop grid, in addition to the one visible question in the mobile carousel. For an exam part with, say, 20–30 questions, that's 20-30 fully bound, always-reactive question cards sitting invisibly in the DOM on every phone, all watched by the page's `deep` watcher on `answers` (`Exams/Show.vue:838-856`) which fires on every keystroke/selection.

**This is a confirmed, not just suspected, source of bugs** — the code's own comments prove it caused a real defect that had to be patched around:

> `Exams/Show.vue:1393-1396`: *"Only VISIBLE question cards may be targeted. The mobile carousel card is always in the DOM (display:none on desktop) and used to be picked on tall viewports — its id parsed to NaN and the answer was written to `answers[NaN]`, which the submit payload ignores, so the question silently stayed unanswered and the student answered again."*

That's a real, documented data-loss-adjacent bug that came directly from having two live copies of the same interactive form fighting for keyboard shortcuts, and it was fixed by *filtering by DOM visibility* (`card.getClientRects().length > 0`, lines 1397, 1442) rather than by not rendering the hidden copy in the first place. The visibility-filter patch works for the two keyboard shortcuts it covers (`1-9` for MCQ, `F` for flag), but the underlying double-mount is still there for everything else: the `deep` watcher on `answers`, the draft/autosave pipeline, GSAP's `.question-card` selector-based animations (which target *both* the visible and hidden copies by class name, `Exams/Show.vue:1271`, `1397`, `1442`), and ordinary Vue reactivity overhead.

### 3.2 Concrete performance cost of §3.1

- **Typing/selecting lag while answering questions**: the `deep` watcher on the `answers` reactive object (`Exams/Show.vue:838-856`) re-runs `collectChangedAnswers()` on every change, which iterates `Object.entries(answers)` — with two mounted copies of the form both bound to the same `answers` object, any interaction schedules the same downstream work, and Vue must reconcile two DOM subtrees (visible + hidden) on every reactive update rather than one.
- **GSAP class-selector animations hit both copies**: `gsap.fromTo('.question-card', ...)` (line 1271, entrance animation on starting a part) and the keyboard-shortcut DOM queries (`document.querySelectorAll<HTMLElement>('.question-card')`, lines 1397 & 1442) select **every** element with that class — both the visible mobile card and every hidden desktop card — then filter by visibility afterward. That's wasted `querySelectorAll` + `getClientRects()` work on every keypress that matches a shortcut, scaling with question count.
- **DOM size**: essay questions render a full `<textarea rows="10">` (line 3453) and matching questions render a `<select>` per item (line 3406) — these are not free-to-mount elements, and a part with several essay/matching questions means several such heavy inputs exist twice in the DOM tree on a phone, unused half the time.

### 3.3 Other timers/intervals (lower impact, for completeness)
- `timerInterval` — 1s countdown (`Exams/Show.vue:404-411`), calls `calculatePace()` each tick. Cheap by itself; not paused on tab-hide (unlike the dashboard's `TodayStrip`), but since the exam timer is meant to keep running even if you switch tabs (it's a real exam clock, not a decorative one), that's correct behavior, not a bug.
- `monitorHeartbeatInterval` — 5s heartbeat POST while `isExamInProgress` (`Exams/Show.vue:2015-2018`) — a small network call, not a rendering cost.
- One `repeat: -1` (infinite) GSAP tween: pulsing the submit button's glow once all questions are answered (`Exams/Show.vue:574-586`, `.submit-celebration-btn`). This is intentionally infinite (encourage submission) and is correctly killed when submission starts (`watch(isSubmitting, ...)`, line 592) — not a leak, just worth knowing it's a continuous per-frame box-shadow animation while it's showing, which is a relatively expensive CSS property to animate continuously (forces paint, not just composite). Low priority, but if you ever want to cut a bit of GPU/CPU load during the "ready to submit" state, switching that pulse to a `transform`/`opacity`-only animation (drop the animated `boxShadow`) would be cheaper.

### 3.4 Modal/scroll findings on this page

| Scroll area | `data-lenis-prevent`? | Status |
|---|---|---|
| Desktop sidebar progress chart (`Exams/Show.vue:3505`) | ✅ Yes (and correctly commented as intentional) | OK |
| Essay "Your response" / AI feedback boxes (`Exams/Show.vue:1696`, `1764` — legacy `Exam.vue` copy) | ❌ No | Same minor gap as §1.2 |
| **Mobile "Progress" right-side drawer** question-jump list (`Exams/Show.vue:3830`, `overflow-y-auto`, opened via the mobile progress button) | ❌ **No** | **Broken** — same symptom pattern: mouse wheel/trackpac scroll over this drawer's contents can be hijacked by Lenis instead of scrolling the drawer's own question-number grid. This is a real, currently-reachable modal (unlike the two dead-code pages above), and is the one I'd prioritize fixing on this page. |
| Start-part / fullscreen-lockout / success modals (`showStartModal`, `showFullscreenLockout`, `showSuccessModal`) | N/A — these don't have their own internal scroll regions | OK, nothing to fix |

---

## 4. Suggested fix order

| # | Fix | File(s) | Effort | Impact |
|---|---|---|---|---|
| 1 | Gate the mobile/desktop question rendering in `Exams/Show.vue` with a real `v-if="isMobile"` / `v-if="!isMobile"` (there's already a `useMobile()`-style breakpoint helper used elsewhere in the codebase) instead of `block md:hidden` / `hidden md:grid` | `resources/js/pages/Exams/Show.vue:2752`, `:3150` | Moderate — needs care since GSAP's `.question-card` selector and the keyboard-shortcut DOM queries assume both class-named elements exist; should be safe once only one is ever mounted, but should be tested against the MCQ/flag keyboard shortcuts specifically since that's exactly the code path that already broke once | **High** — removes the biggest duplicate-DOM cost in the app, on the page where users spend the most focused time |
| 2 | Add `data-lenis-prevent` to the mobile Progress drawer's scroll container | `Exams/Show.vue:3830` | ~1 min | Medium — fixes an actually-broken, currently-reachable scroll area |
| 3 | Add `data-lenis-prevent` to the two essay-answer scroll boxes in the exam review modal | `Activities/Index.vue:1497`, `:1560` | ~1 min | Low-medium |
| 4 | (Optional cleanup, not a perf fix) Decide whether to delete the now-unrouted `Exam.vue` page, or leave it; if kept for some other reason, apply fix #1's pattern there too | `resources/js/pages/Exam.vue` | Your call | Removes dead weight from the bundle either way |
| 5 | (Optional polish) Swap the infinite submit-button glow tween from animating `boxShadow` to `opacity`/`transform` only | `Exams/Show.vue:574-586` | ~5 min | Low |

I have not made any of these edits yet — this is analysis only, same as the dashboard pass. Happy to implement any/all on your go-ahead. Given #1 touches exam-taking logic (the same code path that already had one real bug from this exact pattern), I'd want to test carefully around the keyboard shortcuts and the draft-save watcher before considering it done, rather than treat it as a pure one-liner like the dashboard's equivalent fix.

---

## 5. What I could not verify directly

Same caveat as before: no PHP runtime, no database, no outbound network access for a Playwright browser in this sandbox, so nothing here was captured from an actual DevTools trace or a live repro of scroll behavior — all findings are sourced to exact file/line references in the current code. If you can share a Performance recording while taking a multi-question exam on a phone, or a screen recording of the Progress drawer's scroll issue, I can confirm the magnitude and prioritize further.
