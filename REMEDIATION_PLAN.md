# Luav6 — Remediation Plan

Companion to `CODEBASE_REVIEW.md`. Sequenced so each phase is independently shippable and
low-risk, with the security fixes first and the refactors gated behind tests that prove
behaviour didn't change.

**Guiding rules**
- Nothing in Phase 1 lands without a test that fails before the fix and passes after.
- Refactors (Phases 3–5) are behaviour-preserving. If a refactor "fixes a bug", split it out.
- One PR per numbered task unless stated otherwise. Keep them small enough to actually review.
- Run `composer ci:check` locally before every push.

---

## Correction to the review: the exam leak is worse than reported

I said `ExamController::show()` leaks the answer key. That's true, but `index()` is worse:

```php
// ExamController::index() — line 23
$exams = Exam::with(['section.season', 'parts' => ...])
    ->where('status', '!=', 'draft')
    ...
return Inertia::render('Exam', ['examsBySeason' => $examsBySeason]);
```

It eager-loads `parts` for **every exam the student can see** and ships `$exam->toArray()`.
So the answer key for every not-yet-attempted exam is in the props of the exam **list** page.
A student doesn't even have to open an exam.

**And there's a legitimate consumer.** `Exam.vue` is a combined list + post-submission review
screen — `isAnswerCorrect()` (line 302) and the review template (lines 827, 854) genuinely need
`is_correct` and `correct_answer` to show "correct answer" feedback. So this is **not** a
delete-the-field fix. The key must be present for parts the student has already submitted and
absent otherwise. That shapes Task 1.1 below.

`Courses/Lesson.vue:31` has the same shape (`quiz.questions` passed raw from
`CourseController::lesson()`), so lesson quizzes leak too — folded in as Task 1.2.

---

## Phase 1.0 — The live incident: lost exam submissions 🔴🔴

**This is now the top priority — above the answer-key leak.** Students are losing completed exam
work in class today.

### Diagnosis

Reported symptom: "Octane fails, the student sees submitted, but the part still shows unanswered."

Octane is not failing. `ExamController::submitPart()` runs AI grading **synchronously, before
persisting**:

```php
line 214:  $essayAssessments = $this->aiService->batchAssessEssays($essaysToProcess);  // blocks ≤45s
line 274:  $submission = ExamSubmission::updateOrCreate(...);                          // the save
line 287:  return redirect('/exams/'.$exam->id);
```

If the request dies during line 214 — timeout, browser giving up, worker recycled — **line 274 never
runs and the answers are lost**. The frontend has already optimistically shown "submitted".

Three compounding factors:

1. **No queue on the student path.** `GenerateExamEssayFeedback implements ShouldQueue` exists but is
   only dispatched from Filament admin screens. Student submits grade inline.
2. **`Http::pool()` fires all essays at once**, not sequentially. 25 students × 2–4 essays =
   **50–100 concurrent Cloudflare calls in one burst**, each `->timeout(45)`.
3. **Worker starvation.** Server is started as `octane:start --host=... --port=8000` with no
   `--workers`, and there is no `.rr.yaml`. RoadRunner therefore defaults to **one worker per CPU
   core** — 4–8 on a laptop, which is also running Vite, SQLite and a browser.

So ~25 simultaneous submits contend for ~4–8 workers, each held up to 45s. The overflow queues at the
HTTP layer and times out. **This is the entire bug.**

Secondary findings:

- **`headers: { 'X-Inertia-Timeout': 300000 }`** in `Show.vue:1146` does nothing. It is not part of
  the Inertia protocol and axios takes `timeout` as a config option, not a header. It has never
  extended any timeout. And `config/octane.php` sets `'max_execution_time' => 180`, so a 5-minute
  request is impossible anyway.
- **Rate-limit failures are silent.** `parseCloudflareResponses()` returns `['score' => 0.0]` on a
  429/5xx and only writes to the log. Students get **silently zeroed essays** with no error shown.
  Even on Workers AI's generous limits, a 100-call burst can trip per-model limits — worth confirming
  actual quota in the Cloudflare dashboard.

### 1.0.1 — Immediate mitigation (do before the next exam, ~10 minutes)

Requests are blocked on network I/O, not CPU, so workers can be heavily oversubscribed:

```bash
php artisan octane:start --host=<lan-ip> --port=8000 --workers=32 --max-requests=500
```

