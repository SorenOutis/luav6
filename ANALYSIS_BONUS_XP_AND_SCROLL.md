# Analysis — Bonus XP Claim + Level Stat Cards Modal + Scrolling Bugs

**Date:** 2026-08-17  
**Repo:** `SorenOutis/luav6` @ `arena/01a00e6e-luav6` (base `5ebd184`)  
**Request:**  
1. Add a **second claimable XP — “Bonus XP”** — beside the existing **Daily XP**, surfaced **inside the Level stat cards modal** (the “Your XP history” modal that opens when you click the Level card). Below the existing “Today’s daily XP claimed” banner, show the new bonus claim block. Admin configures the Bonus XP value in **Platform Settings → Daily XP Claim → Bonus XP**.  
2. Fix **mouse/trackpad scrolling not working** inside the **XP History modal** and the **Notifications dropdown**.

This doc is **analysis only** — no code was changed. It maps the *current* implementation, identifies gaps, and proposes a minimal, safe design for both tasks.

---

## 1 — Current Daily XP Claim (as-built)

### 1.1 Settings (Admin — `Filament/Pages/AiSettings.php`)

```php
Section::make('Daily XP Claim')
  ->description('Configure the daily login reward…')
  ->schema([
    Toggle::make('daily_claim_enabled')       // Setting key: daily_claim_enabled (bool, default true)
      ->helperText('If disabled, … hidden…'),
    TextInput::make('daily_claim_base_xp')    // Setting key: daily_claim_base_xp (int 1..1000, default 1)
      ->visible(fn($get) => $get('daily_claim_enabled'))
      ->helperText('… Streaks add +1 XP every 5 days, up to +4 (e.g. base 1 → 1–5 XP).'),
  ]);

// persist in save()
Setting::set('daily_claim_enabled', '1'|'0');
Setting::set('daily_claim_base_xp', (string) max(1, (int) value));
```

No other XP claim exists — search for `bonus` outside Tower Defense is only streak-bonus text ([AiSettings:211], [ClaimXpService:23]).

### 1.2 Service (`app/Services/ClaimXpService.php`)

```php
class ClaimXpService {
  const MAX_STREAK_BONUS = 4;
  isEnabled(): bool                // Setting daily_claim_enabled
  baseXp(): int                   // Setting daily_claim_base_xp, clamp ≥1
  canClaim(User): bool            // check last_claimed_at.isToday() && ! DailyXpClaim exists for today
  claimAmount(User): int          // baseXp + min(4, floor(streak/5))
  nextClaimAt(User): ?Carbon
  claim(User): array              // transaction + insertOrIgnore.daily_xp_claims + mirrorToSections + awardGlobalAndSeason + GamificationHistory(reason='Daily Claim')
}
```

- Concurrency guard: **unique `(user_id, claim_date)`** in `daily_xp_claims`.  
- Mirrors XP to every `SectionProgress` (without double-counting global/season) via `GamificationSyncContext`.  
- History: `GamificationHistory{ amount_xp, reason='Daily Claim', description='Daily login claim bonus' }`.

### 1.3 Ledger (`database/migrations/2026_08_16_000003_create_daily_xp_claims_table.php` + `app/Models/DailyXpClaim.php`)

```php
daily_xp_claims: id, user_id→users, season_id→seasons(nullable), claim_date:date, amount:uint, streak:uint, claimed_at:ts, timestamps
  unique(user_id, claim_date)   // critical
  index(season_id, claim_date)
```

Also legacy `users.last_claimed_at` is kept as a pre-ledger guard.

### 1.4 Controllers / Routes

