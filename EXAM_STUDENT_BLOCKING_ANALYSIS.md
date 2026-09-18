# Analysis — Block Individual Students From an Exam

**Goal:** From the admin panel (Filament → Learning → Exams), a teacher should be able to block
specific students from an exam so that those students **cannot see it at all** — not while it is
published, not while it is scheduled/upcoming, not while it is open, and not after it closes.

---

## 1. How exam visibility works today

### 1.1 Targeting is section-level only

`exams` table (`2026_03_10_015305_create_exams_table.php` + `2026_04_12_053816_add_section_id_to_exams_table.php`
+ `2026_09_01_000001_add_schedule_to_exams_table.php`):

| column | notes |
|---|---|
| `section_id` | nullable FK → `sections`. **The only targeting column.** `null` = every student |
| `status` | `draft` / `published` / `closed` |
| `starts_at`, `ends_at` | nullable schedule window (newest migration) |
| `exam_date` | legacy display date, kept in sync with `starts_at` by the form hooks |
| `workspace_id`, `admin_id` | multi-tenancy |

There is **no per-student exam table**. The only per-student exam rows that exist are:

- `exam_set_assignments` (`2026_08_28_000003`, unique `exam_id + user_id`) — which *set* the student was dealt;
- `exam_submissions` — what they answered;
- `exam_answer_drafts`, `exam_live_sessions`, `exam_xp_awards`.

None of them is an authorisation table. Grepping the whole of `app/` and `database/migrations` for
`block`, `banned`, `suspend`, `blacklist`, `excluded` returns only AI-chat log keys
(`ai_chat.request.blocked`) and prompt text — **there is no blocking concept anywhere in the app
today.**

### 1.2 The visibility scope

`App\Models\Exam::scopeVisibleTo()` (`app/Models/Exam.php:204`):

```php
if ($user->is_admin) return $query;              // admins see everything
// students: exams whose section_id is one of their sections,
// or section_id IS NULL and workspace is theirs / null
```

It reads `section_user` through `DB::table()` on purpose and bypasses the `workspace` global scope —
the docblock at `Exam.php:186-203` explains that workspace bookkeeping can lag behind enrollment, so
`section_user` is the source of truth. **Any blocking check must follow the same rule** (query the
pivot with `DB::table()`, never through a workspace-scoped model), or blocked students will reappear
whenever the two drift apart.

### 1.3 Status vs. schedule are *not* visibility filters

This is the key structural fact for the feature:

- `Exam::hasStarted()` / `hasEnded()` / `acceptsSubmissions()` / `scheduleState()` /
  `isEffectivelyClosed()` (`Exam.php:75-146`) drive **labels and submission acceptance**, not
  visibility.
- An **upcoming** exam (published, `starts_at` in the future) is *fully visible* — the card shows
  "Starts …" (`is_upcoming` in both listing mappers).
- A **closed / ended** exam stays visible so the student can review (`results_available`).
- Only `draft` is filtered out of the list (`->where('status','!=','draft')`).

So "blocked" has to be a **third, independent axis**: `status` (teacher lifecycle) ×
`schedule` (window) × **`blocked` (per-student deny list)**. Blocked means invisible in *every*
state, including states where the exam is otherwise deliberately visible.

### 1.4 Every place a student can encounter an exam

Seven distinct query paths. **Four of them do not use `scopeVisibleTo()`** — this is the main risk
in the feature, because a block added only to `scopeVisibleTo` would still leak the exam through the
dashboard, the calendar and the AI assistant.