Not a fix — 45-second requests remain fragile — but it removes the immediate starvation cliff.
`--max-requests=500` recycles workers periodically to contain any memory leak.

Also move session + cache off SQLite (see 1.0.5). Both are zero-risk.

### 1.0.2 — Persist first, grade asynchronously 🔴

The actual fix. Reorder `submitPart()`:

1. Grade auto-gradable questions inline — MC, true/false, identification are pure local computation,
   microseconds, no network.
2. **Write the `ExamSubmission` immediately** with the partial score and
   `status = 'pending_grading'` if the part contains essays, else `'submitted'`.
3. `GradeEssaySubmission::dispatch($submission->id)` — one job per submission.
4. Redirect. Total request time ~20ms, no outbound network, effectively cannot fail.

The job calls `batchAssessEssays()`, adds the essay score, sets `status = 'graded'`.

⚠️ **Interacts with 1.3 (resubmission block) and the XP hooks.** `ExamSubmission::booted()` applies
an XP *delta* on `created` and again on `updated` when `score` changes. With async grading the row is
now created with a partial score and updated later — so XP will be applied twice, in two pieces.
That happens to be arithmetically correct (delta logic), but **must be covered by a test** before
shipping. Verify: create with MC score 8 → XP +8; job updates to 14 → XP +6; total 14. 

⚠️ **Consider deferring XP entirely to `graded`** to avoid a student seeing their XP jump twice.
Cleaner, but a bigger change to the hook. Decide during implementation.

### 1.0.3 — Run a queue worker alongside Octane 🔴

`QUEUE_CONNECTION=database` and the server is started by hand, so **there is almost certainly no
queue worker running.** Today that is harmless because nothing queues on the student path. The moment
1.0.2 lands, submissions would save but never grade.

Provide a supervised worker. Minimum viable for a laptop:

```bash
# second terminal
php artisan queue:work --tries=3 --backoff=10 --timeout=120
```

Better: a systemd unit or `supervisord` config committed to the repo, plus a single
`start-classroom.sh` that launches Octane + queue worker together so there is one command to run and
nothing to forget. Document it in `PRODUCTION.md`.

Start with **1 worker** — grading is then genuinely sequential, which matches the intended design.
Scale to 2–3 if the backlog is too slow.

### 1.0.4 — Honest progress UI, driven by real state

The "~1 minute" counter idea is good, but must not hold the HTTP request open. With 1.0.2 the submit
returns instantly, so:

- Add `GET /exams/{exam}/parts/{part}/status` returning `{ status, graded_at }`.
- After submit, poll every 2–3s and show "Grading your essays…" with a position/estimate derived from
  actual pending-job count, not a hardcoded guess.
- Auto-gradable parts skip this entirely — they are already final.

Remove the fake `X-Inertia-Timeout` header.

### 1.0.5 — Take session/cache off SQLite (~10 minutes)

```env
SESSION_DRIVER=file      # every page load currently writes a session row
CACHE_STORE=octane       # or 'file'
```

`DB_CONNECTION=sqlite` with `SESSION_DRIVER`, `CACHE_STORE` **and** `QUEUE_CONNECTION` all set to
`database` means one single-writer file absorbs every session write, every cache write, and (after
1.0.3) queue polling. WAL + `busy_timeout=5000` are correctly configured and buy headroom, but past
5s of write contention you get `SQLITE_BUSY` — which surfaces as a random 500 and looks exactly like
"Octane failing".

⚠️ Switching `SESSION_DRIVER` logs everyone out. Do it between classes.

### 1.0.6 — Keep the localStorage draft, but reconcile it

The existing draft-save (`Show.vue:503/538/557`) was the right instinct and should stay as a safety
net. One change: on load, reconcile against server state so a part that *did* submit successfully
doesn't restore a stale local draft over it. Clear the draft only on a confirmed server-side
`submission.id`, not merely on redirect.

### 1.0.7 — Surface AI failures instead of silently zeroing

In `parseCloudflareResponses()`, a failed call yields `score => 0.0` indistinguishable from a
genuinely zero-scoring essay. Add a `grading_failed` flag on the submission so the teacher can see
"3 essays failed to grade" in Filament and re-run, rather than a student silently receiving a zero.
Pairs with the existing `GenerateExamEssayFeedback` re-run action.

### 1.0.8 — Prove it before the next exam