```php
// routes/web.php
POST api/claim-xp              → ClaimXpController@__invoke   (throttle:claim-xp, Limit::perMinute(10))
POST api/claim-xp/prompt-shown → ClaimXpController@promptShown (session flag daily_claim_prompt_shown_on)
GET  users/{user}/xp-history   → XpHistoryController
RateLimiter 'claim-xp'         // AppServiceProvider:156

// DashboardController
canClaim, claimAmount, nextClaimAt computed per request
showClaimPrompt = canClaim && session(daily_claim_prompt_shown_on) !== today
inertia('Dashboard', [
  'claimXp' => [enabled, canClaim, amount, baseXp, nextClaimAt, lastClaimedAt, showPrompt],
  'xpHistory' => GamificationHistory.where(amount_xp !=0).latest(30),
  'statsBreakdown' => grouped by reason,
  ...
])
```

`ClaimXpController` simply delegates to `ClaimXpService::claim()`.

### 1.5 Frontend

| File | Role |
|---|---|
| `ClaimXpButton.vue` | The *only* claim button. Handles `POST /api/claim-xp`, states `idle→claiming→claimed`, GSAP particles, modal prompt (`showClaimModal`), countdown, `promptShown` POST. Props: `canClaim, amount, baseXp, nextClaimAt, streak, showPrompt` |
| `DailyRewardCard.vue` | Thin wrapper: shows `<ClaimXpButton>` only when `claimXp?.canClaim && !hideClaimCard`. Emits `claimed` → Dashboard does `router.reload(only: claimXp,xpHistory,…)` after 1.4s |
| `LevelProgressCard.vue` | The **Level card** itself (`p-3.5`, tap to open). Modal = `ResponsiveModal title="Your XP history"`. Inside modal: <br>• **Daily-claim status banner** (`claimStatus` computed: `claimed | available | never`) <br>• Tabs `History` (per-entry ledger, `max-h-[50vh] overflow-y-auto`) vs `Summary` (aggregated) <br>• No claim button today — just a status + hint “claim it from the daily reward card” |
| `Dashboard.vue` | Holds `claimXpForPrompt` (gated by `claimPromptReady` until `sectionName` exists). Passes same object to both `DailyRewardCard` *and* `LevelProgressCard`. Polls every 30s (`POLL_PROPS` includes `claimXp, xpHistory`) |
| `ResponsiveModal.vue` | Mobile → `MobileBottomSheet` (has `data-lenis-prevent` ✅). Desktop → `Dialog` → `DialogContent` (`max-h-[85vh] overflow-y-auto overscroll-contain` — see §3) |
| `DialogContent.vue` | `fixed … max-h-[85vh] overflow-y-auto overscroll-contain … sm:max-w-lg` — portals + overlay |
| `MobileBottomSheet.vue` | `max-h-[70vh] overflow-y-auto overscroll-contain` on `contentRef` + `data-lenis-prevent` ✅ |
| `AppHeader.vue` (also `AppSidebarHeader.vue`) | Notifications bell → `DropdownMenu` → `DropdownMenuContent align="end" class="w-80 … p-0"` → inner list `max-h-[min(24rem,50vh)] overflow-y-auto overscroll-contain p-2` renders 8 latest notifications from `HandleInertiaRequests::share` |

---

## 2 — Desired Bonus XP Feature (parsed from request)

> “another one that the students can claim xp besides from the daily xp, … in the level stat cards when clicked there is a modal right? it will display there the Todays daily xp claimed etc. and below that there it should show another claim xp. now in the admin panel in the platform settings in the Daily XP Claim, below that i should create another Bonus XP then i will put the value.”

### 2.1 Intent

- A **second, independent daily claim** (“Bonus XP”) alongside Daily XP.
- Admin sets its **value** in **Platform Settings → Daily XP Claim → Bonus XP** (below the base XP field).
- Student UX: open **Level → Your XP history** modal → sees **two stacked status blocks**: top = Daily XP, bottom = Bonus XP. Each can be claimed from inside that modal (not only from the separate Daily Reward card).

Open questions to confirm with you before coding:

1. **Frequency:** should Bonus XP also be **once per calendar day** (like Daily XP) or e.g. once per season / unlimited? Assumed **daily**.  
2. **Streak scaling:** should Bonus XP reuse the same `+1 per 5 streak (max +4)` or be a **flat amount** from the admin value? Assumed **flat** (admin value is the final amount) — simplest and matches “I will put the value”.  
3. **Enable toggle:** should Bonus XP have its **own enable/disable toggle** (`bonus_claim_enabled`) or always follow `daily_claim_enabled`? Recommended: **own toggle**, default enabled = false or true (your call).  
4. **Season scoping:** should it mirror into SectionProgress/SeasonProgress and write `GamificationHistory` like daily does? Recommended: **yes**, same pattern but reason = `Bonus Claim` (so history/tone mapping can show differently).  
5. **Popup behavior:** should Bonus XP also auto-prompt on dashboard visit like Daily does (`showPrompt`), or only be claimable inside the modal? Recommended: **no auto-popup** — quieter; claim from the modal only.

### 2.2 Proposed Settings Keys (under existing `Daily XP Claim` section)

```php
// New keys — all via Setting::get / Setting::set
'daily_claim_bonus_enabled' // bool, default false (or true if you want it live immediately)
'daily_claim_bonus_xp'      // int, 1..1000, default 5 (or your pick)
// optionally:
'daily_claim_bonus_label'   // string, default 'Bonus XP' — if you want admin to rename it
```

In `AiSettings::mount()` and `AiSettings::form()` they sit **below** `daily_claim_base_xp`, same `Section::make('Daily XP Claim')`:

```php
Toggle::make('daily_claim_bonus_enabled')
  ->label('Enable Bonus XP Claim')
  ->helperText('Second daily reward shown inside the Level → XP History modal.')
  ->reactive(),
TextInput::make('daily_claim_bonus_xp')
  ->label('Bonus XP per Claim')
  ->numeric()->integer()->minValue(1)->maxValue(1000)->required()
  ->helperText('Flat XP for the bonus claim (streak not applied).')
  ->visible(fn($get)=> (bool)$get('daily_claim_bonus_enabled')),
```

Persist similarly to `daily_claim_*` in the `save()` handler (`max(1, …)` clamp).

### 2.3 Ledger Options

**Option A — new table `bonus_xp_claims`** (mirrors `daily_xp_claims`) — **recommended**.
```
bonus_xp_claims: id, user_id, season_id nullable, claim_date date, amount uint, streak uint default 0, claimed_at ts, timestamps
  unique(user_id, claim_date)
  index(season_id, claim_date)
```
- Pro: clean, identical concurrency guard, separate rate/introspection, no migration risk to existing data.

**Option B — add `type` enum to `daily_xp_claims`** (`daily|bonus`) and widen unique to `(user_id, claim_date, type)`.
- Pro: one table. Con: needs altering existing unique index + backfill, riskier.

**Option C — reuse `GamificationHistory` alone** (no ledger).
- Con: no atomic DB guard; race can double-award.

Recommended: **A**.

Model `BonusXpClaim` mirrors `DailyXpClaim`.

### 2.4 Backend Service

Two viable patterns:

- **`BonusClaimService`** (new class, mirrors `ClaimXpService` minus streak math) — cleanest; `isEnabled()`, `baseXp()`→`bonusAmount()`, `canClaim()`, `claim()`.
- Or extend `ClaimXpService` to handle both (`claimBonus(User)`).

Either way the `claim()` contract should reuse the same sub-steps:
- `insertOrIgnore` ledger
- `mirrorIntoSections(amount)` via `GamificationSyncContext::withoutSectionPropagation`
- `awardGlobalAndSeasonProgress(amount)` (same logic)
- `recordGamificationHistory(amount, 0, 'Bonus Claim', 'Bonus daily claim', null, season_id)`
- update *separately*: either a new `users.last_bonus_claimed_at` column **or** rely solely on ledger (preferred to avoid extra column). For `nextClaimAt` / `canClaim` UI we can use ledger existence; a timestamp column is convenient for `whenLabel` but not required.

Throttle: new limiter `claim-bonus-xp` (or reuse `claim-xp`) — recommend **same 10/min** but distinct key so daily spam doesn’t block bonus.

