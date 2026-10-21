---
paths:
  - resources/js/lib/device.ts
---

# Lib

## Low-end detection must not flag ordinary desktops
isLowEndDeviceFrom() gates the whole site's motion (RevealGroup reveals, feature-card mini-demos, Lenis smooth scroll, the data-low-end CSS guard). It must NOT flag a desktop on hardware alone: ≤4 cores / ≤4 GB only count when the viewport is not a full-size desktop window, and `isCoarsePointer` means the *primary* pointer is coarse (a touch laptop has a mouse, so pointer stays fine even though ontouchstart exists). resources/views/app.blade.php duplicates this logic for the pre-paint flag — keep the two in sync.
