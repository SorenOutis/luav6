# Analysis — Assign Assignments to Sections (instead of Courses)

**Goal:** In the admin panel (Filament → Learning → Assignments), an admin should create an
assignment and assign it to **Sections**, not Courses.

---

## 1. How assignments work today

### 1.1 Data model

`assignments` table (`database/migrations/2026_03_09_060457_create_assignments_table.php`
+ workspace backfill):

| column | notes |
|---|---|
| `id` | |
| `title` | required |
| `description` | nullable text |
| `due_date` | **`string`**, nullable (not a date/datetime column) |
| `course_id` | nullable FK → `courses`, `nullOnDelete()` |
| `workspace_id`, `admin_id` | multi-tenancy (added later) |

`App\Models\Assignment`:

```php
protected $fillable = ['title','description','due_date','course_id','workspace_id','admin_id'];
public function course()      { return $this->belongsTo(Course::class); }
public function users()       { return $this->belongsToMany(User::class)->withPivot(...); }
public function submissions() { return $this->hasMany(Submission::class); }
```

There is **no `section_id`** and **no `assignment_section`** table.

`assignment_user` pivot = per-student submission state
(`submitted`, `status`, `grade`, `file_path`, `submitted_at`, `points`, `xp_earned`,
`feedback`, `graded_at`, `graded_by`).

### 1.2 Admin panel (Filament v4)

`app/Filament/Resources/Assignments/`

- `Schemas/AssignmentForm.php` — `title`, `description`, `due_date` (plain `TextInput`),
  `Select::make('course_id')->relationship('course','name')->required()`.
- `Tables/AssignmentsTable.php` — shows `course.name` column.
- `AssignmentResource.php` — pages + `SubmissionsRelationManager`.

### 1.3 The important finding — `course_id` is decorative

**Nothing actually uses `course_id` for targeting.** The student-facing list is:

```php
// app/Http/Controllers/AssignmentController.php::index()
$assignments = Assignment::with(['course'])->get()   // ← every assignment in the workspace
```

So *every* student in the workspace sees *every* assignment; the course is only rendered as a
label ("Subject: …") and used for a client-side filter dropdown in
`resources/js/pages/Assignments.vue`. There is no code path that enrolls students into an
assignment — `assignment_user` rows are only created lazily on submit
(`syncWithoutDetaching` in `AssignmentController::store`).

Side effect of that: `DashboardController` uses `$user->assignments()` (pivot-driven), so the
dashboard shows an assignment **only after the student has already submitted it** — the
"upcoming assignments" list is effectively always empty. Switching to section targeting is the
natural moment to fix this.

The only place `course_id` is semantically used is the AI student tool
`app/Ai/Tools/AssignmentsTool.php`, which filters by the student's enrolled courses — i.e. the
AI answer and the actual Assignments page disagree with each other today.

### 1.4 There is already a proven "section targeting" pattern in this repo

`Exam` and `Announcement` both do exactly what you're asking for:

- `exams.section_id` nullable FK, `Exam::section()` belongsTo.
- Form: `Select::make('section_id')->relationship('section','name')` with helper text
  *"If selected, only students in this section can see and take this exam."*
- Visibility filter (`ExamController:120`, `UpcomingExamsService:32`):
  ```php
  $q->whereNull('section_id')->orWhereIn('section_id', $sectionIds);
  ```
  → `null` = visible to everyone, set = visible to that section only.
- Authorization guard (`ExamController:713`) re-checks membership server-side.

Students belong to sections via `User::sections()` (`section_user`, many-to-many with
`season_id`). So **one student can be in several sections** — targeting must be by set
intersection, not `users.section_id`.

---

## 2. Design decision: one section or many?

| | A. `assignments.section_id` (single, mirrors Exam) | B. `assignment_section` pivot (many) |
|---|---|---|
| Schema | 1 nullable FK column | new pivot table |
| Matches existing code | ✅ identical to Exam/Announcement | ➖ new pattern |
| "Same task for 3 sections" | must duplicate the assignment 3× | ✅ one record, multi-select |
| Query | `whereNull OR whereIn` | `whereDoesntHave OR whereHas(...whereIn)` |
| Filament form | `Select` | `Select…->multiple()->relationship('sections','name')` |
| Grading / submissions | unchanged | unchanged |