Load test simulating the real classroom: **25 concurrent submits, 3 essays each**.

```bash
# k6, artillery, or a simple parallel curl loop against a seeded staging DB
```

Assert: zero lost submissions, all rows written, p95 submit response < 200ms.
This is the acceptance criterion for Phase 1.0 — without it you are guessing again.

**Exit criteria:** 25-concurrent load test passes with zero data loss; queue worker supervised;
submit response is a local write with no outbound HTTP.

---

## Phase 0 — Safety net (do this first, ~half a day)

Can't safely change grading logic with no tests and no factories.

### 0.1 Build the missing factories
Only `UserFactory` exists. Create:
```
database/factories/ExamFactory.php          + states: draft(), published(), closed()
database/factories/ExamPartFactory.php      + states: multipleChoice(), identification(), essay()
database/factories/ExamSubmissionFactory.php
database/factories/SectionFactory.php
database/factories/SeasonFactory.php
database/factories/CourseFactory.php / CourseModuleFactory / LessonFactory / LessonQuizFactory
```
`ExamPartFactory` matters most — it needs to generate realistic `questions` JSON
(text/type/points/options with `is_correct`, `correct_answer`) so grading tests are meaningful.

Add a `UserFactory::admin()` state (`is_admin => true`) and `superAdmin()`.

### 0.2 Characterisation tests for grading
Before touching `submitPart()`, lock in current behaviour — including quirks:
```
tests/Feature/Exams/GradingTest.php
  - multiple choice: correct index scores, wrong index doesn't
  - true/false: same
  - identification: case/whitespace/punctuation normalization ("Manila " == "manila!")
  - essay: score comes from AIService (fake the service)
  - unanswered questions are skipped but still count toward totalPossible
  - mixed part totals correctly
```
Fake `AIService` via `$this->mock(AIService::class)` so tests don't hit a network.

### 0.3 Enable lazy-loading detection
`AppServiceProvider::configureDefaults()`:
```php
Model::preventLazyLoading(! app()->isProduction());
Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
```
Expect the suite to go red. **Don't fix the N+1s yet** — note them and move on; they're Phase 3.
If it's too noisy to work with, gate it to the test env only for now.

**Exit criteria:** `php artisan test` green (excluding known lazy-load failures), factories usable.

---

## Phase 1 — Security & correctness (ship this week)

### 1.1 Stop leaking exam answer keys 🔴
**Approach:** a single serializer that takes a part + "may see answers?" flag.

Create `app/Http/Resources/ExamPartResource.php`:
```php
public function toArray($request): array
{
    $reveal = $this->resource->revealAnswers ?? false;   // set by caller

    return [
        'id' => $this->id,
        'title' => $this->title,
        'instructions' => $this->instructions,
        'type' => $this->type,
        'sort_order' => $this->sort_order,
        'points' => $this->points,
        'questions' => collect($this->questions)->map(fn ($q) => array_filter([
            'text'    => $q['text'],
            'type'    => $q['type'],
            'points'  => $q['points'] ?? null,
            'options' => collect($q['options'] ?? [])
                ->map(fn ($o) => $reveal ? $o : ['text' => $o['text']])
                ->values()->all(),
            'correct_answer' => $reveal ? ($q['correct_answer'] ?? null) : null,
        ], fn ($v) => $v !== null))->values(),
    ];
}
```

Then:
- **`show()`** (taking the exam) — always `$reveal = false`.
- **`index()`** (list + review) — `$reveal` per part = "this user has a submission for this part".
  Compute the submitted-part IDs once (you already query submissions there) and set the flag.
- Optionally gate review on `$exam->status === 'closed'` too, so students can't compare answers
  while a section is still mid-exam. **Product decision — confirm before implementing.**

⚠️ The `Cache::remember("exam_structure_{$exam->id}")` in `show()` caches the **model**, which is
fine, but make sure the resource is applied *after* the cache read, never cached with `$reveal`
baked in. Safer: cache only the raw structure array, never the serialized-for-user payload.

Update TS types in `Exams/Show.vue:51-52` — `options` loses `is_correct`, `correct_answer` goes.
`Exam.vue` keeps them but as optional (`is_correct?: boolean`).

**Tests:** student GETs an unsubmitted exam → response props contain no `is_correct` / `correct_answer`
anywhere (assert on the raw JSON string, not field-by-field). Student who submitted part 1 → sees
key for part 1 only, not part 2.