Routes:

```php
POST api/claim-bonus-xp              → BonusClaimController (auth,verified,throttle:claim-bonus-xp)
POST api/claim-bonus-xp/prompt-shown  (optional, if you want a popup)
```

DashboardController then shares *both*:

```php
'claimXp'      => [enabled, canClaim, amount, baseXp, nextClaimAt, lastClaimedAt, showPrompt],
'bonusXp'      => [enabled, canClaim, amount, nextClaimAt, lastClaimedAt], // or claimBonus
```

### 2.5 Frontend — Level Modal (LevelProgressCard.vue)

**Today** the modal shows:

```
[Daily-claim banner]  (claimed / available / never)
[History | Summary tabs]
  History:  max-h-[50vh] scroll list …
```

**Proposed** (minimal diff):

```
[Daily-claim banner]            ← existing, unchanged
[Bonus XP banner]               ← new, same component pattern
   states:
     - bonus disabled → hidden (null)
     - available      → amber/ violet tone, "Bonus XP — +X ready" + [Claim Bonus XP] button
     - claimed        → emerald tone, "Bonus XP claimed today +X · time"
   button calls POST /api/claim-bonus-xp, local state bonusState idle/claiming/claimed,
   on success emits update so Dashboard reloads claimBonus + xpHistory + userStats

[History | Summary tabs]
```

Implementation sketch:

```ts
interface BonusInfo { enabled?: boolean; canClaim: boolean; amount: number; nextClaimAt?: string|null; lastClaimedAt?: string|null }
props: { bonusXp?: BonusInfo } // plus existing claimXp

const bonusStatus = computed<ClaimStatus|null>(() => {
  if(!props.bonusXp || props.bonusXp.enabled===false) return null;
  if(props.bonusXp.canClaim) return {state:'available', amount:props.bonusXp.amount};
  return {state:'claimed', amount:props.bonusXp.amount, whenLabel: formatWhen(props.bonusXp.lastClaimedAt!)};
});
```

- Styling: use a distinct tone to differentiate from daily (e.g. `violet` / `bg-[#9D7CD8]/15` for bonus vs `amber` for daily).  
- Button: replicate `ClaimXpButton`’s idle/claiming UI but violet palette, or extract a shared `ClaimXpServiceButton` component.  
- On success: update `bonusStatus` locally to `claimed`, fire `axios.post('/api/claim-bonus-xp')`, then `router.reload({only: ['bonusXp','xpHistory','userStats','notifications']})` or emit to Dashboard.  
- History mapping: add `reasonMeta('bonus claim') => {icon: Star, label:'Bonus Claim', tone:'violet'}` so ledger rows show distinctly.

**Alternative if you prefer not to add an API button inside the history modal:** keep the status banner only (“+X bonus available”) and keep the actual button on the dashboard (`DailyRewardCard` area) — but request explicitly says “below that there it should show another claim xp” inside the modal, so the button belongs there.

### 2.6 Admin UI wireframe

`Filament/Pages/AiSettings.php` → `Platform Settings` → existing `Daily XP Claim` card:

```
┌─ Daily XP Claim ────────────────────────────────────────┐
│ Enable Daily XP Claim  [toggle]                           │
│ Base XP per Claim      [  1  ]  (numeric, visible when    │
│                                    enabled)               │
│ ───────────────────────────────────────────────────────  │
│ Enable Bonus XP Claim  [toggle]  ← NEW                    │
│ Bonus XP per Claim     [  5  ]  ← NEW (visible when      │
│                                    bonus enabled)         │
│ helper: “Flat XP for the bonus claim shown in Level →    │
│          XP History. Once per day, alongside Daily XP.”   │
└───────────────────────────────────────────────────────────┘
```

Keep it in the *same* section so admins don’t hunt for it; visually separate with a `Section` divider or Grid.

### 2.7 Risks / Edge Cases

