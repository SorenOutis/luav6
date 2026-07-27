# Luav6 — Codebase Review

Laravel 12 + Inertia + Vue 3 LMS. ~22k lines PHP, ~42.5k lines Vue/TS, 378 PHP files, 229 Vue files.
Reviewed: architecture, request flow, frontend design system, security, performance, tooling.

The foundation is good — Filament for admin, Wayfinder for typed routes, Pest, CI, Octane, a real
design-token system in `app.css`. The problems are almost all **structural drift**: logic that
accumulated in the wrong layer, and a handful of correctness/security bugs that fall out of that drift.

---

## 0. Critical — fix these first

### 0.1 Exam answer keys are shipped to the browser 🔴

`ExamController::show()` sends the whole `ExamPart` model, and `questions` is an `array` cast holding
the answer key. The frontend types admit it:

```ts
// resources/js/pages/Exams/Show.vue:51
options: { text: string; is_correct: boolean }[] | null;
correct_answer: string | null;
```

Every student taking an exam can open DevTools → the Inertia page props → read `is_correct` and
`correct_answer` for every question. The exam system does not actually work.

Fix: never serialize the key. Add an API Resource / transformer that strips it:

```php
'parts' => $exam->parts->map(fn ($part) => [
    'id' => $part->id,
    'title' => $part->title,
    'instructions' => $part->instructions,
    'type' => $part->type,
    'points' => $part->points,
    'questions' => collect($part->questions)->map(fn ($q) => [
        'text'    => $q['text'],
        'type'    => $q['type'],
        'points'  => $q['points'] ?? null,
        // options WITHOUT is_correct
        'options' => collect($q['options'] ?? [])->map(fn ($o) => ['text' => $o['text']])->values(),
    ])->values(),
]),
```

Grading already happens server-side in `submitPart()`, so nothing else has to change.

### 0.2 No resubmission guard 🔴

`submitPart()` does `ExamSubmission::updateOrCreate(...)`. A student can submit part 1, see their
score on the redirect, then POST again with corrected answers — unlimited attempts, last write wins.
The `throttle:10,1` only slows it down.

Fix: reject if a submission already exists (unless the exam explicitly allows retakes).

```php
$existing = ExamSubmission::where([
    'user_id' => $request->user()->id,
    'exam_id' => $exam->id,
    'exam_part_id' => $examPart->id,
])->exists();

abort_if($existing && ! $exam->allows_retake, 409, 'This part has already been submitted.');
```

### 0.3 `duration_minutes` is never enforced server-side 🔴

`duration_minutes` exists on the model, appears in the Filament form and table — and is referenced
**nowhere** in any controller. The timer is purely a frontend countdown. Stop the JS timer, or reload
the page, and you have unlimited time.

Fix: you already have `ExamLiveSession` with `last_seen_at`. Record a `started_at` when the student
opens the part, and in `submitPart()` reject or flag submissions past
`started_at + duration_minutes` (plus a grace window).

### 0.4 `examPart` is not scoped to `exam` 🟠

```php
Route::post('exams/{exam}/parts/{examPart}/submit', ...)
```

No `->scopeBindings()`, and `submitPart()` never checks `$examPart->exam_id === $exam->id`. A student
can POST part IDs from an exam they *do* have access to against any other exam, creating
`ExamSubmission` rows with mismatched `exam_id`/`exam_part_id`. The section-access check in `show()`
is also **not** repeated in `submitPart()` — so a student can submit to an exam they can't even view.

Fix: add `->scopeBindings()` to the route group and re-run the section check in `submitPart()`.
Better: move it into a policy (see §2.3).

### 0.5 Debug endpoint exposed in production 🟠

```php
Route::get('api/grades/debug', [GradeController::class, 'debug'])->name('api.grades.debug');
```

Dumps raw grades, section data, admin flags, and a hardcoded `'ICT 12-A (2026)'` lookup. It's behind
auth but available to every student, permanently. Delete it, or wrap it in
`->middleware('can:viewDebug')` / `if (! app()->isProduction())`.

---

## 1. The flow — where logic lives

### 1.1 `routes/web.php` is 577 lines and contains your business logic

Three closures do the heavy lifting:

| Route | Lines | What's in it |
|---|---|---|
| `dashboard` | ~230 | streak mutation, heatmap query, badge awarding, season resolution, leaderboard assembly |
| `api/leaderboard` | ~75 | leaderboard assembly again |
| `api/dashboard-exams` | ~45 | upcoming-exams mapping again |