### 1.2 Same fix for lesson quizzes 🔴
`CourseController::lesson()` passes `'questions' => $lesson->quiz->questions` raw.
Apply the same treatment: strip `is_correct` unless `$userProgress->completed` (or attempts
exhausted). Same test shape.

### 1.3 Block exam resubmission 🔴
In `submitPart()`, before grading:
```php
$alreadySubmitted = ExamSubmission::where([
    'user_id'      => $request->user()->id,
    'exam_id'      => $exam->id,
    'exam_part_id' => $examPart->id,
])->exists();

abort_if($alreadySubmitted, 409, 'You have already submitted this part.');
```
Then change `updateOrCreate` → `create`.

⚠️ **Check first:** `ExamSubmission::booted()` has an `updated` hook that applies a *score delta* to
the student's XP. That hook exists specifically to handle re-scoring. Killing `updateOrCreate` means
student-facing resubmits stop, but admin re-grades (Filament) still fire `updated` — that's fine and
should keep working. Verify the Filament re-grade path still adjusts XP correctly after this change.

If retakes are a wanted feature, add `exams.max_attempts` (default 1) and count instead of `exists()`.
Don't build it speculatively.

**Test:** submit part twice → second gets 409, score unchanged, only one row, XP applied once.

### 1.4 Enforce the time limit server-side 🔴
`duration_minutes` is read by nothing. Wire it up:

1. Migration: add `started_at` (timestamp, nullable) to `exam_live_sessions`.
2. In `monitorProgress()`, when `status === 'starting'`, set `started_at` on create only
   (`updateOrCreate` with `started_at` in the *create* half — don't reset it on later pings).
   Better: a dedicated `POST /exams/{exam}/parts/{part}/start` so the clock has one owner.
3. In `submitPart()`:
```php
$session = ExamLiveSession::where('user_id', $userId)->where('exam_id', $exam->id)->first();
$deadline = $session?->started_at?->addMinutes($exam->duration_minutes)->addSeconds(30); // grace
abort_if($deadline && now()->greaterThan($deadline), 422, 'Time limit exceeded.');
```

**Decision needed:** hard-reject, or accept-and-flag (`status => 'late'`)? Rejecting loses a
student's work if their connection hiccups — for a school setting I'd **accept and flag**, and let
the teacher decide. Recommend flagging; confirm with stakeholders.

⚠️ The unique constraint on `exam_live_sessions` is `['user_id','exam_id']`, so the timer is
per-exam, not per-part. If each part should have its own clock, the constraint needs to change.
Clarify intent before implementing.

### 1.5 Scope route bindings + authorize `submitPart` 🟠
```php
Route::post('exams/{exam}/parts/{examPart}/submit', ...)->scopeBindings();
```
Apply to the whole exam route group. Then add the section-access check to `submitPart()` —
currently only `show()` has it. Extract to a private `assertCanAccess(Exam $exam)` now; it becomes
`ExamPolicy` in Phase 4.

**Test:** part from exam A submitted against exam B → 404. Student outside the section → 403.

### 1.6 Tighten `submitPart` validation 🟠
```php
$request->validate([
    'answers'                   => ['required', 'array', 'max:200'],
    'answers.*.question_number' => ['required', 'integer', 'min:1'],
    'answers.*.answer'          => ['present'],
]);
```
Also cap essay length (`max:20000`) — essays go straight to a paid AI provider, so this is a cost
control as much as a validation fix.

### 1.7 Remove the debug endpoint 🟠
Delete the `api/grades/debug` route and `GradeController::debug()`. It leaks raw grades and has a
hardcoded `'ICT 12-A (2026)'` reference. If it's genuinely useful, make it an artisan command.

**Exit criteria:** all 7 tasks tested; a manual DevTools pass on the exam list page shows no answer key.

---

## Phase 2 — Cheap high-impact performance (~1 day)

### 2.1 Cache `Setting::get()`
Highest value-per-line in the codebase. 75 call sites, 1–2 queries each, ~15 on every request from
`HandleInertiaRequests` + `AIService`.

```php
public static function get(string $key, $default = null): mixed
{
    $user  = auth()->user();
    $scope = ($user?->is_admin && ! $user->is_super_admin) ? $user->id : null;

    $global = Cache::rememberForever('settings:global',
        fn () => static::whereNull('admin_id')->pluck('value', 'key')->all());

    if ($scope) {
        $ws = Cache::rememberForever("settings:admin:$scope",
            fn () => static::where('admin_id', $scope)->pluck('value', 'key')->all());
        if (array_key_exists($key, $ws)) return $ws[$key];
    }

    return $global[$key] ?? $default;
}
```
Bust in a `SettingObserver` on `saved`/`deleted` (forget both keys).

⚠️ **Octane:** do *not* add a `static $memo` array — it persists across requests and will serve one
admin's workspace settings to the next user. The `Cache` facade is safe. If you want per-request
memoization, use a scoped singleton registered with `$this->app->scoped(...)`, which Octane resets.

**Test:** two admins with different workspace values get their own; changing a setting busts cache;
`assertDatabaseQueryCount` style assertion that a dashboard render doesn't issue 15 settings queries.

### 2.2 Fix the `env()` call outside config
```php
$this->geminiApiKey = config('ai.providers.gemini.key') ?? env('GEMINI_API_KEY');
```
Returns `null` once `config:cache` runs — a production-only failure. Move the fallback into
`config/ai.php` and read config only.

### 2.3 Make `AIService` construction lazy
Its constructor issues ~9 `Setting::get()` calls for all four providers, on every resolution.
After 2.1 this is cheap, but still — only read the active provider's settings. Full driver refactor
is Phase 5; this is a two-line guard.

### 2.4 Enable route caching
Blocked by the closures in `routes/web.php` — that's Phase 3. Once done, add
`php artisan route:cache` to the deploy path and a CI assertion that it succeeds.

---

## Phase 3 — Untangle the request flow (~3–4 days)

Behaviour-preserving. Write a characterisation test for the dashboard payload **before** starting,
and diff the JSON before/after.

### 3.1 Extract the leaderboard (do this one first)
It's duplicated between the `dashboard` closure and `api/leaderboard`, already diverging
(`xpProgress`/`trend` in one, different `weeklyXp` cast in the other).

`app/Services/LeaderboardService.php`:
```php
public function forUserSections(User $user, Season $season): Collection
```
One implementation, both call sites use it. Pick the dashboard version's shape as canonical (it's
the superset) and confirm the API consumer tolerates the extra fields.