| # | Surface | Location | Uses `visibleTo()`? |
|---|---|---|---|
| 1 | Legacy list `/exams` + `/api/exams` | `ExamController::examPage()` `:124`, scope at `:135` | ✅ |
| 2 | Activities hub `/activities` + `/api/activities` (grid **and** the overview tiles / section tabs) | `ActivityHubController::visibleExams()` `:219-222`, used by `examPage()` + `hubSummary()` | ✅ |
| 3 | Direct access + every write action (`show`, `review`, `startPart`, `saveAnswers`, `submitPart`, `partStatus`, `monitorProgress`) | `ExamController::assertCanAccess()` `:867` | ❌ separate check |
| 4 | Dashboard "Upcoming exams" (`DashboardController:206`) + `/api/dashboard-exams` | `UpcomingExamsService::forUser()` `:35` | ❌ own section filter |
| 5 | Calendar grid | `CalendarEventService::examEvents()` `:69` | ❌ own section filter |
| 6 | AI chat "what are my upcoming exams?" | `App\Ai\Tools\UpcomingExamsTool:34` | ❌ own section filter |
| 7 | Route-model binding for `/exams/{exam}` | `Exam::resolveRouteBinding()` `:241` | ❌ resolves across tenants on purpose; enforcement is per-action in #3 |

`assertCanAccess()` (`ExamController.php:867`) today only checks section enrollment, and **returns
early for exams with no `section_id`**:

```php
if ($user->is_admin || ! $exam->section_id) {
    return;                      // ← global exams are unchecked
}
```

so a block check inserted naively inside the enrollment branch would never run for a global exam.
It has to run **before** that early return.

### 1.5 Set dealing is a side effect of *seeing* the exam

`ExamController::show()` calls `ExamSetAssignmentService::resolveSet()` (`:239`) which **writes** an
`exam_set_assignments` row on first open (`resolve()` → `assignNext()` → `persist()`,
service `:360/:412/:562`). The service docblock (`:26-37`) promises a section is *split evenly*
across sets. If a blocked student can still resolve a set (e.g. via a direct URL, or because a
surface was missed), they consume a deck slot and skew the distribution for their classmates.

The listings deliberately avoid this by using `summariesFor()` / `assignedSet()` — "merely browsing
cannot consume a deal slot" (`service:200-204`). **The block must be enforced before `resolveSet()`
in `show()` and `review()`** (`:239` and `:101-102`).

### 1.6 Admin side (Filament v5.7.6 — `composer.lock`)

`app/Filament/Resources/Exams/`

- `Schemas/ExamForm.php::configure()` — `title`, `section_id` (`:70`), `description`,
  `starts_at` (`:79`), `ends_at` (`:88`), `duration_minutes`, `status` (`:102`), `url`,
  `Section::make('XP Rewards')` (`:115`), `Section::make('Exam Sets')` (`:146`).
- `Pages/CreateExam.php::mutateFormDataBeforeCreate()` — already strips the form-only
  `sets_count` field and mirrors `starts_at` → `exam_date`. The natural place to sync a block list
  on create.
- `Pages/EditExam.php::mutateFormDataBeforeSave()` — same for edit; also hosts the header actions
  (answer report, reshuffle sets, export).
- `Tables/ExamsTable.php::configure()` — columns `title`, `section.name`, `starts_at`, `ends_at`,
  `duration_minutes`, `sets_count` (`->counts('sets')`), `status` badge.
- **Precedent for a student multi-select already exists**: `EditExam.php:325`
  `CheckboxList::make('student_ids')` in the answer-report action — but its options come from
  `ExamAnswerReportService::studentOptions()`, i.e. *students who submitted that set*, which is the
  wrong population for blocking. A block picker needs **section enrollees**
  (`Section::users()`, `app/Models/Section.php:96`) or, for a global exam, all students.

Note also `EnsureStudentPageIsAvailable` (`app/Http/Middleware/EnsureStudentPageIsAvailable.php`) —
that is a **workspace-wide** page switch (`student.page:exams`), not per-student, so it is not the
right hook here.

---

## 2. Design options

### Option A — per-exam deny list (recommended)

New pivot `exam_user_blocks (exam_id, user_id, …)`. "Everyone in the section **except** these
students."

- ✅ Matches how exams are targeted today (whole section). Nothing to backfill.
- ✅ A student who joins the section **after** the exam was created still gets it — no silent
  exclusion.
- ✅ Cheap to reason about, cheap to query (one indexed `whereNotIn` subselect).
- ❌ Not usable for "only these 5 students may take this exam".