Consequences:

- **`route:cache` is impossible.** Closure-based routes can't be serialized. You're running Octane
  for performance while leaving the single cheapest optimization on the table. `php artisan route:cache`
  will fail today.
- **Untestable.** You can only exercise this logic through a full HTTP request.
- **Duplicated three ways.** The leaderboard block (`weeklyXpMap` appears 6× in the file) exists in
  the dashboard closure *and* the API closure, near-identically but subtly divergent — the dashboard
  version computes `xpProgress` and `trend`, the API one casts `weeklyXp` differently. These will
  drift further.

Fix: extract into controllers + services.

```
app/Http/Controllers/DashboardController.php     ← thin
app/Services/LeaderboardService.php              ← forSection(), forUser() — ONE implementation
app/Services/StreakService.php                   ← touch($user)
app/Services/UpcomingExamsService.php
```

Then `Route::get('dashboard', DashboardController::class)` and route caching starts working.

### 1.2 The streak mutation is a write on a GET request

Inside the `dashboard` closure:

```php
$user->update(['current_streak' => 1, ...]);
// ...
$user->increment('current_streak');
$user->update(['last_login_at' => $now]);
$user->update(['longest_streak' => $user->current_streak]);
```

Three problems:
- **A GET request mutates state.** Any prefetch, crawler, or Inertia partial reload fires it.
- **Up to 3 separate UPDATE queries** where one would do.
- **Race condition.** Two concurrent dashboard loads both see `last_login_at` as yesterday and both
  increment. Needs a transaction or an atomic conditional update.

Fix: move to a `StreakService::touch()` called from a login listener (`Illuminate\Auth\Events\Login`),
not a page render. Collapse to a single `update()`.

### 1.3 N+1 queries throughout

`->with(` appears **12 times across all controllers**, and `Model::preventLazyLoading()` is never
called — so nothing warns you. Concrete instances:

**`CourseController::index()`** — inside a `->map()` over every course:
```php
$completedLessons = $course->completedLessonsForUser($user);   // query per course
'modulesCount'    => $course->modules()->count(),              // query per course
```

**Dashboard + `api/dashboard-exams`** — inside a `->map()` over exams:
```php
$submittedPartsCount = ExamSubmission::where(...)->count();    // query per exam
$totalParts = $exam->parts()->count();                         // query per exam
```
Use `withCount(['parts', 'submissions' => fn ($q) => $q->where('user_id', $user->id)])`.

**Dashboard leaderboard** — a `foreach ($userSections as $section)` where each iteration runs a
`$section->users()->with(...)->get()`, a `DB::table('course_user')` aggregate, and a
`SectionProgress::where(...)->whereHas('user')->count()`. That's ~3 queries × section count, and the
`whereHas` rank query is a correlated subquery per section.

Also **dead code** in `CourseController::index()`:
```php
$query->whereHas('modules.lessons', function ($q) use ($userSeasonIds) {
    // No scope needed — just get all enrolled courses for this season
});
```
An empty `whereHas` closure — this silently filters to courses that have *at least one lesson*,
which is almost certainly not intended. Either remove the `whereHas` or implement the filter.

Fix: add to `AppServiceProvider::configureDefaults()`:
```php
Model::preventLazyLoading(! app()->isProduction());
```
This will immediately surface every N+1 in your test suite.

### 1.4 `Setting::get()` — 75 call sites, 1–2 uncached queries each

```php
public static function get(string $key, $default = null): mixed
{
    // for non-super admins: SELECT ... WHERE key = ? AND admin_id = ?   ← query 1
    // then:                 SELECT ... WHERE key = ? AND admin_id IS NULL  ← query 2
}
```

There is no caching, no request-level memoization. Worse, `HandleInertiaRequests::share()` calls it
**6 times on every single request** (`ai_chat_enabled`, `ai_chat_maintenance_message`, `school_name`,
`school_tagline`, `school_logo_path` ×2, `school_accent_color`). `AIService::__construct()` fires
another 9 in its constructor — and that service is resolved per request.

That's easily 15–30 extra queries per page load on a settings table that changes maybe weekly.

Fix: memoize per request and cache across requests.