While in here, fix the per-section N+1: the `foreach` runs ~3 queries per section, and the rank
query is a correlated `whereHas` subquery. Batch the section-user fetch and compute ranks with a
single windowed query (`ROW_NUMBER() OVER (PARTITION BY section_id ORDER BY exp DESC)`).
⚠️ SQLite (dev) and Postgres (prod) both support window functions — verify on both.

### 3.2 Move streak logic out of the GET request
Currently mutates on dashboard render, with up to 3 UPDATEs and a race between concurrent loads.

`app/Services/StreakService.php::touch(User $user): void`, called from a listener on
`Illuminate\Auth\Events\Login`. Collapse to one atomic update.

⚠️ **Behaviour change:** streaks currently advance on *dashboard visit*, not login. With Fortify +
"remember me", a returning user may not fire `Login` for weeks. Safer: keep calling it on dashboard
render for now but move the *logic* into the service and make it idempotent + single-query, then
switch the trigger to a login/session listener as a separate, deliberate change.

### 3.3 `DashboardController`
Move the 230-line closure into a single-action controller that composes `LeaderboardService`,
`StreakService`, `UpcomingExamsService`. Target: under 40 lines.

Extract badge awarding (`awardEligibleBadges` mid-render) into a listener too, or at minimum out of
the render path.

### 3.4 `UpcomingExamsService`
Third duplicate: the exam-mapping block appears in the dashboard closure and `api/dashboard-exams`.
Fix its N+1 while extracting:
```php
Exam::withCount(['parts'])
    ->withCount(['submissions as submitted_parts' => fn ($q) => $q->where('user_id', $user->id)])
```

### 3.5 Empty the rest of `routes/web.php`
Remaining closures: `/`, `/about`, `api/leaderboard/toggle-blur`, `users/{user}/xp-history`.
Move to thin controllers (`WelcomeController`, `ProfileController@toggleBlur`, etc.).

**Exit criteria:** `php artisan route:cache` succeeds. `routes/web.php` under ~120 lines, zero
closures. Dashboard JSON identical to the pre-refactor snapshot.