### Option B — per-exam allow list (`exam_user_targets`)

"Only these students see it."

- ❌ Requires backfilling every existing exam with its current enrollees.
- ❌ Breaks the moment a student joins the section mid-window (they lose the exam unless someone
  remembers to re-sync) — this is the same trap the `section_user` docblock at `Exam.php:186-203`
  was written around.
- ✅ Right answer if the real requirement is small private make-up exams.

### Option C — account-level flag (`users.exams_blocked`)

Blocks a student from *every* exam.

- ✅ One-line enforcement, one toggle.
- ❌ Not what was asked (per-exam), and it is a disciplinary switch, not an exam setting.

**Recommendation: Option A.** If make-up exams are also wanted later, add Option B as a separate
`target_mode` on the exam rather than overloading the block list.

---

## 3. Implementation plan (Option A)

### 3.1 Schema

`database/migrations/2026_09_02_000001_create_exam_user_blocks_table.php`

```php
Schema::create('exam_user_blocks', function (Blueprint $table) {
    $table->id();
    $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->foreignId('blocked_by')->nullable()->constrained('users')->nullOnDelete();
    $table->string('reason')->nullable();      // optional, shown in the admin table only
    $table->timestamps();

    $table->unique(['exam_id', 'user_id']);   // idempotent sync
    $table->index('user_id');                 // the listing subselect
});
```

The `user_id` index matters: the hot path is
`whereNotIn('exams.id', select exam_id from exam_user_blocks where user_id = ?)`.

### 3.2 Model + relations

`app/Models/ExamUserBlock.php` (plain model, like `ExamSetAssignment`), plus on `Exam`:

```php
public function blocks(): HasMany            { return $this->hasMany(ExamUserBlock::class); }
public function blockedUsers(): BelongsToMany { return $this->belongsToMany(User::class, 'exam_user_blocks')->withTimestamps(); }
```

### 3.3 The scope — one place, applied everywhere

Add to `Exam`:

```php
/** Exams this student has been blocked from, whatever the status or schedule. */
public function scopeNotBlockedBy(Builder $query, User $user): Builder
{
    if ($user->is_admin) {
        return $query;
    }

    return $query->whereNotIn(
        $query->qualifyColumn('id'),
        DB::table('exam_user_blocks')->where('user_id', $user->id)->select('exam_id'),
    );
}
```

`DB::table()` on purpose — the pivot has no `workspace_id`, and going through a model would inherit
tenant scoping that `scopeVisibleTo()` explicitly avoids.

Then:

1. `Exam::scopeVisibleTo()` — chain `->notBlockedBy($user)` (covers surfaces #1 and #2, including
   the hub tiles and section-tab counts).
2. `ExamController::assertCanAccess()` — add the block check **above** the `! $exam->section_id`
   early return; return 404 (not 403) so a blocked student cannot even confirm the exam exists —
   matching how `show()` already 404s a draft.
3. `UpcomingExamsService::forUser()` (`:35`) — add the exclusion.
4. `CalendarEventService::examEvents()` (`:69`) — add the exclusion.
5. `UpcomingExamsTool` (`:34`) — add the exclusion.

Surfaces 3–5 currently hand-roll their own section filter; the cheapest correct move is to add the
one scope call to each. The more durable move (worth doing while in there) is to have them call
`visibleTo($user)` too — `ActivityHubController:213-216` already documents the drift this causes.

### 3.4 Ordering inside `show()` / `review()`

`assertCanAccess()` is already the first statement of both (`:75`, `:225`), so fixing it also
prevents a blocked student from consuming a set slot at `:239` / `:101-102`. Verify with a test that
no `exam_set_assignments` row is written.

### 3.5 Admin form

In `ExamForm::configure()`, a new section next to `Exam Sets`:

