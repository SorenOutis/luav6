---
paths:
  - 'app/Services/Streak*.php'
  - app/Services/PendingAiActionService.php
---

# Services

## One writer for current_streak; never use last_login_at day-deltas
StreakRestoreService::repairStreak() is the ONLY writer of users.current_streak. StreakService::touch() stamps last_login_at and delegates to it — do not reintroduce day-delta arithmetic (increment on yesterday / reset to 1 on a gap) there. The two algorithms used to coexist: touch() read only users.last_login_at while repairStreak() recounted gamification_histories, so the next dashboard load after a restore hit touch()'s reset branch and wiped the repaired streak to 1, making the streak look like it only counted the current month. Active days come from StreakRestoreService::activityDates(), which excludes reason = 'Streak Restore' audit rows (they are XP debits stamped now(), not activity) — adding one back would make every restore mark today active for free.

## Impact metadata is computed in present(), not stored
`present()` adds an `impact` payload (`{level, label, note}`) computed by `impactFor()` from `action_type`: high for `delete_*` and `manage_maintenance`, standard for everything else. New action types default to standard — extend `impactFor()` if they should be high. The AI-chat frontend renders the red warning badge/note only when `level === 'high'`; standard changes get no badge by design.