### 3.6 Fix remaining N+1s
Now clear the `preventLazyLoading` failures from 0.3. Known:
- `CourseController::index()` — `completedLessonsForUser()` and `modules()->count()` per course
- The empty `whereHas('modules.lessons', function () {})` — **this is a live bug**, not just dead
  code: it silently filters out courses with no lessons. Decide the intent and either remove it or
  implement the season filter it was meant to be.
- `ExamController::index()` — per-exam submission query inside the map

---

## Phase 4 — Missing architectural layers (~2–3 days)

### 4.1 Enums
`app/Enums/ExamStatus.php`, `SubmissionStatus.php`, `LiveSessionStatus.php`, `TdRunStatus.php`.
Back them with strings, cast on the models, replace the 34 literal occurrences. Add helpers
(`ExamStatus::Draft->isVisibleToStudents()`).
Do this **after** Phase 1 — the security fixes touch the same lines and rebasing enum changes is
tedious.

### 4.2 Policies
`ExamPolicy`, `ExamSubmissionPolicy`, `CoursePolicy`, `LessonPolicy`, `TdRunPolicy`.
Fold in the ad-hoc checks from 1.5, `TowerDefenseController:170`, `Admin/ExamSubmissionController:16,33`,
and `CourseController::submitQuiz`'s enrollment check.
Add an `admin` middleware alias and apply it to the `admin/*` group instead of in-controller `abort()`.

### 4.3 Form Requests
Replace the 13 inline `$request->validate()` calls. Priority: `SubmitExamPartRequest` (from 1.6),
`SubmitQuizRequest`, `StartTdRunRequest`, `FinishTdRunRequest`.
Move authorization into `authorize()` where a policy fits.

### 4.4 API Resources
`ExamPartResource` already exists from 1.1. Add `ExamResource`, `LeaderboardEntryResource`,
`CourseResource`, `NotificationResource`. This kills the big inline `->map(fn ...)` array literals
and gives the frontend a stable contract.

### 4.5 Observers / Events
`ExamSubmission::booted()` currently holds XP math + learning-map sync in static closures — ~120
lines of business logic in a model hook. Move to `ExamSubmissionObserver` and/or a
`SubmissionScored` event with `AwardExamXp` + `SyncLearningMapProgress` listeners.
⚠️ Do this carefully and with tests — the delta logic (`applyScoreDeltaToStudent`) is subtle and
handles create/update/delete. This is the highest-regression-risk refactor in the plan; consider
deferring it if time is short.

---

## Phase 5 — Frontend (~3–4 days, parallelisable with 3–4)

### 5.1 Drop dead dependencies (10 minutes, do it now)
```bash
npm uninstall framer-motion tw-animate-css
```
`framer-motion` is a **React** library, zero imports, in a Vue app. `tw-animate-css` unused.
Verify with `npm run build` after.

### 5.2 Consolidate GSAP vs Motion One
Two overlapping tween engines: GSAP (29 files) + Motion One (17 files). Standardise on **GSAP** —
you already have GSAP skill docs in `.agents/skills/` and it's the wider usage. Migrate the 17
Motion One files, then drop `@motionone/vue`.
Do it incrementally, a few files per PR — this is cosmetic-risk, not correctness-risk, but it's
easy to leave a page half-animated.

### 5.3 Fix the memory leaks
`MobileNav.vue`, `Grades.vue`, `Ngl.vue` register listeners/timers in `onMounted` with no teardown;
in an Inertia SPA they accumulate across navigations. `@vueuse/core` is already a dependency —
swap to `useEventListener` / `useIntervalFn`, which auto-clean.
Small, isolated, high value. Good first PR for someone.

### 5.4 Centralise animation + reduced motion
`resources/js/composables/useAnimation.ts` exporting shared durations/eases, wrapped in
`gsap.matchMedia()` so `prefers-reduced-motion` is honoured once instead of 11 scattered checks.

### 5.5 Break up `Exams/Show.vue` (3629 lines)
Highest-stakes flow, hardest file to change. Extract in this order:
1. `composables/useExamTimer.ts` — pairs with the 1.4 server-side clock
2. `composables/useExamAutosave.ts`
3. `composables/useExamProgress.ts` — the `monitorProgress` pings
4. Components: `QuestionRenderer`, `PartNavigator`, `SubmitDialog`
Then `Grades.vue` (1913) and `Assignments.vue` (1220).

