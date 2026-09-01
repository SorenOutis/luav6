# Exam Sets: why everyone ends up on Set A

**Question:** in the admin panel an exam can ship as several sets (Set A, Set B, …),
but in practice only the *first* set is handed out to students. Why, and what has to
change so the deal really depends on the sets that are available?

**Short answer:** the shuffled deal *is* implemented (`ExamSetAssignmentService::dealOrder()`
+ `assignNext()`), and it is covered by tests — so the feature is not "missing". What is
broken is *when* and *from what* the deal is made. There are five defects, and the first
two are almost certainly what you are seeing in production. Every one of them fails in the
same direction: **fall back to `sets->first()`**, i.e. Set A.

---

## How the deal works today

| Step | Code |
| --- | --- |
| Student opens `/exams/{exam}` | `ExamController::show()` → `resolveSet()` (`ExamController.php:230`) |
| No assignment row yet? | `ExamSetAssignmentService::assignNext()` (`:308`) |
| Deck order | `dealOrder()` — Fisher–Yates seeded by `crc32("section:{id}:{exam}")` (`:231`) |
| Slot picked | `count(exam_set_assignments for this exam) % deck size` (`:331-338`) |
| Row written | `exam_set_assignments` (unique on `exam_id,user_id`), **never rotated afterwards** |
| Listings (`/exams`, hub, dashboard) | `summariesFor()` — read-only, deliberately does **not** assign |

So the *N*th student to **open the exam page** draws the *N*th slot of the shuffled deck.

---

## Defect 1 — the set is locked in when a student *peeks*, not when they *start* (most likely cause)

`resolveSet()` is called from `ExamController::show()`, and `show()` is reachable as soon as
the exam leaves `draft`. A student who merely taps the exam card days before the exam gets a
permanent `exam_set_assignments` row. Nothing ever re-deals it: `ExamSet::booted()` only
clears the structure cache when a set is created/deleted, and there is no admin action to
reset assignments.

The normal admin workflow therefore poisons itself:

1. Exam is created/published with the default **1 set** (the migration also backfills a single
   "Set A" onto every pre-existing exam).
2. Students browse the hub and open the exam → **the whole class is pinned to Set A** while
   only Set A exists (`dealOrder()` with one item deals that one item).
3. Later the teacher raises "Number of sets" to 3 and builds Set B / Set C.
4. Every existing row is sticky, so nobody is ever moved. New sets are only reachable by
   students who never opened the exam before — usually nobody.

`deriveFromExistingWork()` (`:374`) hard-pins the same way for any student who already has a
submission/draft/live-session on an old single-set exam.

**Check it on your data — if `first_assignment` is earlier than `last_set_created`, this is your bug:**

```sql
SELECT e.id, e.title,
       (SELECT COUNT(*) FROM exam_sets s WHERE s.exam_id = e.id)               AS sets,
       (SELECT MAX(s.created_at) FROM exam_sets s WHERE s.exam_id = e.id)      AS last_set_created,
       (SELECT MIN(a.created_at) FROM exam_set_assignments a WHERE a.exam_id = e.id) AS first_assignment,
       (SELECT COUNT(DISTINCT a.exam_set_id) FROM exam_set_assignments a WHERE a.exam_id = e.id) AS distinct_sets_dealt
FROM exams e
ORDER BY e.id DESC;
```

`sets = 3` but `distinct_sets_dealt = 1` confirms it.

**Fix**
- Deal at the moment the student actually *starts* a part (`startPart`), not on page view;
  `show()` should only *read* an existing assignment (there is already `assignedSet()` for
  exactly this) and preview the set they *will* get.
- When the exam's set list changes (set added/removed), drop the assignments of students who
  have not submitted/started anything yet, so they get re-dealt from the new deck.
- Add an admin header action on the exam: **"Re-shuffle sets"** — re-deals every student who
  has no submission yet, and reports how many were moved. Without this, an exam that was ever
  opened as a single-set exam can never become multi-set.

## Defect 2 — the deck contains sets that have no questions

`sets()` (`:44`) returns *every* row in `exam_sets`, whether or not it owns any parts.
"Number of sets = 4" creates 4 **empty** sets on save, and every bulk write path defaults to
the first set:

- *Upload Questions* → `Select::make('exam_set_id')->default(first set)` and the selector is
  hidden entirely when the exam has one set at import time (`EditExam.php`, `ExamsTable.php`).
