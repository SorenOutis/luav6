---
paths:
  - 'app/Services/Streak*.php'
---

# Services

## One writer for current_streak; never use last_login_at day-deltas
StreakRestoreService::repairStreak() is the ONLY writer of users.current_streak. StreakService::touch() stamps last_login_at and delegates to it — do not reintroduce day-delta arithmetic (increment on yesterday / reset to 1 on a gap) there. The two algorithms used to coexist: touch() read only users.last_login_at while repairStreak() recounted gamification_histories, so the next dashboard load after a restore hit touch()'s reset branch and wiped the repaired streak to 1, making the streak look like it only counted the current month. Active days come from StreakRestoreService::activityDates(), which excludes reason = 'Streak Restore' audit rows (they are XP debits stamped now(), not activity) — adding one back would make every restore mark today active for free.