Your wording — *"assign it to the **sections** available"* — reads like **B (multi-select)**.
B is a superset of A and costs one extra migration; I'd recommend **B**, keeping `null`/empty =
"all sections" so existing rows keep working.

---

## 3. What has to change (Option B, multi-section)

### 3.1 Database
1. `create_assignment_section_table` — `assignment_id`, `section_id`, cascade deletes,
   unique(`assignment_id`,`section_id`).
2. *(optional data migration)* map existing `course_id` values → nothing; existing assignments
   simply become "all sections", which preserves today's behaviour exactly.
3. Decide on `course_id`: **keep the column nullable and make it optional in the UI**
   (safest — `AssignmentsTool`, the Vue course filter and the AI write-tool all read it), or
   drop it in a follow-up once those are migrated. Recommendation: keep, un-require.

### 3.2 Model — `app/Models/Assignment.php`
```php
public function sections() { return $this->belongsToMany(Section::class); }

public function scopeVisibleTo(Builder $q, Collection $sectionIds): Builder
{
    return $q->where(fn ($w) => $w
        ->whereDoesntHave('sections')
        ->orWhereHas('sections', fn ($s) => $s->whereIn('sections.id', $sectionIds)));
}
```
Also consider casting `due_date` to `datetime` (currently a string column — see §4).

### 3.3 Admin panel
- `Schemas/AssignmentForm.php`
  - `Select::make('sections')->relationship('sections','name')->multiple()->preload()->searchable()`
    with helper text "Leave empty to make this visible to every section."
  - `course_id` → `->required()` removed, placeholder "Optional".
  - `due_date` → `DateTimePicker` (consistent with `ExamForm`).
- `Tables/AssignmentsTable.php` — replace/augment `course.name` with
  `TextColumn::make('sections.name')->badge()->label('Sections')` + a
  `SelectFilter::make('sections')->relationship('sections','name')`.
- Workspace scoping: the `sections` relationship Select is automatically limited by the
  `BelongsToWorkspace` global scope on `Section`, same as `ExamForm` — no extra work, but worth
  a test.
- Nice-to-have: a "Sections" / "Assignments" relation manager on `SectionResource`.

### 3.4 Student side (this is where the real behaviour change lands)
- `AssignmentController::index()` — load `$sectionIds = $user->sections()->pluck('sections.id')`
  and apply `visibleTo($sectionIds)`; return `sections` in the payload.
- `AssignmentController::store()` — add a guard: reject a submission if the assignment is
  section-targeted and the student isn't in one of those sections (mirrors
  `ExamController:713`).
- `DashboardController` (~line 176) — switch the assignment list from `$user->assignments()`
  to the same `visibleTo()` query left-joined with the pivot, so upcoming assignments actually
  appear before submission.
- `resources/js/pages/Assignments.vue` — the `course` field is optional in the TS interface;
  either keep the course filter and add a section badge, or swap the filter dropdown to
  sections (lines ~48, 161, 437, 1102, 1660, 1693).

### 3.5 AI layer
- `app/Ai/Tools/AssignmentsTool.php` — replace the `whereIn('course_id', $courseIds)` filter
  with the section-based `visibleTo()` scope so the assistant matches the page.
- `app/Ai/Tools/CreateAssignmentTool.php` — swap the required `course_id` arg for an optional
  `section_ids` array; validate each section against `$this->workspaceId()`; update the
  approval-card diff rows.
- `app/Services/AiActionExecutor.php::prepareCreateAssignment()` — lock/validate sections
  instead of the course (`lockWorkspaceRecord(Section::class, …)`, as `prepareCreateExam`
  already does) and `sync()` them after create.

### 3.6 Analytics / widgets
`AdminAnalyticsOverview`, `AdminCommandCenterWidget`, `SectionComparisonWidget`,
`ActivityFeedWidget`, `AdminActivityTrendChart` all count rows in `assignment_user`
("assigned" = has a pivot row). With section targeting, "assigned" should mean "student is in a
targeted section", otherwise the submission-rate stat stays 100 % by construction. Either
recompute those denominators from sections, or materialise pivot rows on save (see §4).

