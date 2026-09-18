# Investigation — Bonus XP not shown in the student XP-history modal

**Date:** 2026-08-17 · **Repo:** `SorenOutis/luav6` @ `arena/01a00e85-luav6` (HEAD `ebf7e3a`)
**Reported symptom:** Admin enables **Bonus XP** in Platform Settings → Daily XP Claim, but on the student dashboard the Level → "Your XP history" modal shows the daily claim banner only — the **Bonus XP claim block never appears** (daily claim itself works).

---

## 1 — Verdict

The feature is **fully implemented and correctly wired in this codebase** — backend service, API route, ledger migration, admin settings, dashboard prop, and the modal UI all exist and match up. I mounted the actual `LevelProgressCard.vue` component against the exact JSON shape `DashboardController` sends and the bonus block renders correctly in every state (available / claimed / disabled).

So the most likely reasons a **running** deployment shows the old behavior are operational:

1. **The student-facing frontend bundle is stale** (built before the bonus-XP UI was merged) — the #1 suspect.
2. **The `daily_claim_bonus_enabled` setting isn't visible in the student's settings scope** (workspace-scoped `settings` rows), so `BonusXpService::isEnabled()` silently returns `false`.
3. Settings cache staleness (unlikely; `Setting` flushes cache on save) or the `bonus_xp_claims` migration not being run (would 500 the whole dashboard, so unlikely given daily claim works).

Sections 2–4 show exactly where to look and how to confirm/fix each.

---

## 2 — Data flow that must be true for the block to appear

```
AiSettings (Filament) save()
  └─ Setting::set('daily_claim_bonus_enabled', '1')          [app/Filament/Pages/AiSettings.php:959]
DashboardController::__invoke()
  └─ BonusXpService::isEnabled()  → Setting::get('daily_claim_bonus_enabled', false)   [DashboardController.php:271]
  └─ 'bonusXp' => [enabled, canClaim, amount, nextClaimAt, lastClaimedAt]              [DashboardController.php:270]
Dashboard.vue
  └─ <LevelProgressCard :bonus-xp="props.bonusXp" … />                                 [Dashboard.vue:761]
LevelProgressCard.vue
  └─ bonusStatus computed → v-if="bonusDisplayStatus" renders the block + claim button  [LevelProgressCard.vue:115, 426]
  └─ Claim button → POST /api/claim-bonus-xp (BonusClaimController → BonusXpService::claim)
```

Every link exists. The block is hidden only when `props.bonusXp` is `undefined` **or** `bonusXp.enabled === false`. There is no code path that hides it for claimed-but-available users, on mobile vs desktop, or after a daily claim (the two claims are independent — proven by the new tests).

## 3 — Root cause candidates (ranked) & how to verify

### 3.1 Stale student SPA bundle (most likely)

- The **admin panel is Filament**, which ships its own prebuilt JS — the new toggle renders even when this repo's Vite bundle is old/absent. The **student app is 100% Vite-built**, so it only gains the bonus block after a successful `vite build` + deploy of `public/build`.
- Evidence in the repo: failed `bun run build` logs at the repo root (`build_full.log`, `build_error.log`, `build_error.txt`, `build.log`) — e.g. `build_full.log` shows `error during build: [@tailwindcss/vite:generate:build] Cannot apply unknown utility class ease-[cubic-bezier(0.23,`. That specific class no longer exists in the code (verified), so a **rebuild should now succeed** — but until one is shipped, the deployed bundle predates the bonus UI.

**Verify:** in the built JS (`public/build/assets/*.js`) search for the bonus strings:
```
grep -rl "Bonus XP claimed\|claim-bonus-xp" public/build/assets | head
```
Empty ⇒ the deployed bundle is stale.

**Fix:**
```
bun run build        # or: npm run build   (requires PHP + artisan route:list)
php artisan migrate  # ensure bonus_xp_claims table exists (2026_08_17_000001)
php artisan cache:clear && php artisan config:clear
```
Deploy `public/build/` (plus the PHP changes) and hard-refresh the student browser.

### 3.2 Bonus setting not visible in the student's settings scope

`Setting::get()` reads **workspace-scoped rows first, then global** (`app/Models/Setting.php:21-36`). `AiSettings::save()` writes with the *admin's* `WorkspaceContext` scope (`Setting::set`, `app/Models/Setting.php:40`). If the admin's save landed in a workspace the students don't read, students fall back to the global map → `daily_claim_bonus_enabled` missing → default `false`.

The daily claim masks this: `daily_claim_enabled` **defaults to `true`** and `daily_claim_base_xp` to `1`, so daily claiming "works by default" even when its settings are also scoped away. Bonus defaults to `false`, so it silently disappears. This asymmetry explains the exact symptom.

**Verify** (in the DB, e.g. `php artisan tinker`):
```php
App\Models\Setting::where('key', 'daily_claim_bonus_enabled')->get(['key','value','workspace_id'])->each(fn($s) => dump($s->toArray()));
// Student-side read:
App\Models\User::find(<student_id>)->current_workspace_id;           // student scope
App\Models\User::find(<admin_id>)->current_workspace_id;             // admin scope
App\Models\Setting::get('daily_claim_bonus_enabled', 'MISSING');     // what the student sees
```
If the row has `workspace_id = N` but students have no workspace (or a different one), move/duplicate the row to `workspace_id = NULL` (global) or set the toggle while inspecting the students' workspace.

### 3.3 Lower-probability

- **Settings cache:** `Setting` uses `Cache::rememberForever` but flushes on every `saved`/`deleted` (`app/Models/Setting.php:47-67`). If a custom cache store is misconfigured, `php artisan cache:clear` resolves it.
- **Migration not run:** the dashboard would 500 (query on missing `bonus_xp_claims`), so this would not produce the reported symptom — but `php artisan migrate` is required anyway.

## 4 — What was added in this pass

| File | Purpose |
|---|---|
| `tests/Feature/BonusXpTest.php` | Backend regression suite for the bonus claim (settings toggle, flat amount, ledger row, history entry, daily/bonus independence, section/season mirroring, dashboard prop). Mirrors `ClaimXpTest.php` conventions. **The feature previously shipped with zero backend tests.** |
| `tests/js/bonus-xp-modal.test.ts` | Frontend regression suite mounting the real `LevelProgressCard.vue` against the exact `bonusXp` prop contract (renders when enabled, hidden when disabled, claimed state, local claim flow). **Passing: 4/4** (`npx vitest run tests/js/bonus-xp-modal.test.ts`). |

## 5 — Quick conclusion

If the students' page is served from an **old `public/build`**, rebuild + redeploy and the block appears (3.1). If it still doesn't, check the **settings scope** (3.2). Both are verifiable in minutes; no code defect was found in the feature itself.
