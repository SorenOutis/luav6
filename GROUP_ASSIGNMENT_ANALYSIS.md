# Analysis — Group Assignments (Student-Formed Groups with Shared Submission)

**Goal:** On the student Assignments page, students form a **group per assignment** (a
"group activity"). Any member can upload the group's file; every member sees that file on
their assignment page. The teacher grades the group **once** and the grade, points, and XP
apply to **all members** automatically.

**Decisions confirmed with the user:**

| Question | Decision |
|---|---|
| Group scope | Per assignment — each assignment has its own member list |
| Grading | One group grade — same grade/points/XP/feedback applied to every member |
| Adding members after submit | Allowed until the assignment is **graded**; late joiners instantly see the uploaded file; any member can resubmit (replaces the file for everyone) |
| Membership management | Only the group **creator** adds/removes; other members can leave on their own |

---

## 1. How assignments work today

### 1.1 Data model

- `assignments` — title, description, due_date, course_id (decorative), workspace_id, admin_id.
- `assignment_section` — pivot: which sections an assignment is targeted at.
- `assignment_user` — **one row per (assignment, user)** — the per-student submission state:
  `submitted`, `status` (`Pending`/`Submitted`/`Graded`), `grade`, `file_path`, `submitted_at`,
  `points`, `xp_earned`, `feedback`, `graded_at`, `graded_by`. Unique on `(assignment_id, user_id)`.
- `AssignmentRosterService` materialises a `Pending` row for every targeted student and keeps
  that roster in sync when section targeting or membership changes.

### 1.2 Student flow (today)

- `AssignmentController::index()` → `resources/js/pages/Assignments.vue`
  - Lists assignments visible to the student's sections; each assignment carries the
    student's **own** `submission` (pivot row).
  - Card shows status badge, due info; when submitted: "View grade" expand → file preview,
    points/XP pills, teacher feedback, preview/download buttons; "Resubmit" button (disabled once graded).
  - Upload modal → `POST /assignments/{id}/submit` (`AssignmentController::store`) → validates
    file + section visibility → stores file → `syncWithoutDetaching` updates **only the uploader's row**.

### 1.3 Admin flow (today)

- Filament `Assignments` resource → `SubmissionsRelationManager` lists one row per student
  (`assignment_user`). Grading a row sets status/grade/points/XP/feedback; the
  `Submission` model's `updated` hook awards points/XP to that student's section/season
  progress and sends a "graded" notification. **This machinery is the most valuable thing to reuse.**

### 1.4 Why this feature is a natural fit

`assignment_user` already has every field we need for a shared submission
(file, status, grade, points, XP, feedback). The unique `(assignment_id, user_id)` index means
a student can only ever have **one** row per assignment — i.e., can only be in **one** group
per assignment — with zero extra enforcement. We only need:

1. a small `assignment_groups` table for group identity + creator,
2. a nullable `group_id` on `assignment_user` linking each member's row to the group,
3. a service that propagates submit/grade changes across the group's rows,
4. UI on the Assignments page to manage members.

---

## 2. Proposed design

### 2.1 Data model

**New table `assignment_groups`** (group identity + creator only — the submission itself
stays on the mirrored `assignment_user` rows):

```php
Schema::create('assignment_groups', function (Blueprint $table) {
    $table->id();
    $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
    $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
    $table->foreignId('workspace_id')->nullable()->constrained()->nullOnDelete(); // tenancy, like Assignment/Section
    $table->timestamps();

    $table->index('assignment_id');
});
```

**New migration on `assignment_user`:**

```php
Schema::table('assignment_user', function (Blueprint $table) {
    $table->foreignId('group_id')->nullable()->after('assignment_id')
        ->constrained('assignment_groups')->nullOnDelete();
    $table->foreignId('submitted_by')->nullable()->after('submitted_at')
        ->constrained('users')->nullOnDelete(); // who last uploaded the shared file ("Submitted by Mina")
    $table->index('group_id');
});
```

**Why mirror on `assignment_user` instead of storing the file once on the group?**

| | Mirrored per-member rows (chosen) | File/grade only on `assignment_groups` |
|---|---|---|
| Student page file display | Works **unchanged** — each member's row already drives the card | Every read path in Assignments.vue must be re-pointed at the group |
| Admin grading | Existing SubmissionsRelationManager + XP/points/notification hooks fire per member for free | New "grade group" admin UI + a new award path for N members |
| Late joiner | Copy file fields onto the new member's row — done | Extra lookup everywhere |
| Cost | Write-side duplication (acceptable: one row per member, small files metadata) | Cleaner write-side, bigger read-side refactor |