```php
Section::make('Blocked Students')
    ->description('These students cannot see or open this exam in any state — draft, upcoming, open or closed.')
    ->schema([
        Select::make('blocked_user_ids')
            ->label('Blocked students')
            ->multiple()
            ->searchable()
            ->options(fn (Get $get) => ExamBlockService::optionsFor($get('section_id')))
            ->preload(false)      // a workspace can hold thousands of students
            ->dehydrated(false)   // synced manually in the page hooks
            ->columnSpanFull(),
    ]),
```

Options source: students of the selected section (`Section::users()`, `Section.php:96`) when
`section_id` is set, otherwise every student in the workspace. `->live()` on `section_id` if the
list should re-scope on the fly.

Sync in **both** page hooks, so create works too (a `->relationship()` field would need the record
to exist):

```php
// CreateExam::mutateFormDataBeforeCreate() / EditExam::mutateFormDataBeforeSave()
$data = ExamForm::syncSetCount($data);
$blockedIds = array_map('intval', $data['blocked_user_ids'] ?? []);
unset($data['blocked_user_ids']);
// after create/save: $record->blockedUsers()->sync($blockedIds);
```

On create the sync has to happen in `afterCreate()` (the record has no id inside
`mutateFormDataBeforeCreate`).

`Tables/ExamsTable.php` — add a badge so a blocked exam is visible at a glance:

```php
TextColumn::make('blocked_users_count')
    ->label('Blocked')
    ->counts('blockedUsers')
    ->badge()
    ->visible(fn ($state): bool => $state > 0)
    ->tooltip('Students who cannot see this exam'),
```

### 3.6 Front-end

No Vue changes are required: the cards simply never arrive. `resources/js/pages/Activities/Index.vue`,
`Exam.vue` and `Exams/Show.vue` all render from the server payload.

### 3.7 Edge cases to decide

| Case | Recommendation |
|---|---|
| Student blocked **after** submitting | Keep `exam_submissions`, `exam_xp_awards`, drafts. Teacher reports (`ExamAnswerReportService`, `ExamSubmissionsTable`) stay intact — they do not go through `visibleTo()`. |
| Student loses their own review | `review()` is behind `assertCanAccess()`, so yes: blocking also hides their results. That is the intended meaning of "cannot see the exam". |
| Existing `exam_set_assignments` row | Leave it. Harmless, and `redealUnstarted()` (`service:333`) only touches students who never started. |
| Blocking a student not in the section | Allow it (no-op today, but correct if the exam is later re-targeted to "All sections"). |
| Admins / super-admins | Exempt, same as `scopeVisibleTo()`. |
| Admin impersonating a student (`ImpersonateUserTest`) | Should see the student view — `assertCanAccess()` reads `auth()->user()`, so it follows the impersonated identity automatically. |
| Caching | `exam_structure_{examId}` (`service:275`) is per-exam and holds only parts — **not** user-scoped, so no invalidation needed. `summariesFor()` does not cache. |
| Unblocking mid-exam | Student sees the exam again immediately; if they were never dealt a set they join the deck normally. |

---

## 4. Tests

New `tests/Feature/Exams/ExamBlockingTest.php`, following the setup helpers already in
`tests/Feature/Exams/ExamSetsTest.php` (`examSetsContext()`, `examSetsStudent()` — Pest style,
`Season::factory()->active()`, `Section::factory()->forSeason()`, `Exam::factory()->published()->forSection()->withSets()`).

1. Blocked student: `/activities` and `/api/activities` omit the exam; the hub tiles and section
   tabs count it out.
2. Blocked student: `/api/exams` omits it.
3. Blocked student: `GET /exams/{exam}` → 404, and **no** `exam_set_assignments` row was created.
4. Blocked student: `start` / `saveAnswers` / `submitPart` → 404 (or the chosen code), nothing
   written.
5. Blocked student: dashboard `upcomingExams` and `/api/dashboard-exams` omit it; the calendar
   window omits it.
6. Blocked student: the exam is hidden in **all** states — upcoming (`starts_at` future), open,
   ended, and `status = closed`.
7. Non-blocked classmate in the same section is unaffected.
8. Admin still sees it in the list and can still open the answer report.
9. Blocking survives a status change (published → closed → published) and a schedule change.
10. Unblocking restores visibility.

