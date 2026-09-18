# Platform Settings — Write-Scope Bug (Registration / Login / Bonus XP)

**Date:** 2026-08-17 · **Repo:** `SorenOutis/luav6` @ `arena/01a00ebd-luav6`
**Reported symptoms:**
1. Enabling/disabling **Registration** (and other Platform Settings toggles) has no effect.
2. Enabling **Bonus XP** in Platform Settings → Daily XP Claim never shows the bonus claim block to students.

---

## 1 — Verdict

One root cause explains both symptoms (and most other "saved but no effect" Platform Settings):

**The tenant-workspaces migration (`2026_08_16_000005_create_tenant_workspaces.php`) made every `Setting::set()` write scoped to the admin's current workspace (`workspace_id = <admin's own workspace>`), but the code that *consumes* these settings never reads from that scope.**

- **Registration / Login** toggles are consumed by **unauthenticated** requests (`CreateNewUser`, `EnsureLoginIsEnabled`, the Welcome/Login/Register pages). Unauthenticated requests have **no workspace**, so `Setting::get()` reads **only the global map** (`workspace_id IS NULL`). The admin's rows live in the admin's workspace → invisible → the hardcoded defaults (`registration_enabled = true`, `login_enabled = true`) always win. **The toggles are a guaranteed no-op on every install that ran the tenant migration.**
- **Bonus XP** (`daily_claim_bonus_enabled`, default `false`) is read from the **student's** workspace scope with a global fallback. When the student isn't in the exact workspace the admin saved from (multi-admin installs — the migration creates one workspace *per admin*; students join the workspace of the teacher who created their section; students without a section have no workspace at all), the student falls back to global → no row → `false` → the bonus block never renders and `BonusXpService::isEnabled()` returns `false`. The **daily claim masks the same bug** because its default is `true` (base XP default `1`), which is exactly why "daily XP works but bonus XP doesn't".
- **Everything else on the page has the same exposure**: `ai_chat_enabled`, provider API keys (students in other workspaces fall back to env vars), school branding, `welcome_demo_video_path` (read by an unauthenticated controller → always broken), and the **Student Page Controls** page (`student_page_controls`).

The admin panel *appears* to work because `AiSettings::mount()` reads settings in the **same** workspace scope it writes to — the toggle shows saved, but the effect isn't seen where the setting is actually consumed.

The `INVESTIGATION_BONUS_XP_MODAL.md` doc (stale student bundle) and `ANALYSIS_BONUS_XP_AND_SCROLL.md` mapped the wiring as correct — they were: the backend, route, ledger, and modal are all wired. The failure is **where the settings rows are stored**, not the feature plumbing.

---

## 2 — Scope diagram

```
AiSettings::save()  →  Setting::set(key, value)   [before fix]
                        └─ workspace_id = admin's current_workspace_id
                                 │
             ┌───────────────────┴──────────────────────┐
             ▼                                          ▼
  Public pages (guests)                     Student dashboard
  WorkspaceContext::id() = null             WorkspaceContext::id() = student's workspace
  Setting::get → global map ONLY            Setting::get → student's workspace, then global
             │                                          │
             ▼                                          ▼
  registration_enabled → default true        daily_claim_bonus_enabled → default false
  login_enabled       → default true         (daily_claim_enabled → default true — masks bug)
```

## 3 — The fix (this branch)

1. **`Setting::setGlobal()`** (`app/Models/Setting.php`) — persists a platform-wide row (`workspace_id = null`), keeping the audit `admin_id`.
2. **`AiSettings::save()`** — every platform-wide key now uses `setGlobal()`. Only the explicitly labeled **"Workspace AI Budget & Fallback"** section (`ai_budget_*`, `ai_fallback_*`, `ai_budget_cost_rates`, `ollama_enabled`) stays workspace-scoped, so per-workspace budget enforcement is unchanged.
3. **`StudentPageRegistry::setControls()`** — `student_page_controls` now writes globally.
4. **Migration `2026_08_17_000002_consolidate_platform_settings_global.php`** — repairs existing installs: for each platform-wide key, workspace-scoped rows left behind by the tenant migration are consolidated into the global scope (newest row wins; stale rows deleted so they can never shadow the global value). Workspace-scoped budget keys are untouched.
5. **`tests/Feature/PlatformSettingsScopeTest.php`** — regression tests: admin panel saves land global (and budget keys stay scoped); a guest sees/enforces the registration toggle; a student in a *different* workspace than the admin sees Bonus XP enabled (the exact reported bug); student page controls write globally.

## 4 — Deploy steps

```bash
php artisan migrate          # runs the consolidation migration
php artisan cache:clear      # flush cached settings maps
```

Then re-save Platform Settings once from the admin panel to write the canonical global rows.

## 5 — Notes / residual items

- **Public settings can only be global.** Registration/login/branding are read by guests, which by definition have no workspace — a per-workspace interpretation of those toggles is impossible with the current `Setting::get()` contract.
- **Stale workspace rows for platform keys are deleted by the migration** — global values win; no silent shadowing afterwards.
- **AI provider credentials are now global** (they were global before the tenant migration; students and admins in any workspace fall back to them). Per-workspace isolation of credentials was never functional for students in other workspaces.
- `chats_enabled` / `chats_maintenance_message` are already seeded global and have no writers — untouched.