- AI question drafts → `exam_set_id` select, defaulting to the first set.
- `ExamPart::creating()` → any part created without a set is parked in
  `ExamSet::ensureDefaultForExam()` = **the first set**.

So the common outcome is: Set A holds all the questions, Sets B–D are empty shells. Students
dealt B–D get an exam with **zero parts** (`filterParts()` returns nothing for them), which
looks like a broken exam — and the natural reaction is to delete the extra sets and conclude
"only the first set is being distributed".

**Fix:** deal only from sets that actually have parts (`sets()->has('parts')`), i.e. make the
rotation "depend on the sets available" literally. Surface the rest in the panel: a badge per
set in the repeater showing its question count, and a validation warning on save/publish when
a set is empty.

## Defect 3 — every DB error silently degrades to Set A

```php
} catch (QueryException) {
    $existing = $this->assignmentFor($exam, $user);
    return $existing !== null ? $sets->firstWhere(...) : $sets->first();   // ← :344-352
}
```

The `catch` is meant for the unique-index race, but it swallows *every* `QueryException`:
missing migration on the deployed DB, FK failure, deadlock, `lock wait timeout` under the
"everybody clicks at 8:00" load. In all of those cases the student is handed **Set A and no
row is written**, so it happens again on the next request — permanently, invisibly, for the
whole class. Nothing is logged.