### 3.7 Tests
`tests/Feature/AiChatStudentToolsTest.php`, `AiChatAdminToolsTest.php`,
`PagePerformanceTest.php` and `tests/js/assignments-ui.test.ts` build assignments with
`course_id` — they'll need updating. Add coverage for: student in section sees it, student in
another section does not, empty-sections assignment is visible to all, submission guard.

---

## 4. Decisions taken

| Question | Decision |
|---|---|
| One section or many? | **Many** — `assignment_section` pivot, multi-select in the form. |
| Keep `course_id`? | **Kept, but optional** — a label only; visibility is section-driven. |
| Empty sections means…? | **Visible to nobody** (unassigned). Existing assignments are backfilled to every section in their workspace so nothing disappears. |
| Materialise `assignment_user` rows? | **Yes** — a `Pending` row per targeted student, kept in sync when sections or section membership change. |
| Fix `due_date`? | **Yes** — migrated from `string` to a real `datetime`, form now uses a `DateTimePicker`. |

---

## 5. What was implemented

### Migrations (`database/migrations/`)
- `2026_08_20_000001_create_assignment_section_table.php` — the targeting pivot.
- `2026_08_20_000002_backfill_assignment_sections.php` — attaches every existing assignment to
  every section in its own workspace and materialises the roster, so behaviour is unchanged for
  existing data.
- `2026_08_20_000003_change_assignments_due_date_to_datetime.php` — `string` → `datetime`,
  parsing existing values and nulling unparseable ones.
- `2026_08_20_000004_add_unique_index_to_assignment_user_table.php` — dedupes, then makes
  `(assignment_id, user_id)` unique so the roster writes are race-safe.

### Model / service
- `Assignment::sections()`, `scopeVisibleToSections()`, `isVisibleTo()`, `due_date` datetime cast.
- `App\Services\AssignmentRosterService` — `syncAssignment()`, `syncNewMembership()`,
  `syncRemovedMembership()`. It only ever adds rows, and removes them only when the student is
  untargeted **and** has nothing to lose (no submission, file, grade, feedback, points or XP).
- `SectionUser` pivot events wire membership changes into the roster.

### Admin panel
- `AssignmentForm` — required multi-select **Assign to sections**, optional Course, and a
  `DateTimePicker` for the due date.
- `AssignmentsTable` — section badges, a student count, a Section filter and an
  "Unassigned only" filter.
- `CreateAssignment` / `EditAssignment` pages call the roster service after save.

### Student side
- `AssignmentController::index()` filters by the student's sections and returns section names.
- `AssignmentController::store()` returns 403 for an assignment the student was not given.
- `DashboardController` now lists assignments from section targeting, so upcoming work appears
  **before** submission instead of only after it.
- `Assignments.vue` — `sections` on the payload, section pills on each card, section names
  included in search.

### AI layer
- `AssignmentsTool` filters by section instead of course.
- `CreateAssignmentTool` requires `section_ids` (comma-separated), course is optional.
- `AiActionExecutor::prepareCreateAssignment()` locks and verifies each section, syncs the
  pivot and the roster.

### Analytics
No widget changes were needed: because the roster is materialised, the existing
`assignment_user` denominators in `AdminAnalyticsOverview`, `AdminCommandCenterWidget` and
`SectionComparisonWidget` now mean "students who were actually assigned this", so the
submission-rate stats become meaningful instead of always 100%.

### Tests
- `tests/Feature/AssignmentSectionTargetingTest.php` — visibility, multi-section, unassigned,
  403 on foreign-section submission, roster creation, late joiners, and that submitted work
  survives untargeting.
- Updated `AiChatStudentToolsTest`, `AiChatAdminToolsTest` and `tests/js/assignments-ui.test.ts`.

---

## 6. Not run in this sandbox

There is no PHP runtime here, so the migrations, Pint and Pest were **not** executed. Please run
locally before merging:

```bash
php artisan migrate
vendor/bin/pint
php artisan test
npm run test:js   # already run here: passing
```

The JS suite and Prettier were run in the sandbox and pass; `vue-tsc` reports only the
pre-existing "Cannot find module '@/routes'" errors from the ungenerated Wayfinder routes.
