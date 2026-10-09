---
paths:
  - resources/js/components/dashboard/StreakCalendarModal.vue
---

# Dashboard

## Streak calendar window vs streak lookback
The calendar's restored-day set arrives windowed: the API's restored_dates is capped at StreakRestoreService::CALENDAR_WINDOW_DAYS (90) while the streak recount reads all restores. The API also echoes restored_date for the day just restored — merge it into localRestoredDates on success, otherwise restoring a day older than 90 days leaves its cell looking untouched and still restorable, and tapping it returns "already been restored". Also note the calendar grid is built from browser-local Date math while the server's today is app-timezone; server dates are plain Y-m-d strings and must be compared as strings, never parsed with new Date().