- **Double award:** must keep `unique(user_id, claim_date)` ledger + `insertOrIgnore` pattern; otherwise two rapid taps/ workers race.  
- **Season rollover:** `season_id` on ledger should capture *current* season; history already filters by active season. Bonus history should do the same.  
- **Permissions:** bonus check uses same bracket as daily — no extra role; any verified student can claim.  
- **Toasts / notifications:** consider firing a `StudentNotificationService` notification on bonus claim like daily? Current daily does *not* push a notification (only XP history); keep bonus consistent.  
- **Analytics:** `xpBreakdown` / `statsBreakdown` queries already group by `reason`; bonus will appear as its own row automatically.  
- **Migration safety:** new table is additive; zero downtime.

### 2.8 File impact checklist (if implemented)

| Area | Files to touch |
|---|---|
| **Migrations** | new `create_bonus_xp_claims_table.php` |
| **Models** | `BonusXpClaim.php` (new), touch `User.php` (`bonusXpClaims()` relation, maybe `last_bonus_claimed_at` cast if added) |
| **Settings** | `Filament/Pages/AiSettings.php` (`mount()`, `form()`, `save()`/`mutateFormDataBeforeSave`) |
| **Services** | `Services/BonusClaimService.php` (or extend `ClaimXpService.php`) |
| **Controllers** | `Http/Controllers/Api/BonusClaimController.php` or `ClaimXpController@claimBonus` |
| **Routes** | `routes/web.php` (`POST api/claim-bonus-xp`) + rate limiter in `Providers/AppServiceProvider.php` |
| **Inertia share** | `Http/Controllers/DashboardController.php` (share `bonusXp`) |
| **Frontend** | `resources/js/components/dashboard/LevelProgressCard.vue` (new prop + bonus block + claim handler), possibly `resources/js/pages/Dashboard.vue` (pass `bonusXp`) |
| **Tests** | `tests/Feature/BonusClaimTest.php` (mirrors `ClaimXpTest.php`) |

---

## 3 — Scrolling Bug — XP History Modal & Notifications Dropdown

### 3.1 Symptoms (as reported)

- XP History modal content (the `History` tab list) does **not scroll with mouse wheel / trackpad**, presumably keyboard scrollbar drag still works.  
- Notification bell dropdown list likewise does not scroll with wheel/trackpad.

### 3.2 Root Cause — **Lenis smooth-scroll hijacking** (confirmed)

Global setup: `resources/js/app.ts:316–317` → `initLenis()` (single `Lenis` instance, `smoothWheel: true`, `overscroll: true`, `autoRaf: true`).  
`Lenis` virtualises wheel/touch scroll: it `preventDefault`s the native `wheel` event, translates it into a smoothed `scroll` on `document.scrollingElement`, and recomputes `lenis.resize()` on Inertia navigations (`useLenis.ts`).

Lenis docs: any element that should **keep native scroll** must carry **`data-lenis-prevent`**. Only two places in the codebase do:

```html
<!-- MobileBottomSheet.vue:144 -->
<div data-lenis-prevent class="max-h-[70vh] overflow-y-auto …">

<!-- Sidebar.vue:87 -->
… data-lenis-prevent
```

**Every other scroll container lacks it:**

| Scroll container | File | Current overflow | `data-lenis-prevent` | Result |
|---|---|---|---|---|
| **Desktop dialog shell** | `ui/dialog/DialogContent.vue` | `max-h-[85vh] overflow-y-auto overscroll-contain` | ❌ missing | Wheel over modal background scrolls page behind (or nothing) instead of dialog. On desktop, `ResponsiveModal` delegates to this. |
| **XP History list** | `dashboard/LevelProgressCard.vue` (`History` tab) | `max-h-[50vh] overflow-y-auto overscroll-contain pr-1` | ❌ missing | Wheel is captured by Lenis → main page scroll, not list. Scrollbar may still drag via pointer events but wheel/trackpad dead. |
| **Notifications outer dropdown** | `ui/dropdown-menu/DropdownMenuContent.vue` | `max-h-(--reka-dropdown-menu-content-available-height) overflow-y-auto` | ❌ missing (no one adds attribute) | Outer portal hijacked; inner wheel never fires. |
| **Notifications inner list** | `AppHeader.vue` (`max-h-[min(24rem,50vh)] overflow-y-auto`) | `max-h-[min(24rem,50vh)] overflow-y-auto overscroll-contain p-2` | ❌ missing | Double-nested scroll with outer also scrollable compounds the issue. |

