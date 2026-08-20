# Group Assignment Invite Flow — Design Spec

Status: **awaiting review** — no code yet. Decisions below were confirmed with
the product owner:

| Decision | Choice |
| --- | --- |
| Consent model | Invites **replace** instant add/remove-group formation entirely |
| Responses | **Flexible**: submit with whoever accepted; late accepters still join |
| Size limits | **Teacher-configurable** min/max per assignment |
| Delivery | Design first, implement after review |

## 1. Problem

Today `AssignmentGroupService::addMember()` lets the group creator pull a
classmate into a group **unilaterally**. The classmate never consents, and if
the group already submitted, their `assignment_user` row is immediately marked
**Submitted** with the shared file. A student can appear to have submitted work
they have never seen. There is also no teacher control over group sizes.

## 2. Proposed flow (student's view)

1. **Compose** — on a pending assignment card, "Form a group" opens a modal
   (step 1): search section-mates, add/remove chips, live slot counter
   ("2 of 4 slots"). Sending creates the group (creator-only) + invites.
2. **Respond** — invitees get a database notification **with Accept / Decline
   buttons in the bell dropdown**, and the same Accept / Decline banner on the
   assignment card itself. No forced trip to a separate page.
3. **Track** — the creator's card is the status surface: accepted members as
   avatars, pending invitees greyed with a "Waiting…" chip, per-invite cancel.
   The modal is *not* a blocking wizard — waiting on humans happens on the card.
4. **Submit** — unchanged upload modal. Allowed whenever the group satisfies
   the size rules (§5). Members who accept **after** submission still join and
   inherit the shared file (existing late-joiner logic already handles this).

## 3. Schema

### New: `assignment_group_invites`

| Column | Type | Notes |
| --- | --- | --- |
| `id` | pk | |
| `assignment_id` | fk → assignments | cascade delete |
| `group_id` | fk → assignment_groups | cascade delete |
| `inviter_id` | fk → users | who sent it (group creator at send time) |
| `invitee_id` | fk → users | who receives it |
| `status` | enum-string | `pending`, `accepted`, `declined`, `cancelled`, `expired` |
| `responded_at` | timestamp nullable | set on accept/decline/cancel |
| `expires_at` | timestamp nullable | see §5 TTL |
| `workspace_id` | fk → workspaces | house convention (`BelongsToWorkspace`) |
| timestamps | | |

Indexes:
- unique `(assignment_id, invitee_id)` **where status = pending** — one live
  invite per student per assignment (a student courted by two groups must
  answer one first).
- index `(group_id, status)` for card rendering.

### Changed: `assignments`

- `min_group_size` unsigned tinyint nullable (null = no minimum, 1 = solo ok)
- `max_group_size` unsigned tinyint nullable (null = unlimited)

### Unchanged

`assignment_groups` and the mirrored `assignment_user` rows stay exactly as
they are — invites are a **front door** to group membership, not a new
membership model. Grading, XP, admin tables keep working untouched.

## 4. Invite lifecycle

```
            invite()                respond(accept)        addMember path
 pending ──────────────► accepted ────────────────────► member row written
    │                      (invitee)                     (reuse service)
    ├─ respond(decline) → declined   (slot frees, re-invitable)
    ├─ creator cancel   → cancelled  (slot frees, re-invitable)
    └─ expiry sweep     → expired    (TTL or due date, whichever first)
```

State transitions are guarded in the service; status changes are terminal —
a re-invite creates a **new** row (the partial unique index allows that).

## 5. Rules & invariants

1. **One group per student per assignment** — existing unique
   `(assignment_id, user_id)` index; unchanged.
2. **One pending invite per invitee per assignment** — partial unique index
   above; races produce a friendly 422 ("They already have a pending invite").
3. **Max size** — creator cannot send invites past `max_group_size`
   (accepted + pending counted against the cap so two pending invites can't
   push a 4-max group to 5). Hard-enforced client + server.
4. **Min size at submit** — ⚠ open question A; recommended rule:
   - blocked while below min **and** invites are still pending;
   - once every invite is resolved (accepted/declined/cancelled/expired) and
     the group is still below min, submission is allowed but flagged
     `under_minimum` for the teacher (visible in the Filament submissions
     table). No student is ever deadlocked at 11 PM.
5. **Locked when graded** — `ensureNotGraded` already aborts everything;
   invites also become non-sendable/non-acceptable once graded.
6. **Expiry** — pending invites expire at `min(expires_at, due_date)`.
   Recommended TTL: 48 h. ⚠ open question B.