### 5.6 Design tokens
Promote the recurring hexes to `@theme` tokens — `#10b981` (×9) → `--color-success`,
`#f59e0b` (×6) → `--color-warning`. Note `#f59e0b` is also the hardcoded default
`school_accent_color` in `HandleInertiaRequests`; drive it from a CSS variable set from the
branding prop so theming actually works end to end.
Then chip away at the 693 arbitrary values with a spacing scale. This is ongoing, not a single PR.

### 5.7 Accessibility
21 `<img>` without `alt`; only 20 of 229 components use `aria-label`. Start with the `alt`
attributes (mechanical), then keyboard nav on exam/leaderboard/game surfaces.
For an education product this is plausibly a procurement requirement — worth confirming whether
you're subject to WCAG/Section 508 obligations, which would move it up the list.

---

## Phase 6 — Tooling & hygiene (~half a day, do anytime)

### 6.1 Fix CI
`tests.yml` runs only Pest, so `vue-tsc --noEmit` and `format:check` never run — despite
`composer ci:check` already being defined to run them.
```yaml
- name: CI checks
  run: composer ci:check
```
`lint.yml` runs `npm run format` and `npm run lint` (**write** mode) with the auto-commit step
commented out — it computes fixes and throws them away, so CI is green on unformatted code.
Switch to `format:check` / `lint:check`.
Drop the `workos` branch trigger (starter-kit leftover).

### 6.2 Untrack build artefacts
```bash
git rm --cached build.log build_error.log build_full.log .rnd
echo "*.log" >> .gitignore
echo ".rnd"  >> .gitignore
```

### 6.3 Docs
Move `FORM_METHOD_ANALYSIS.md`, `TOWER_DEFENSE.md`, `PRODUCTION.md` into `docs/`. Keep `README.md`
at root. Add `CODEBASE_REVIEW.md` + this plan to `docs/` too.

### 6.4 Dedupe agent skills
`.agents/skills/` and `.claude/skills/` hold the same 8 GSAP files. Symlink or pick one.

### 6.5 Lint rules
Flip `@typescript-eslint/no-explicit-any` from `'off'` to `'warn'` and burn down the 34 `: any`.
Add `no-console: 'warn'` (8 stray `console.log`). Consider a rule banning raw hex in templates
once 5.6 lands.

---

## Sequencing

```
TODAY   1.0.1 --workers=32  +  1.0.5 session/cache off SQLite   ← 10 min, do before next exam
Week 1  Phase 0 (factories + tests)  →  Phase 1.0 (async grading + queue worker)
Week 2  Phase 1 (security: answer-key leak, resubmit, timer)
Week 3  Phase 2 (settings cache)  +  Phase 6 (CI/hygiene)  +  5.1/5.3
Week 4-5 Phase 3 (flow untangling)                              ← unblocks route:cache
Week 6+ Phase 4 (layers)  ||  Phase 5 (frontend)                ← parallelisable
```

**If you do nothing else today:** add `--workers=32` to your `octane:start` command. It is a
one-flag change that would have prevented most of the lost submissions you have already suffered.

**Two separate emergencies, ranked:**
1. *Phase 1.0* — students lose completed exam work. Happening now, in class, and it destroys trust
   in the system fastest.
2. *Phase 1.1 + 1.3* — students can read every answer key from the exam list page and resubmit
   freely. More serious in absolute terms, but silent; nobody is complaining yet.

Everything after those is engineering debt.

---

## Open questions (need your call before I implement)

1. **Exam review visibility** — should students see correct answers immediately after submitting a
   part, or only once the exam is `closed`? Affects 1.1. Immediate reveal lets students in the same
   room share answers mid-exam.
2. **Late submissions** — hard-reject past the deadline, or accept and flag for the teacher?
   I recommend accept-and-flag. Affects 1.4.
3. **Per-exam vs per-part timer** — `exam_live_sessions` is uniquely keyed on `(user_id, exam_id)`,
   so today the clock is per-exam. Is that intended, or should each part have its own limit?
4. **Retakes** — is a single attempt per part correct, or do you want `max_attempts`? Affects 1.3.
5. **Accessibility obligations** — any WCAG/Section 508 requirement from the school? Would promote
   5.7 significantly.

Tell me the answers (or say "your judgement") and I'll start on Phase 0.