Other contributing factors:

- **`max-h-[85vh] overflow-y-auto` on `DialogContent` creates a *second* scroll layer above the history list.** Native behavior: wheel over the list should scroll the list until its edge, then chain to the dialog shell, then to the page. With `overscroll-contain` on both, chaining is blocked; with Lenis hijacking, the list never receives the wheel at all.  
- **No `overscroll-behavior` reset on mobile bottom sheet** unaffected because it already has `data-lenis-prevent`.  
- **Trackpad “two-finger” scroll** uses the same `wheel` path (often `deltaMode: 0` + momentum) — equally hijacked.  
- **Keyboard scroll / scrollbar drag** still works because Lenis only virtualises `wheel`/`touch`.

Verified by `grep -rn data-lenis-prevent resources` → only 2 hits, none in Dialog or Dropdown.

### 3.3 How to prove (reproduction)

1. Log in as student with a season that has ≥ 30 history rows (so History tab overflows).  
2. Open Level → modal → `History` tab. Hover the list and roll mouse wheel / two-finger swipe.  
3. Observe: page behind modal moves (or nothing), list stays put.  
4. Same with header bell → open notifications (need ≥ 9 items to overflow `24rem`), wheel over list — same.

On devices where `isLowEndDeviceSignal()` is true (coarse pointer / ≤4 GB / ≤4 cores / `prefers-reduced-motion`), Lenis is **not initialised** (`initLenis` returns null), so the bug likely does *not* repro there — consistent with “it works on my phone but not desktop”.

### 3.4 Proposed Fixes (no-risk, 3-line change type)

**A. Add `data-lenis-prevent` to every native-scroll portal.**

```vue
<!-- DialogContent.vue -->
<DialogContent
  data-lenis-prevent
  … class="… max-h-[85vh] overflow-y-auto overscroll-contain"
>

<!-- LevelProgressCard.vue — the History tab scroll area -->
<div
  v-if="activeTab === 'history'"
  data-lenis-prevent
  class="max-h-[50vh] overflow-y-auto overscroll-contain …"
>

<!-- AppHeader.vue — notifications inner list (the one that should scroll) -->
<div
  v-if="notifications.items.length > 0"
  data-lenis-prevent
  class="max-h-[min(24rem,50vh)] overflow-y-auto overscroll-contain p-2"
>

<!-- DropdownMenuContent.vue — pass-through: allow consumer to add data-lenis-prevent
     or hardcode it + ensure touch-action -->
<DropdownMenuContent
  data-lenis-prevent
  data-slot="dropdown-menu-content"
  class="… overflow-y-auto …"
>
  <!-- but remove outer overflow-y-auto if inner handles it, or keep both with prevent -->
```

> Lenis docs: `data-lenis-prevent` on the scrolling element makes Lenis `stop` while the pointer is over it, restoring native wheel/scroll. Touchpad scroll also restored.

**B. Disambiguate nested scroll layers.**

- Keep `DialogContent` as `overflow-y-auto` (so short modals still fit on small viewports) **and** keep inner `max-h-[50vh]` list scroll — but **both need `data-lenis-prevent`**, otherwise Lenis races.  
- For notifications: the cleanest is **outer `DropdownMenuContent` = `overflow-hidden`**, **inner list = `overflow-y-auto overscroll-contain` + `data-lenis-prevent`**. Today both have `overflow-y-auto`; change outer to `overflow-hidden` or keep both with `data-lenis-prevent` (either fixes, former is tidier).

**C. Ensure synthetic scroll lock not fighting.**