Since the user chose **one grade for everyone**, propagation on grade is exactly the
"duplication" — the `Submission::updated` hook already awards points/XP per row, so each
member automatically gets their share through the existing, tested path.

### 2.2 Model layer

- `App\Models\AssignmentGroup` (new) — `BelongsToWorkspace`, `assignment()`, `creator()`,
  `members(): HasMany` → `Submission::where('group_id', ...)` (Submission already maps to
  `assignment_user`).
- `App\Models\Assignment` — add `groups(): HasMany(AssignmentGroup::class)`.
- `App\Models\Submission` — add `group(): BelongsTo(AssignmentGroup::class, 'group_id')`;
  extend the booted `updated` hook: **when a row with `group_id` becomes Graded (status/grade/
  points/xp/feedback changed), copy those values to the sibling group rows** (skip self).
  Identical-value writes are no-ops for Eloquent, so propagation is naturally idempotent and
  cannot loop. Editing any member's row re-grades the whole group — that *is* the "one group
  grade" semantics; individual overrides aren't a thing for group rows (documented behavior).

### 2.3 Service — `App\Services\AssignmentGroupService`

Mirrors the style of `AssignmentRosterService` (small, explicit methods, bulk-safe):

| Method | Behaviour |
|---|---|
| `createGroup(Assignment, User)` | Validates the user can see the assignment; creates group; sets `group_id` on the creator's roster row (inserting a `Pending` row first if missing). Returns the group. |
| `addMember(Group, User, actor)` | Actor must be the creator. Assignment must **not** be Graded. Member must be able to see the assignment (in a targeted section). Member must not already have a `group_id` (422 "already in a group"). Sets `group_id`; if the group already submitted, **mirrors `file_path`/`submitted`/`status`/`submitted_at`** onto the new member's row so they see the file instantly. Sends the member a notification. |
| `removeMember(Group, User, actor)` | Actor = creator (any member) or the member themselves (leave). Assignment must not be Graded. Resets the member's row to `Pending` (`submitted=false, file_path=null, submitted_at=null, group_id=null`). If the leaving user was the creator, **creator role transfers** to the earliest-joined remaining member; if no members remain, the group record is deleted. The shared file survives on the remaining rows (they are mirrored copies). |
| `submitFile(Assignment, User, path)` | Called by `AssignmentController::store` after storing the upload. Updates the uploader's row as today, then **propagates** `file_path, submitted=true, status='Submitted', submitted_at=now, submitted_by=uploader` to all sibling rows. Clears stale grade fields only if somehow present (can't happen while "not graded" is enforced). Optionally notifies members "X submitted the group file". |
| `applyGradeToGroup(Submission)` | Internal helper invoked by the model hook (see 2.2) — copies grade/points/xp/feedback/status/graded_at/graded_by to sibling rows. |
| `candidates(Assignment, User, ?query)` | Students in the assignment's targeted sections (same workspace), minus self, minus anyone whose `assignment_user.group_id` is already set; name/email search; returns `id, name, avatar, section names`, limited (~20). |

**Rules enforced in one place:**

- Group is locked (no add/remove/resubmit) once the assignment's group submission is **Graded** — enforced in the service, and the UI disables the buttons.
- Membership is restricted to students who can see the assignment (same-section classmates in the common case; if the assignment targets several sections, members of those sections can group together).
- A user can belong to at most one group per assignment — guaranteed by the existing `(assignment_id, user_id)` unique index.

### 2.4 Routes & controller

```php
// routes/web.php (student group, inside the same student middleware as existing routes)
Route::get('assignments/{assignment}/groups/candidates',    [AssignmentGroupController::class, 'candidates'])->name('assignments.groups.candidates');
Route::post('assignments/{assignment}/groups',              [AssignmentGroupController::class, 'store'])->name('assignments.groups.store');
Route::post('assignments/{assignment}/groups/members',      [AssignmentGroupController::class, 'addMember'])->name('assignments.groups.members.store');
Route::delete('assignments/{assignment}/groups/members/{user}', [AssignmentGroupController::class, 'removeMember'])->name('assignments.groups.members.destroy');
```