```php
protected static array $memo = [];

public static function get(string $key, $default = null): mixed
{
    $scope = static::scopeId();               // admin id or 'global'
    return static::$memo["$scope:$key"] ??= Cache::rememberForever(
        "settings:$scope",
        fn () => static::where(...)->pluck('value', 'key')->all()
    )[$key] ?? $default;
}
```
Load **all** settings in one query, cache forever, bust in a `Setting::saved()` observer.
⚠️ Under Octane, static memoization persists between requests — reset it in an
`Octane\Events\RequestReceived` listener or use a scoped singleton instead.

### 1.5 `AIService` — a 536-line class doing four providers' work

You have `AIService` (536 lines, handles Ollama + Cloudflare + Groq + Gemini via four near-identical
`try/catch` blocks — note the repeated `$errorMsg = $response instanceof Response ? ...` at lines 188,
250, 320, 393) *and* separate `CloudflareAIService`, `GroqAIService`, `OllamaAIService` classes that
appear to be the same logic extracted but unused by `AIService`.

Also mixing config sources:
```php
$this->geminiApiKey = config('ai.providers.gemini.key') ?? env('GEMINI_API_KEY');
```
Calling `env()` outside a config file returns `null` once `config:cache` runs — this is a
production-only failure waiting to happen. Also all nine settings are loaded in the constructor even
when only one provider is used.

Fix: a driver interface.

```php
interface AiDriver {
    public function complete(string $prompt, array $options = []): string;
    public function preWarm(): bool;
}

// AiManager extends Illuminate\Support\Manager
// createGeminiDriver(), createGroqDriver(), createOllamaDriver(), createCloudflareDriver()
```
Laravel's `Manager` gives you lazy resolution and `driver()` switching for free. Then `AIService`
becomes a thin facade over `AiManager`, and the three orphan service classes become real drivers
instead of dead code.

### 1.6 No authorization layer

`app/Policies/` does not exist. Authorization is ad-hoc `abort()` calls scattered across controllers:

```php
app/Http/Controllers/Admin/ExamSubmissionController.php:16:  abort(403, 'Unauthorized');
app/Http/Controllers/Admin/ExamSubmissionController.php:33:  abort(403, 'Unauthorized');
app/Http/Controllers/Games/TowerDefenseController.php:170:   abort_unless($run->user_id === auth()->id(), 403);
```

…and that's essentially all of it, across 15 controllers. The exam section-access check (§0.4) lives
inline in one method and isn't reused. Admin routes are guarded by a manual `is_admin` check rather
than middleware.

Fix: `ExamPolicy`, `SubmissionPolicy`, `CoursePolicy`, `TdRunPolicy`; `$this->authorize('view', $exam)`.
Add an `admin` middleware alias for the `admin/*` route group instead of in-controller checks.

### 1.7 Validation is inline, not in Form Requests

Only 4 Form Requests exist, all under `Settings/`. Everywhere else validates inline —
13 `$request->validate([...])` calls across 9 controllers. `submitPart()`'s is the weakest:

```php
$validated = $request->validate(['answers' => 'required|array']);
```

No shape validation at all. `answers.*.question_number` and `answers.*.answer` are read but never
validated, so malformed payloads reach the grading loop and rely on `?? null` to not crash.

### 1.8 No enums for status

34 occurrences of `'draft'`, `'published'`, `'pending'`, `'graded'` as raw strings, plus
`'pending_review'`, `'submitted'`, `'in_progress'`, `'closed'`, `'starting'`, `'submitting'`,
`'finished'`. Typos are silent. `ExamStatus`, `SubmissionStatus`, `RunStatus` backed enums + model
casts would make these compile-time errors and give you `$exam->status->isOpen()` helpers.

### 1.9 Missing architectural layers

`app/Http/Resources`, `app/Enums`, `app/Policies`, `app/Events`, `app/Listeners`, `app/Observers` —
**none exist**. The consequence is that serialization, authorization, and side effects all get inlined
into controllers and routes, which is exactly what §1.1–§1.8 describe. Badge awarding
(`awardEligibleBadges` called mid-dashboard-render) is a textbook event listener.

---

## 2. The design layer

### 2.1 The token system is good — but bypassed

`app.css` (1401 lines) defines a proper `@theme` with semantic tokens (`--color-background`,
`--color-card`, `--color-muted-foreground`, sidebar tokens, radii) and light/dark/`theme-about`
variants. That's the right architecture.

But it's routinely bypassed:
- **61 hardcoded hex colors** in `.vue` files (`#10b981` ×9, `#f59e0b` ×6, `#0b0b0d` ×3…). None of
  these respond to dark mode or theme switching.