`reka-ui`’s `DialogContent` automatically locks body scroll (via `remove-scroll` / `aria-hidden`). Lenis also tries to keep scrolling while `overscroll: true`. The `data-lenis-prevent` + `overscroll-contain` combo is the intended escape hatch; no extra JS needed. Do **not** set `overscroll: false` globally — would break other pages.

**D. Mobile is already correct.**

`MobileBottomSheet` already has `data-lenis-prevent`, so XP History on mobile bottom sheet likely *does* scroll (unless something else is wrong). If reporter’s mobile also broken, it might be the `overscroll-contain` on the inner list swallowing momentum — easy to test after fix A.

**Optional polish:**

- Add `scrollbar-gutter: stable` to prevent layout jank when scrollbar appears.  
- Add `touch-action: pan-y` via Tailwind `touch-pan-y` to help Chrome’s passive wheel.  
- Verify `DialogScrollContent.vue` (alternate dialog variant) also gets the attribute if used.

### 3.5 Files to change for scroll fix (4–6 one-liners)

| File | Change |
|---|---|
| `resources/js/components/ui/dialog/DialogContent.vue` | add `data-lenis-prevent` to `<DialogContent>` |
| `resources/js/components/ui/dialog/DialogScrollContent.vue` | same (if used anywhere) |
| `resources/js/components/dashboard/LevelProgressCard.vue` | add `data-lenis-prevent` to `activeTab === 'history'` div |
| `resources/js/components/AppHeader.vue` | add `data-lenis-prevent` to notifications inner scroll div (`max-h-[min(24rem…]`) or outer `DropdownMenuContent`; prefer inner + set outer to `overflow-hidden` |
| `resources/js/components/AppSidebarHeader.vue` | same notifications block (it duplicates the bell) |
| `resources/js/components/ui/dropdown-menu/DropdownMenuContent.vue` | optionally add passthrough `data-lenis-prevent` default or leave to consumers |

No backend changes for scroll.

---

## 4 — Recommendations & Next Steps

### 4.1 For Bonus XP — please confirm before we code:

1. Flat amount (e.g. 5) vs streak-scaled like daily?  
2. Should it have its own Enable toggle or follow daily’s toggle?  
3. Daily frequency confirmed? (once per calendar day)  
4. Want it to also auto-popup like daily, or only claimable inside the Level modal?  
5. Default value (what should admin see pre-filled: 5? 2? 10?)  
6. Should Bonus XP notify / push like other XP events?

If you’re happy with the **defaults assumed above** (flat, own toggle default OFF, daily, modal-only, default 5), we can ship exactly that.

### 4.2 Implementation priority

1. **Scroll fix first** — 10-minute, zero-risk, high UX win; verify on trackpad/mouse right after.  
2. **Bonus XP second** — ~1.5h (migration + service + controller + settings + LevelProgressCard + history tone + tests).

---

## 5 — Appendix: Raw References

- `app/Services/ClaimXpService.php` — streak math `MAX_STREAK_BONUS=4`, `claimAmount = baseXp + min(4,floor(streak/5))`.  
- `app/Filament/Pages/AiSettings.php:196–212` — `Section Daily XP Claim` form + `940–941` persist.  
- `resources/js/components/dashboard/LevelProgressCard.vue:120–350` — modal body: `claimStatus` + tabs + history list.  
- `resources/js/composables/useLenis.ts` — `autoRaf: true, overscroll: true, smoothWheel` + `isLowEndDeviceSignal()` gate.  
- `resources/js/app.ts:316` — `initLenis()` global.  
- `resources/js/components/ResponsiveModal.vue` / `MobileBottomSheet.vue:144` — only sheet has `data-lenis-prevent`.  
- `resources/js/components/AppHeader.vue:401–405` — notifications inner scroll container without `data-lenis-prevent`.  
- `database/migrations/2026_08_16_000003_create_daily_xp_claims_table.php` — unique `(user_id, claim_date)`.  
- `tests/Feature/ClaimXpTest.php` — full coverage template to duplicate for Bonus.