**Fix:** catch only the unique-violation (re-read the winner's row), log everything else at
`error` level, and never fall back to `sets->first()`. A safe deterministic fallback is
`deck[crc32(user_id) % deck->count()]`, which still spreads the class if the write fails.

## Defect 4 — the shuffle itself is biased, and the counter is global

`dealOrder()` uses an LCG masked to 31 bits and then takes `state % (i + 1)` — the *low* bits
of an LCG, which are famously non-random (bit 0 of `state * 1103515245 + 12345` simply flips
every step). Simulated over 10 000 exam/section seeds, the set that lands in slot 0:

| sets | slot-0 distribution across seeds |
| --- | --- |
| 2 | 5000 / 5000 ✔ |
| 3 | 3340 / 3308 / 3352 ✔ |
| **4** | **3323 / 3350 / 2491 / 836** ✘ — the 4th set is 4× less likely to be dealt first |

With 4+ sets the "shuffle" is measurably skewed toward the earlier sets, which reinforces the
Set-A impression. Related, smaller issues:

- The seed is `crc32(section:exam)` only, so the deck order for an exam is fixed forever —
  re-running the deal can never produce a different order, which makes Defect 1 unfixable
  without a seed input (add e.g. a `deal_seed`/`dealt_at` column bumped by "Re-shuffle").
- `$handedOut` counts assignments **exam-wide** and depends on rows never being deleted.
  Counting *per set* and dealing the least-used set (ties broken by the shuffled order) is
  self-healing: it stays balanced when rows are deleted, students are removed, or sets change
  mid-flight, and it balances per section for a global exam.

**Fix:** hash-order the deck (`sha1("{seed}:{set_id}")`) or use a proper PRNG (`mt_srand` on a
cloned state / xoshiro), and pick the least-loaded eligible set instead of `count % n`.

## Defect 5 — read-only screens *display* Set A to unassigned students

`summariesFor()` returns `set => null` before a student is assigned, but the part counts come
from the first set, and `filterParts($exam, $parts, null)` also falls back to
`sets->first()` (`:170-190`). So on the hub, the dashboard (`UpcomingExamsService`) and the
exam card, an unassigned student sees Set A's part list and counts. If the sets differ in
length, the counters are wrong until they open the exam — and it visually confirms "everyone
is on Set A" even where the deal would have worked.

**Fix:** once Defect 1 is fixed the student's set is known before they start, so show the
*deal preview* (`deck[slot for this student]`) instead of "first set", or show a neutral
"Set assigned when you start".

## Defect 6 — the service memoised the deal on a long-lived Octane worker (confirmed in CI)

Production runs Octane + FrankenPHP (`start.sh`, `Dockerfile`). `ExamSetAssignmentService`
kept three memo arrays on the instance (`$sets`, `$dealable`, `$resolved`) — and the instance
lives far longer than a request: Laravel caches the resolved controller on the `Route` object
(`Route::getController()`), so on an Octane worker the same `ExamController` — and the same
service — serves every subsequent request.

Consequences on a warm worker:

- `sets($exam)` memoised while the exam still had **one** set keeps returning that single-set
  list, so the deck stays "Set A" for every student that worker serves, no matter how many
  sets the teacher adds afterwards;
- `$resolved["{exam}:{user}"]` short-circuits `resolve()`, so the service returns a set
  **without ever writing the assignment row** — the deal silently stops persisting.

This reproduced in CI: after assignments were released, four students re-opened the exam and
**zero** rows were written, because the cached controller's service replayed its memo. The
same repo already documents this exact hazard for `RequestCache`
("under Octane a static survives between requests on the same worker").

**Fix:** all instance memoisation was removed from the service; the remaining lookups are
single indexed queries. `tests/Feature/Exams/ExamSetDistributionTest.php` now pins this with a
test that reuses one service instance across a set being added.

### Bonus — a student can answer another set's parts

`startPart()`, `saveAnswers()` and `submitPart()` only check
`$examPart->exam_id !== $exam->id` (`ExamController.php:321, 377, 460`). They never check that
the part belongs to the student's assigned set, so a hand-crafted request can submit Set B's
parts while assigned Set A (and `deriveFromExistingWork()` will then re-pin them). Worth an
`abort_unless($part->exam_set_id === $assignedSet->id, 403)`.

---

## Recommended order of work

1. **Deal on start, not on view** + re-deal unstarted students when sets change + admin
   "Re-shuffle sets" action. *(fixes existing, already-poisoned exams)*
2. **Exclude question-less sets from the deck** + show per-set question counts in the panel.
3. **Stop swallowing DB errors** in `assignNext()`; log, and fall back deterministically per user.
4. **Least-loaded, hash-ordered deal** (unbiased, self-healing, per-section for global exams).
5. Set-scoped part authorisation on start/save/submit.
6. Listing/dashboard preview of the set instead of the first-set fallback.

Items 1–4 are contained in `app/Services/ExamSetAssignmentService.php`,
`app/Http/Controllers/ExamController.php`, `app/Models/ExamSet.php` and the Filament
`EditExam` page, and the existing suites (`tests/Feature/Exams/ExamSetsTest.php`,
`tests/Unit/ExamFormSetCountTest.php`) already cover the invariants that must not regress
(sticky set per attempt, only-my-set questions, XP against my own set).

---

## What was implemented

| Change | Where |
| --- | --- |
| Deck draws only from sets that hold questions (`dealableSets()`) | `ExamSetAssignmentService` |
| Unbiased deal order — sets keyed by `sha256("{seed}:{set id}")` instead of LCG low bits | `ExamSetAssignmentService::dealOrder()` |
| Least-used set is dealt (ties keep deck order), instead of `count % n` — self-healing and stays even when sets/rows change | `ExamSetAssignmentService::leastUsed()` |
| `redealUnstarted()` — releases the hand-out of every student with no submission, draft or timer | `ExamSetAssignmentService` |
| Adding/removing a set, or a set receiving its **first question**, triggers that re-deal automatically | `ExamSet::booted()`, `ExamPart::booted()` |
| **"Re-shuffle Sets"** header action (confirm modal, reports how many students move + the deal order) | `EditExam` |
| Warning after save listing sets that still have no questions | `EditExam::afterSave()` |
| Unique-violation is handled separately; any other DB error is **logged** and falls back to a per-user deck slot instead of Set A for everyone | `ExamSetAssignmentService::assignNext()` |
| Students can no longer start/answer/submit a part from a set they were not given | `ExamController::assertPartInAssignedSet()` |
| Unassigned students are previewed against the first set that actually has questions | `ExamSetAssignmentService::summariesFor()` |
| **All instance memoisation removed** — it survived across requests on an Octane worker (controller cached on the `Route`), freezing the set list and skipping the assignment write | `ExamSetAssignmentService` |

New coverage in `tests/Feature/Exams/ExamSetDistributionTest.php`: even split across sets,
empty sets never dealt, a class that only browsed gets re-dealt when a set is added, a
student who already started keeps their set, foreign-set parts are rejected, and the deck
is not simply the stored order, and a single service instance reused across requests (the
Octane case) still sees a newly added set.

### Migrating the exams that are already stuck

Existing exams keep their poisoned assignments until something re-deals them. Either edit the
exam (adding/removing a set re-deals automatically) or press **Re-shuffle Sets** on the exam's
edit page — students who already answered are never moved.

### Not done (deliberate)

Assignment still happens when a student *opens* the exam rather than when they start a part.
Moving it to `startPart()` would change what the exam page can show before the first part is
started; the automatic re-deal removes the harmful consequence without that churn. Say the
word if you would rather have the deal deferred to the first "Start" click.