---

## 5. Scope summary

| Area | Files |
|---|---|
| Migration | `database/migrations/2026_09_02_000001_create_exam_user_blocks_table.php` (new) |
| Model | `app/Models/ExamUserBlock.php` (new), `app/Models/Exam.php` |
| Enforcement | `app/Http/Controllers/ExamController.php`, `app/Http/Controllers/ActivityHubController.php` (via scope), `app/Services/UpcomingExamsService.php`, `app/Services/CalendarEventService.php`, `app/Ai/Tools/UpcomingExamsTool.php` |
| Admin UI | `app/Filament/Resources/Exams/Schemas/ExamForm.php`, `Pages/CreateExam.php`, `Pages/EditExam.php`, `Tables/ExamsTable.php` |
| Tests | `tests/Feature/Exams/ExamBlockingTest.php` (new) |
| Front-end | none |

No changes to `exam_submissions`, XP awards, or the answer-report pipeline.

---

## 6. What shipped (Option A, block list, "nothing kept visible")

Decisions taken while implementing, in the order they were forced:

1. **The picker is a form-only field**, not `->relationship()`. A relationship select would list
   every user (admins included) and cannot be scoped to the chosen section. The value is stripped
   from the payload by `ExamForm::extractBlockedUserIds()` — the same pattern the form already uses
   for `sets_count` — and written by `ExamBlockService::sync()` from `afterCreate()` / `afterSave()`.
2. **`extractBlockedUserIds()` returns `null` when the key is absent, and `[]` when it is present but
   empty.** That distinction is load-bearing: `EditRecord::saveFormComponentOnly()` (Filament
   v5.7.6, `packages/panels/src/Resources/Pages/EditRecord.php:205`) also calls
   `mutateFormDataBeforeSave()` + the `afterSave` hook, but with **only the one component's state**.
   Without the `null` case, saving any single field on the edit page would have wiped the whole
   block list. An empty picker still means "block nobody".
3. **`blocked_by` is stamped only on rows that have never been attributed.** Laravel's
   `sync([id => attributes])` pushes attributes through `updateExistingPivot()`
   (`InteractsWithPivotTable::attachNew()`, line 195), so stamping inside `sync()` would rewrite the
   original blocker every time anyone re-saved the form. The service syncs plain ids, then updates
   only `whereNull('blocked_by')`.
4. **404, not 403** for a blocked student, matching how `show()` already 404s a draft — the exam
   does not exist for them, and blocking cannot be used to enumerate a section's exams.

## 7. Verification status

This sandbox has no `vendor/` directory and no network route to packagist, so **the Pest suite could
not be executed here.** Run it with:

```bash
php artisan test tests/Feature/Exams/ExamBlockingTest.php
# or the whole exam suite:
php artisan test tests/Feature/Exams
```

What *was* checked mechanically in this environment:

- `php -l` (real PHP 8.5.8 binary) over all 13 changed/created files — clean.
- The shipped `ExamForm::extractBlockedUserIds()` was loaded and executed directly: 7 assertions
  covering the absent-key, string/duplicate/zero-id, empty-picker and null-state cases.
- Every Filament API used (`Select::multiple/options/searchable/preload/default`, `Section::description`,
  `TextColumn::counts/badge/color/formatStateUsing`, `mutateFormDataBeforeCreate/Save`, the
  `afterCreate`/`afterSave` hooks, and the blank-state → `getDefaultState()` fallback that makes the
  `default()` closure hydrate the picker on edit) verified against the Filament v5.7.6 source.
- Laravel-side behaviour verified against the framework source: `BelongsToMany::sync()` pivot
  handling (which is what exposed finding 3 above), `Conditionable::when()` calling the `$default`
  branch for a null section, `whereNotIn()` accepting a query builder subselect, and
  `Builder::qualifyColumn()`.

Untested until the suite runs: the HTTP-level assertions (404s, listing payloads, dashboard and
calendar omissions) and the Filament page hooks.