- New `AssignmentGroupController` — thin validation + visibility checks, delegates to the service. JSON responses for the candidate search, Inertia redirect back for mutations (consistent with `store()`).
- `AssignmentController::index()` — for each assignment, attach `group: { id, created_by, members: [{id, name, avatar}] } | null` (the user's group) and `submitted_by` (uploader name) inside the existing `submission` payload.
- `AssignmentController::store()` — after storing the file: if the user has a group for this assignment → `AssignmentGroupService::submitFile()` (propagates); else → existing single-row update. Add a backend guard: reject when the existing submission is already Graded (today only the frontend disables Resubmit).

### 2.5 Frontend — `resources/js/pages/Assignments.vue`

Adds a **"Group" section to each assignment card** (only while the assignment is not graded):

- Member chips (avatar + name) with a small "Group" label; creator sees an **× remove** on each member and a **+ Add member** button; non-creator members see a **Leave** button.
- **Add-member modal**: search box → results from `GET .../groups/candidates?q=...` (axios is already a dependency) → click to add → Inertia reload updates the card + group payload.
- Upload modal: when the assignment already has a group, show the member list and a note — *"This file will be shared with everyone in your group"*.
- Submitted card: under the file, add **"Submitted by {name}"** (from `submitted_by`) so group members know who uploaded.
- No changes needed to the stats/tabs — each member's mirrored row already makes "Submitted" and "Graded" counts truthful per student.

### 2.6 Admin — Filament `SubmissionsRelationManager`

Minimal changes:

- New column **"Group"** — badge with the group id (or member count) when `group_id` is set, so the teacher sees the rows are linked.
- Helper text on the status field: *"This student is in a group — grading this row applies the grade, points, and XP to all group members."*
- Everything else (file preview, per-member row grading) works as-is because the group file is mirrored on every row.

---

## 3. Edge cases & how they're handled

| Case | Handling |
|---|---|
| Member added **after** submission | Service mirrors the current file onto the new member's row → they see it immediately (confirmed requirement) |
| Member **removed / leaves** | Their row is reset to `Pending` (no file). Remaining members keep their mirrored copies; the group file survives |
| Creator leaves | Creator role transfers to the earliest-joined remaining member; empty group is deleted |
| Resubmit by a non-uploader member | Any member can resubmit; the new file replaces the file on **all** group rows (`submitted_by` updates) |
| Student in two sections both targeted by the same assignment | One `assignment_user` row (unique index) → one group per assignment, no conflict |
| Student leaves the section (roster prune) | `pruneUntouchedRows` never deletes rows with a file/grade → their work and group membership are preserved |
| Grading while propagation runs | Propagation writes identical values to sibling rows → no `updated` event fires → no loop; each member's points/XP/notification is awarded exactly once via the existing hook |
| Group for an assignment that gets un-targeted from the section | Group stays consistent because roster rows are untouched when the student has work; admin sees rows as today |
| Cross-section grouping | Allowed only between students who can both see the assignment (assignment targeted at a section each belongs to) |

## 4. Implementation checklist

1. Migrations: `assignment_groups` table; `group_id` + `submitted_by` on `assignment_user`.
2. `AssignmentGroup` model; `groups()` on `Assignment`; `group()` + grade-propagation hook on `Submission`.
3. `AssignmentGroupService` (create/add/remove/leave/submit/candidates/grade propagation).
4. `AssignmentGroupController` + 4 routes; extend `AssignmentController` (group payload in `index()`, propagation in `store()`, graded-resubmit guard).
5. `Assignments.vue` — group section on cards, add-member modal (candidate search), leave/remove, "Submitted by", upload-modal note.
6. Filament `SubmissionsRelationManager` — Group column + helper text.
7. Tests — `tests/Feature/AssignmentGroupTest.php` (section 5).

## 5. Test plan (Pest, mirroring `AssignmentSectionTargetingTest` style)

1. Creator forms a group and adds a classmate from the targeted section.
2. Adding a student from a non-targeted section → rejected.
3. A student who is already in a group for the same assignment cannot be added again → 422.
4. Non-creator cannot add/remove members → 403.
5. After the uploader submits, **both** members' rows carry the file and both see `file_url` on the Assignments page.
6. A member added after submission immediately sees the shared file.
7. Removing a member resets their row to `Pending` and their page no longer shows the file.
8. Creator leaving transfers the creator role to a remaining member.
9. Grading one member's row propagates grade/points/XP/feedback to all members and each member receives the points/XP (assert section progress deltas).
10. A graded group rejects add/remove/resubmit.
11. Candidate search excludes self, already-grouped students, and out-of-section students.