- **693 arbitrary Tailwind values** (`[420px]`, `[13px]`, …) — spacing and sizing decided per-component.
- **37 `<style scoped>` blocks**, meaning a second, invisible styling system alongside Tailwind.

`#f59e0b` is especially telling — it's the default `school_accent_color` in
`HandleInertiaRequests`, hardcoded in both PHP and CSS. That should be one CSS variable set from the
branding prop.

Fix: promote recurring hexes to `@theme` tokens (`--color-success`, `--color-warning`), add a spacing
scale for the common arbitrary values, and lint against raw hex in templates.

### 2.2 Three animation libraries, one of them phantom

```
gsap             → 29 files  ✅ the real one
@motionone/vue   → 17 files  ✅ also real
lenis            →  8 files
motion           → 24 files
framer-motion    →  0 files  ❌ REACT LIBRARY, ZERO IMPORTS
tw-animate-css   →  0 files  ❌ zero imports
```

`framer-motion@^12.40.0` is a **React** animation library in a Vue project with no usages —
pure dependency weight and a confusing signal to anyone reading `package.json`. `tw-animate-css`
likewise unused.

More importantly, **GSAP and Motion One overlap almost entirely**. Two tween engines, two easing
vocabularies, two timeline models, both in the bundle. Pick one — you have GSAP skill docs in
`.claude/skills/` suggesting GSAP is the intended standard — and migrate the 17 Motion One files.

```bash
npm uninstall framer-motion tw-animate-css
```

### 2.3 Animation is not centralized

29 files import GSAP directly and each defines its own durations and eases. There's no
`useAnimation()` composable or shared token set, so "the standard page transition" is re-implemented
per component. Combined with 693 arbitrary values, the UI drifts visually between pages.

Also: only **11 references to `prefers-reduced-motion`** across a codebase this animation-heavy.
Most GSAP timelines will run at full intensity for users who asked the OS not to. Use
`gsap.matchMedia()` centrally.

### 2.4 God components

| File | Lines |
|---|---|
| `pages/Exams/Show.vue` | **3629** (1471 script / 2110 template) |
| `pages/Grades.vue` | 1913 |
| `Games/TowerDefense/engine/Game.ts` | 1514 |
| `pages/Assignments.vue` | 1220 |
| `Games/TowerDefense/Playfield.vue` | 1199 |
| `pages/Dashboard.vue` | 1057 |
| `components/ImprovedLeaderboard.vue` | 1054 |

`Exams/Show.vue` alone holds 173 top-level `const`s, 26 `ref`s, 6 `watch`ers and 78 functions.
This is the single hardest file in the repo to change safely — and it's the one handling your
highest-stakes flow. Extract: `useExamTimer`, `useExamAutosave`, `useExamProgress` composables;
`QuestionRenderer`, `PartNavigator`, `SubmitDialog` components.

Note `Dashboard.vue` at 1057 lines is fed by the 230-line dashboard closure — the same feature is
oversized on both ends.

### 2.5 Memory leaks

Three components register listeners/timers with no teardown:

```
resources/js/components/MobileNav.vue
resources/js/pages/Grades.vue
resources/js/pages/Ngl.vue
```

They call `addEventListener` / `setInterval` / `setTimeout` inside `onMounted` but have no
`onUnmounted`/`onBeforeUnmount`. In an Inertia SPA, components unmount on every navigation, so these
accumulate across a session. `@vueuse/core` is already a dependency (46 files) — `useEventListener`
and `useIntervalFn` auto-clean and are drop-in replacements.

### 2.6 Accessibility gaps

- **21 `<img>` tags without `alt`**.
- Only **20 of 229** components use `aria-label` at all.
- `reka-ui` gives you accessible primitives for dialogs/menus/tooltips — good — but the custom
  interactive surfaces (game canvas, leaderboard, exam navigation) are hand-rolled.

For an education product this is likely a procurement/compliance requirement, not just polish.

### 2.7 Composables are utilities, not domain logic

`resources/js/composables/` holds 10 files, all generic (`useAppearance`, `useMobile`, `useInitials`,
`useLenis`). There is **no domain composable** — no `useExam`, `useLeaderboard`, `useCourseProgress`.
That's precisely why the page components are 1000–3600 lines. The composable layer exists; it just
never absorbed any feature logic.

---

## 3. Tooling, tests, repo hygiene

### 3.1 Test coverage is thin where risk is highest

20 test files, and the distribution is inverted:

- ✅ Auth (7 files), Settings (3) — well covered, mostly starter-kit tests
- ❌ **Exams — zero tests.** No grading test, no submission test, no access-control test.
- ❌ Courses, Assignments, Grades, Tower Defense, AI services, Learning Map — zero tests.
- `ExampleTest.php` still present in both Feature and Unit.

The grading logic in `submitPart()` (essay batching, identification normalization, MC index matching)
is intricate, money-critical, and completely unverified. That's where the next 5 tests should go.

### 3.2 CI is missing checks you already wrote

`composer.json` defines a `ci:check` script that runs lint + format + **`types:check`** + tests.
`tests.yml` runs only `./vendor/bin/pest`. So `vue-tsc --noEmit` — your TypeScript safety net —
**never runs in CI**. Same for `npm run format:check`.

Also `lint.yml` runs `npm run format` and `npm run lint` (both **write** mode, `--fix`) rather than
the `:check` variants. Since the auto-commit step is commented out, the fixes are computed and
thrown away — CI passes while the repo stays unformatted.

Fix: `run: composer ci:check` in one job. Change `lint.yml` to `format:check` / `lint:check`.

Also: `.github/workflows` targets branches `develop|main|master|workos` — `workos` looks like a
leftover from the starter kit.

### 3.3 Build logs are committed

```
build.log
build_error.log
build_full.log
```

All three are tracked in git. `build_error.log` is 3.9 KB of build output. Add `*.log` to
`.gitignore` and `git rm --cached` them.

There's also a stray `.rnd` (1 KB, an OpenSSL entropy file) at the repo root.

### 3.4 Root-level docs sprawl

`FORM_METHOD_ANALYSIS.md`, `TOWER_DEFENSE.md`, `PRODUCTION.md`, `README.md` at root.
`FORM_METHOD_ANALYSIS.md` in particular reads like a one-off investigation note. Move to `docs/`.

### 3.5 Duplicated agent skill files

`.agents/skills/` and `.claude/skills/` contain the **same 8 GSAP skill files**. Symlink one to the
other or pick a single location.

### 3.6 TypeScript strictness

`tsconfig.json` has `strict: true` ✅, but eslint disables the guardrail:

```js
'@typescript-eslint/no-explicit-any': 'off',
```

with 34 `: any` annotations in the codebase. Zero `@ts-ignore`, which is good — the escape hatch
being used is `any` instead. Turn the rule to `'warn'` and burn down the 34.

Also 8 stray `console.log` calls in `resources/js` — add `no-console` as a warning.

### 3.7 Octane + static state

You're running Octane (RoadRunner), which keeps the app in memory between requests. Any static
property or singleton holding request state leaks across users. `Setting::get()` reads `auth()->user()`
and is a good candidate to be memoized (§1.4) — do that carefully, or you'll serve one admin's
workspace settings to another. Worth an audit pass for static state generally.

---

## Priority

**Now — correctness & security**
1. Strip answer keys from exam payloads (§0.1)
2. Block exam resubmission (§0.2)
3. Enforce `duration_minutes` server-side (§0.3)
4. `scopeBindings()` + section check in `submitPart()` (§0.4)
5. Remove `api/grades/debug` (§0.5)
6. Write the first exam grading tests (§3.1)

**Next — flow**
7. `Model::preventLazyLoading()` in dev, then fix what it surfaces (§1.3)
8. Cache `Setting::get()` (§1.4) — biggest single perf win
9. Extract dashboard/leaderboard closures → controllers + `LeaderboardService` (§1.1)
10. Move streak logic out of the GET request (§1.2)
11. `composer ci:check` in CI; `:check` variants in lint.yml (§3.2)

**Then — structure & design**
12. Add `app/Policies`, `app/Enums`, `app/Http/Resources` (§1.6, §1.8, §1.9)
13. `npm uninstall framer-motion tw-animate-css`; consolidate GSAP vs Motion One (§2.2)
14. Fix the 3 memory leaks with `@vueuse/core` (§2.5)
15. Break up `Exams/Show.vue` into composables (§2.4)
16. Refactor `AIService` into a driver Manager (§1.5)
17. Token cleanup: hexes → `@theme`, reduce arbitrary values (§2.1)
18. `alt` text + `prefers-reduced-motion` pass (§2.6, §2.3)

**Hygiene**
19. Untrack `*.log`, remove `.rnd`, docs → `docs/`, dedupe skill files (§3.3–§3.5)