7. **Declined → re-invitable** — yes (new row). A decline is an answer to an
   invitation, not a block on future ones.
8. **Creator leaves** — existing earliest-member transfer applies. If a group
   empties, pending invites are cancelled with it (cascade + sweep).
9. **Candidates list** — `/groups/candidates` excludes: already-grouped
   (today) **and** students holding a pending invite for this assignment.
   They render as "unavailable" rather than 422-ing on click.

## 6. Backend surface

```
POST   /assignments/{assignment}/invites              # bulk send: {user_ids: number[]}
POST   /assignments/{assignment}/invites/{invite}/respond   # {action: 'accept'|'decline'}
DELETE /assignments/{assignment}/invites/{invite}     # creator cancels a pending invite
```

- New `AssignmentInviteService` (`invite`, `accept`, `decline`, `cancel`,
  `expireOverdue`) wrapping the existing `AssignmentGroupService` — accept
  funnels into the same membership write the current add-member uses.
- **Removed**: `POST /assignments/{assignment}/groups` (instant create) and
  `POST /assignments/{assignment}/groups/members` (instant add). Kept:
  remove-member/leave (groups still need management after forming).
- `AssignmentController::index()` payload additions per assignment:
  - `group_rules: {min: number|null, max: number|null}`
  - `incoming_invite: {id, inviter: {id, name, avatar}, status, expires_at} | null`
    (the viewing student's pending invite — drives the card banner)
  - on `group`: `pending_invites: [{id, user: {id, name, avatar}, expires_at}]`
    (drives the creator's greyed avatars + cancel buttons)

## 7. Notifications & realtime

- `AssignmentInviteSent` → database notification to invitee, payload
  `{type: 'assignment_invite', invite_id, assignment_id, inviter_name,
  assignment_title, actions: ['accept','decline'], icon: 'users'}` + broadcast
  on `App.Models.User.{id}` (same pattern as `AssignmentGraded`).
- `AssignmentInviteResponded` → notifies the creator (accepted/declined/who).
- **Bell dropdown**: extend `AppHeader.vue` items with optional action buttons
  that call the respond endpoint then `router.reload({only:['notifications']})`.
  Click-anywhere still marks read.
- Assignments page subscribes once (Echo already wired) and partially reloads
  `assignments` on either event, so banners/statuses appear live.

## 8. Admin (Filament)

- `AssignmentForm`: `min_group_size` / `max_group_size` numeric fields
  (validated `min ≥ 1`, `max ≥ min`), shown alongside sections/due date.
- Submissions table: badge for `under_minimum` groups (if rule 4 adopted).

## 9. Student page UI states

| Who | Card shows |
| --- | --- |
| Ungrouped, uninvolved | "Form a group" (if limits allow) + Submit |
| Creator, invites pending | Accepted avatars + greyed pending ("Waiting…"), cancel buttons, "Invite more" while below max |
| Invitee, pending invite | Top banner: "{Inviter} invited you" [Accept] [Decline] |
| Member | Current group block (unchanged) + leave |
| Graded (anyone) | Everything read-only (unchanged) |

Invite modal = **2 steps max**: (1) pick members → (2) sent-confirmation.
No blocking "waiting" step — that state lives on the card.

## 10. Tests

- **Feature (PHP)**: invite lifecycle (send/accept/decline/cancel/expire);
  size caps (max blocks sends, min gates submit per rule 4); one-pending-invite
  race; graded lock; late accept inherits shared file; notifications written;
  endpoints authorization (invitee-only respond, creator-only cancel);
  candidates list exclusions; index payload shape.
- **JS (vitest)**: card banner accept/decline wiring; invite modal slot
  counter + send; greyed pending list; modal closes and card reflects
  `pending_invites`; existing assignments suite keeps passing.

## 11. Rollout

1. Migrations (invites table + size columns) — additive, zero downtime.
2. Service + endpoints + notifications behind the new routes.
3. Student page switch (remove instant-add UI in the same release — the old
   routes are deleted, so UI and API change together).
4. Existing groups are untouched — the flow only governs **forming** groups.

## 12. Open questions for review

- **A. Minimum enforcement** — adopt the no-deadlock rule (block while invites
  pending; allow-but-flag once all resolved), or hard-block below min always?
- **B. Invite TTL** — 48 h, 24 h, or "until due date only"?
- **C. Bulk invites** — cap invites per send (e.g., one batch up to remaining
  slots) — confirmed by slot counter, OK?
- **D. Bell actions** — Accept/Decline directly in the dropdown (recommended),
  or notification click-through to the assignments page only?
